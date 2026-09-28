<?php

declare(strict_types=1);

namespace App\Services\Attachments;

use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Keeps an attachment's row and its file together. Files live on a private
 * disk under a name of our own and are read back through the app, so the
 * record's policy is what guards them, locally and on S3 alike.
 */
class AttachmentStorage
{
    private const string DIRECTORY = 'attachments';

    public function store(Model $record, UploadedFile $file, User $uploader): Attachment
    {
        $disk = (string) config('kit.attachments.disk');
        $path = self::DIRECTORY.'/'.Str::ulid()->toString().'.'.$this->extension($file);
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $size = @getimagesize($file->getRealPath());

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path), 'private');

        /** @var Attachment $attachment */
        $attachment = $record->morphMany(Attachment::class, 'attachable')->create([
            'uploaded_by' => $uploader->id,
            'disk' => $disk,
            'path' => $path,
            'name' => $this->displayName($file),
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'width' => is_array($size) ? $size[0] : null,
            'height' => is_array($size) ? $size[1] : null,
        ]);

        if ($attachment->isImage()) {
            GenerateThumbnail::dispatch($attachment->id)->afterCommit();
        }

        return $attachment;
    }

    /** The row goes now; the files once that is committed, so a rollback cannot orphan the row. */
    public function delete(Attachment $attachment): void
    {
        $attachment->delete();

        $disk = $this->disk($attachment);
        $paths = array_filter([$attachment->path, $attachment->thumbnail_path]);

        DB::afterCommit(fn () => $disk->delete($paths));
    }

    public function writeThumbnail(Attachment $attachment, string $contents): void
    {
        $path = self::DIRECTORY.'/thumbnails/'.$attachment->id.'.webp';

        $this->disk($attachment)->put($path, $contents, 'private');

        $attachment->thumbnail_path = $path;
        $attachment->save();
    }

    public function contents(Attachment $attachment): ?string
    {
        return $this->disk($attachment)->get($attachment->path);
    }

    /**
     * A raster image or a PDF opens in the tab; everything else downloads.
     * The sandbox policy is the second lock: even a file a browser decides to
     * sniff as HTML cannot run script on this origin.
     */
    public function response(Attachment $attachment, bool $thumbnail = false): StreamedResponse
    {
        $path = $thumbnail ? $attachment->thumbnail_path : $attachment->path;
        abort_if($path === null || ! $this->disk($attachment)->exists($path), 404);

        $inline = $thumbnail || $attachment->isImage() || $attachment->mime_type === 'application/pdf';

        return Storage::disk($attachment->disk)->response(
            $path,
            $attachment->name,
            [
                'Content-Type' => $thumbnail ? 'image/webp' : $attachment->mime_type,
                'Content-Security-Policy' => 'sandbox',
                // A row never changes its file, so its URL never serves another.
                'Cache-Control' => 'private, max-age=31536000, immutable',
            ],
            $inline ? 'inline' : 'attachment',
        );
    }

    /**
     * From the bytes, never the client's filename: a GIF called `x.html`
     * must not be stored, or served, as a document.
     */
    private function extension(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->guessExtension());

        return preg_match('/^[a-z0-9]{1,8}$/', $extension) === 1 ? $extension : 'bin';
    }

    /** Shown in the list and sent in the download header, so no path and no control characters. */
    private function displayName(UploadedFile $file): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $file->getClientOriginalName()) ?? '';

        return Str::limit(trim($name) === '' ? 'file' : trim($name), 200, '');
    }

    private function disk(Attachment $attachment): Filesystem
    {
        return Storage::disk($attachment->disk);
    }
}
