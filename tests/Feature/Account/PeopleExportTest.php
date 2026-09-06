<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Account\PeopleController;
use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use App\Services\Account\PeopleDirectory;
use App\Support\Export\ExportFormat;
use App\Support\Export\ListExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PeopleController::class)]
#[CoversClass(ListExport::class)]
#[CoversClass(ExportFormat::class)]
final class PeopleExportTest extends TestCase
{
    use RefreshDatabase;

    private function invite(string $email, string $role = 'member'): Invite
    {
        return Invite::query()->create([
            'email' => $email,
            'role_id' => Role::byKey($role)->id,
            'token_hash' => Invite::hashToken('token'),
            'expires_at' => now()->addDays(7),
        ]);
    }

    /** @return list<list<string>> */
    private function csv(string $url): array
    {
        $body = $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $rows = array_map(
            fn (string $line): array => str_getcsv($line, escape: ''),
            array_filter(explode("\n", str_replace(["\u{FEFF}", "\r"], '', $body)), fn (string $line): bool => $line !== ''),
        );

        /** @var list<list<string>> */
        return array_values($rows);
    }

    #[Test]
    public function the_file_carries_the_same_rows_the_filtered_list_would_show(): void
    {
        $owner = $this->createAndActAs(role: 'owner');
        $owner->name = 'Ada';
        $owner->email = 'ada@example.test';
        $owner->save();

        $this->assignRole(User::factory()->create(['name' => 'Zoe', 'email' => 'zoe@example.test']), 'member');
        $this->invite('bob@example.test');

        $rows = $this->csv('/api/people/export?format=csv&q=example.test&sort=-name');

        // Header first, then every match, newest sort honoured, no paging.
        $this->assertSame(['Name', 'Email', 'Role', 'Status', 'Type', 'Joined', 'Invitation expires'], $rows[0]);
        $this->assertSame(['zoe@example.test', 'bob@example.test', 'ada@example.test'], array_column(array_slice($rows, 1), 1));

        $filtered = $this->csv('/api/people/export?format=csv&status=pending');
        $this->assertSame([['bob@example.test']], array_map(fn (array $row): array => [$row[1]], array_slice($filtered, 1)));
    }

    #[Test]
    public function paging_parameters_are_ignored_so_the_file_is_the_whole_result_set(): void
    {
        $this->createAndActAs(role: 'owner');
        $this->invite('bob@example.test');
        $this->invite('cleo@example.test');

        $rows = $this->csv('/api/people/export?format=csv&page=2&per_page=1');

        $this->assertCount(4, $rows, 'One heading row and every match.');
    }

    #[Test]
    public function the_row_cap_truncates_the_file_and_says_so(): void
    {
        $this->createAndActAs(role: 'owner');
        $this->invite('bob@example.test');
        $this->invite('cleo@example.test');

        $capped = app(PeopleDirectory::class)->export($this->user, [], 2);

        $this->assertSame(3, $capped['total'], 'The count is of the result set, not of the file.');
        $this->assertCount(2, iterator_to_array($capped['rows'], false));

        // Every format states its own cap, and PDF lays every row out so it
        // stops earliest.
        $this->assertSame(ListExport::MAX_ROWS, ExportFormat::Csv->rowLimit());
        $this->assertLessThan(ExportFormat::Xlsx->rowLimit(), ExportFormat::Pdf->rowLimit());
    }

    #[Test]
    public function it_is_gated_exactly_as_the_list_is(): void
    {
        $this->seedRoles();

        $this->createAndActAs(role: 'member');
        $this->get('/api/people/export?format=csv')->assertForbidden();

        $this->createAndActAs(role: 'admin');
        $this->get('/api/people/export?format=csv')->assertOk();
    }

    #[Test]
    public function an_unknown_format_is_refused_rather_than_guessed(): void
    {
        $this->createAndActAs(role: 'owner');

        $this->getJson('/api/people/export?format=exe')->assertStatus(422);
        $this->getJson('/api/people/export')->assertStatus(422);
    }

    #[Test]
    public function the_spreadsheet_and_the_pdf_come_back_as_their_own_file_types(): void
    {
        $this->createAndActAs(role: 'owner');

        $xlsx = $this->get('/api/people/export?format=xlsx')->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $pdf = $this->get('/api/people/export?format=pdf')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());
    }
}
