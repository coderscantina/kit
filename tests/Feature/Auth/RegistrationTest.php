<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Users\CreateUser;
use App\Http\Controllers\Auth\RegisterController;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(RegisterController::class)]
#[CoversClass(CreateUser::class)]
final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(string $email = 'first@example.test'): array
    {
        return ['name' => 'First', 'email' => $email, 'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery'];
    }

    #[Test]
    public function the_first_account_becomes_owner_and_closes_registration(): void
    {
        Notification::fake();
        $this->seedRoles();

        $this->postJson('/auth/register', $this->payload())
            ->assertCreated()
            ->assertJsonPath('user.role', 'owner')
            ->assertJsonPath('user.emailVerified', false);

        $user = User::query()->firstOrFail();
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->post('/auth/logout');

        $this->postJson('/auth/register', $this->payload('second@example.test'))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'REGISTRATION_CLOSED');
    }

    #[Test]
    public function the_override_reopens_registration_but_later_accounts_get_no_role(): void
    {
        Notification::fake();
        $this->seedRoles();
        config(['features.registration' => 'true']);

        User::factory()->create();

        $this->postJson('/auth/register', $this->payload())
            ->assertCreated()
            ->assertJsonPath('user.role', null);
    }

    #[Test]
    public function a_blank_name_is_rejected_even_though_it_is_a_string(): void
    {
        $this->seedRoles();

        $this->postJson('/auth/register', [...$this->payload(), 'name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }
}
