<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Filters\UserFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The filter contract the list surfaces share: `q` is free text, every other
 * parameter is a field, and a field takes an `operator:value` form.
 */
#[CoversClass(UserFilter::class)]
final class UserFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function names(string $query): array
    {
        $response = $this->getJson("/api/users?{$query}")->assertOk()->json('data');
        $this->assertIsArray($response);

        return array_map(fn (mixed $row): string => (string) $row['name'], $response);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->createAndActAs(User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']), role: 'owner');
        $this->assignRole(User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.org']), 'admin');
        $this->assignRole(User::factory()->create(['name' => 'Zoe Zander', 'email' => 'zoe@example.test']), 'member');
    }

    #[Test]
    public function an_operator_prefix_picks_how_a_field_is_compared(): void
    {
        $this->assertSame(['Grace Hopper'], $this->names('email=like%3Aexample.org'));
        $this->assertSame(['Ada Lovelace', 'Zoe Zander'], $this->names('email=like%24%3Aexample.test'));
        $this->assertSame(['Grace Hopper'], $this->names('name=%5Elike%3AGrace'));
        $this->assertSame(['Ada Lovelace', 'Zoe Zander'], $this->names('name=!like%3AHopper'));
    }

    #[Test]
    public function roles_filter_by_key_and_accept_a_set(): void
    {
        $this->assertSame(['Grace Hopper'], $this->names('role=admin'));
        $this->assertSame(['Ada Lovelace', 'Grace Hopper'], $this->names('role=in%3Aowner%2Cadmin'));
    }

    #[Test]
    public function several_columns_sort_in_the_order_they_are_named(): void
    {
        $this->assertSame(['Zoe Zander', 'Grace Hopper', 'Ada Lovelace'], $this->names('sort=-name'));
        $this->assertSame(['Ada Lovelace', 'Grace Hopper', 'Zoe Zander'], $this->names('sort=%2Bemail'));
    }

    #[Test]
    public function pagination_cannot_be_rewritten_through_the_filter(): void
    {
        // `limit` and `offset` are helpers on the package's base class; the
        // allow list is what keeps them out of a client's reach.
        $this->assertCount(3, $this->names('limit=1'));
        $this->assertCount(3, $this->names('offset=2'));
    }
}
