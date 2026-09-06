<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Account\SavedViewController;
use App\Models\SavedView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SavedViewController::class)]
final class SavedViewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_view_round_trips_and_saving_the_same_name_twice_replaces_it(): void
    {
        $this->createAndActAs(role: 'owner');

        $this->postJson('/api/account/views', [
            'scope' => 'people',
            'name' => 'Pending admins',
            'params' => ['q' => 'a', 'role' => 'admin', 'status' => 'pending'],
        ])->assertCreated()->assertJsonPath('params.role', 'admin');

        $this->postJson('/api/account/views', [
            'scope' => 'people',
            'name' => 'Pending admins',
            'params' => ['role' => 'member'],
        ])->assertCreated()->assertJsonPath('params.role', 'member');

        $this->getJson('/api/account/views?scope=people')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Pending admins');
    }

    #[Test]
    public function empty_parameters_are_dropped_so_applying_a_view_does_not_write_blanks(): void
    {
        $this->createAndActAs(role: 'owner');

        $this->postJson('/api/account/views', [
            'scope' => 'people',
            'name' => 'Everyone',
            'params' => ['q' => '', 'role' => null, 'sort' => '-created_at'],
        ])
            ->assertCreated()
            ->assertExactJsonStructure(['id', 'scope', 'name', 'params' => ['sort'], 'isDefault', 'createdAt']);
    }

    #[Test]
    public function only_one_view_per_scope_is_the_default(): void
    {
        $user = $this->createAndActAs(role: 'owner');

        $this->postJson('/api/account/views', [
            'scope' => 'people', 'name' => 'A', 'params' => ['sort' => '+name'], 'isDefault' => true,
        ])->assertCreated();

        $this->postJson('/api/account/views', [
            'scope' => 'people', 'name' => 'B', 'params' => ['sort' => '-name'], 'isDefault' => true,
        ])->assertCreated();

        $defaults = $user->savedViews()->where('is_default', true)->pluck('name')->all();
        $this->assertSame(['B'], $defaults);
    }

    #[Test]
    public function a_view_belongs_to_the_user_who_saved_it(): void
    {
        $owner = $this->createAndActAs(role: 'owner');
        $view = $owner->savedViews()->create([
            'scope' => 'people', 'name' => 'Mine', 'params' => ['q' => 'x'], 'is_default' => false,
        ]);

        $this->createAndActAs(User::factory()->create(), role: 'admin');

        $this->getJson('/api/account/views')->assertOk()->assertJsonCount(0);
        $this->deleteJson("/api/account/views/{$view->id}")->assertNotFound();
        $this->patchJson("/api/account/views/{$view->id}", ['name' => 'Yours'])->assertNotFound();

        $this->assertSame('Mine', SavedView::query()->findOrFail($view->id)->name);
    }
}
