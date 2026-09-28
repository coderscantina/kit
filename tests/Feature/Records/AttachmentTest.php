<?php

declare(strict_types=1);

namespace Tests\Feature\Records;

use App\Http\Controllers\Records\AttachmentController;
use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use App\Models\User;
use App\Queries\Attachments\ListAttachments;
use App\Services\Attachments\AttachmentStorage;
use App\Services\Attachments\Thumbnailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Kit\Reactive\Facades\Reactive;
use Kit\Reactive\Testing\InteractsWithReactive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fixtures\Records\Widget;
use Tests\TestCase;

#[CoversClass(AttachmentController::class)]
#[CoversClass(AttachmentStorage::class)]
#[CoversClass(ListAttachments::class)]
#[CoversClass(GenerateThumbnail::class)]
#[CoversClass(Thumbnailer::class)]
final class AttachmentTest extends TestCase
{
    use InteractsWithReactive;
    use RefreshDatabase;

    private User $owner;

    private Widget $widget;

    protected function setUp(): void
    {
        parent::setUp();

        Widget::install();
        Storage::fake('local');

        $this->owner = $this->createAndActAs(role: 'member');
        $this->widget = Widget::query()->create(['owner_id' => $this->owner->id, 'name' => 'Gear']);
    }

    #[Test]
    public function an_image_upload_is_stored_under_our_name_and_gets_a_thumbnail(): void
    {
        $response = $this->upload(UploadedFile::fake()->image('holiday.jpg', 1600, 1200))->assertCreated();

        $attachment = Attachment::query()->findOrFail($response->json('id'));

        $this->assertSame('holiday.jpg', $attachment->name);
        $this->assertSame([1600, 1200], [$attachment->width, $attachment->height]);
        $this->assertMatchesRegularExpression('#^attachments/[0-9A-Z]{26}\.jpg$#', $attachment->path);
        Storage::disk('local')->assertExists($attachment->path);

        // The queue is synchronous here, so the preview exists already.
        $this->assertNotNull($attachment->thumbnail_path);
        [$width, $height] = getimagesizefromstring((string) Storage::disk('local')->get($attachment->thumbnail_path)) ?: [0, 0];
        $this->assertSame([480, 360], [$width, $height]);
    }

    #[Test]
    public function the_list_is_live_on_other_screens(): void
    {
        Reactive::fake();
        $this->actingAsSubscriber($this->owner, 'attachments.list', ['type' => 'widgets', 'id' => $this->widget->id]);

        $this->upload(UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf'))->assertCreated();

        Reactive::assertPushed('attachments.list', fn (mixed $result): bool => $result[0]['name'] === 'notes.pdf');
    }

    #[Test]
    public function only_someone_who_may_update_the_record_may_attach_to_it(): void
    {
        $this->createAndActAs(role: 'member');

        $this->upload(UploadedFile::fake()->image('x.png'))->assertForbidden();
    }

    #[Test]
    public function svg_and_disguised_files_are_refused(): void
    {
        $this->upload($this->file('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
            ->assertUnprocessable();

        // Named like a PDF, sniffed as what it is.
        $this->upload($this->file('report.pdf', "<?php\nsystem(\$_GET['c']);\n"))
            ->assertUnprocessable();
    }

    #[Test]
    public function an_image_opens_inline_and_anything_else_downloads_under_a_sandbox(): void
    {
        $image = $this->upload(UploadedFile::fake()->image('a.png'))->json('id');
        $archive = $this->upload($this->file('b.zip', $this->zip()))->json('id');

        $shown = $this->get("/api/attachments/{$image}")->assertOk();
        $this->assertStringStartsWith('inline', (string) $shown->headers->get('Content-Disposition'));
        $this->assertSame('sandbox', $shown->headers->get('Content-Security-Policy'));

        $downloaded = $this->get("/api/attachments/{$archive}")->assertOk();
        $this->assertStringStartsWith('attachment', (string) $downloaded->headers->get('Content-Disposition'));
    }

    #[Test]
    public function deleting_the_record_deletes_its_files(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('a.png'))->json('id');
        $attachment = Attachment::query()->findOrFail($id);

        $this->widget->delete();

        $this->assertNull(Attachment::query()->find($id));
        Storage::disk('local')->assertMissing($attachment->path);
    }

    #[Test]
    public function removing_one_file_takes_update_on_the_record(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('a.png'))->json('id');

        $this->createAndActAs(role: 'member');
        $this->deleteJson("/api/attachments/{$id}")->assertForbidden();

        $this->actingAs($this->owner);
        $this->deleteJson("/api/attachments/{$id}")->assertNoContent();
        $this->assertNull(Attachment::query()->find($id));
    }

    /**
     * @return TestResponse<Response>
     */
    private function upload(UploadedFile $file): TestResponse
    {
        return $this->post('/api/attachments', ['type' => 'widgets', 'id' => $this->widget->id, 'file' => $file], ['Accept' => 'application/json']);
    }

    /** A real temp file: a fake's mime type comes from its name, and these tests are about the bytes. */
    private function file(string $name, string $contents): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function zip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('a.txt', 'hello');
        $zip->close();

        return (string) file_get_contents($path);
    }
}
