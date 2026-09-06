<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Avatars live on the private disk and are read back through the app, not
 * from a public URL. That keeps a members-only installation members-only,
 * needs no `storage:link`, and works unchanged on S3.
 *
 * The client sends an already-cropped square, so there is no image library
 * here: the upload is validated as a real raster image and stored as it
 * arrived.
 */
class AvatarStorage
{
    private const string DIRECTORY = 'avatars';

    public function store(User $user, UploadedFile $file): string
    {
        $previous = $user->avatar_path;

        $path = self::DIRECTORY.'/'.$user->id.'/'.Str::ulid()->toString().'.'.$this->extension($file);
        $this->disk()->put($path, $file->get(), 'private');

        $user->avatar_path = $path;
        $user->save();

        // Only after the new one is committed: a failed write must not leave
        // the account with no avatar at all.
        if (is_string($previous)) {
            $this->disk()->delete($previous);
        }

        return $path;
    }

    public function remove(User $user): void
    {
        $path = $user->avatar_path;

        if (! is_string($path)) {
            return;
        }

        $user->avatar_path = null;
        $user->save();

        $this->disk()->delete($path);
    }

    public function read(User $user): ?string
    {
        $path = $user->avatar_path;

        if (! is_string($path) || ! $this->disk()->exists($path)) {
            return null;
        }

        return $this->disk()->get($path);
    }

    public function mimeType(User $user): string
    {
        return match (pathinfo($user->avatar_path ?? '', PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * The URL the SPA renders. The version segment is derived from the stored
     * path, so replacing an avatar changes the URL and no cache has to be told.
     */
    public static function url(User $user): ?string
    {
        $path = $user->avatar_path;

        if (! is_string($path)) {
            return null;
        }

        return '/api/users/'.$user->id.'/avatar?v='.substr(hash('sha256', $path), 0, 12);
    }

    private function extension(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('kit.avatars.disk'));
    }
}
