<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Account\AvatarController;
use App\Models\User;
use App\Services\Account\AvatarStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AvatarController::class)]
#[CoversClass(AvatarStorage::class)]
final class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    #[Test]
    public function uploading_stores_the_image_and_hands_back_a_versioned_url(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $url = $this->postJson('/api/account/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 256, 256)])
            ->assertOk()
            ->json('avatarUrl');

        $path = $user->fresh()?->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        // Versioned off the stored path, so a replacement is a different URL.
        $this->assertStringStartsWith("/api/users/{$user->id}/avatar?v=", (string) $url);
    }

    #[Test]
    public function replacing_an_avatar_removes_the_one_it_replaced(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/avatar', ['avatar' => UploadedFile::fake()->image('one.png', 256, 256)])->assertOk();
        $first = (string) $user->fresh()?->avatar_path;

        $this->postJson('/api/account/avatar', ['avatar' => UploadedFile::fake()->image('two.png', 256, 256)])->assertOk();
        $second = (string) $user->fresh()?->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);
    }

    #[Test]
    public function removing_clears_the_column_and_the_file(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $this->postJson('/api/account/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 256, 256)])->assertOk();
        $path = (string) $user->fresh()?->avatar_path;

        $this->deleteJson('/api/account/avatar')->assertOk()->assertJsonPath('avatarUrl', null);

        $this->assertNull($user->fresh()?->avatar_path);
        Storage::disk('local')->assertMissing($path);
    }

    #[Test]
    public function anything_that_is_not_a_raster_image_is_refused(): void
    {
        $this->createAndActAs(role: 'member');

        // An SVG is a document that can carry script, not a picture.
        $this->postJson('/api/account/avatar', ['avatar' => UploadedFile::fake()->create('logo.svg', 8, 'image/svg+xml')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        $this->postJson('/api/account/avatar', ['avatar' => UploadedFile::fake()->image('huge.png', 4000, 4000)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');
    }

    #[Test]
    public function avatars_are_served_through_the_app_and_need_a_session(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $this->postJson('/api/account/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 256, 256)])->assertOk();

        $this->getJson("/api/users/{$user->id}/avatar")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // Nothing to serve for an account that never set one.
        $other = User::factory()->create();
        $this->getJson("/api/users/{$other->id}/avatar")->assertNotFound();

        $this->postJson('/auth/logout')->assertNoContent();
        $this->getJson("/api/users/{$user->id}/avatar")->assertUnauthorized();
    }
}
