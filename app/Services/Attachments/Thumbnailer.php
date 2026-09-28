<?php

declare(strict_types=1);

namespace App\Services\Attachments;

use GdImage;

/**
 * A small WebP preview of an uploaded image, made with GD because it ships
 * with PHP: no system library, nothing extra in the image. Re-encoding also
 * drops the metadata, so a phone photo's GPS position never reaches the
 * preview. The original is kept byte for byte.
 */
class Thumbnailer
{
    /**
     * The WebP bytes, or null when the input is not an image GD can read or
     * is too large to decode safely.
     */
    public function make(string $contents): ?string
    {
        $info = @getimagesizefromstring($contents);

        if ($info === false || (int) config('kit.attachments.thumbnail_max_pixels') < $info[0] * $info[1]) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            return null;
        }

        $image = $this->orient($image, $contents, $info[2]);
        [$width, $height] = $this->fit(imagesx($image), imagesy($image), (int) config('kit.attachments.thumbnail_size'));

        // Resampled onto a fresh canvas rather than imagescale(), whose
        // better modes are missing from some GD builds. Alpha is kept, so a
        // transparent PNG stays transparent.
        $scaled = imagecreatetruecolor($width, $height);

        if ($scaled === false) {
            return null;
        }

        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, (int) imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        ob_start();
        imagewebp($scaled, null, 80);

        return (string) ob_get_clean();
    }

    /**
     * Within a square box, never enlarged.
     *
     * @return array{0: int, 1: int}
     */
    private function fit(int $width, int $height, int $box): array
    {
        $scale = min(1, $box / max($width, $height));

        return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
    }

    /**
     * Cameras store a JPEG as the sensor saw it and say how to turn it in
     * EXIF. Dropping the metadata without applying the turn would leave the
     * preview on its side.
     */
    private function orient(GdImage $image, string $contents, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $angle = match (is_array($exif) ? ($exif['Orientation'] ?? 1) : 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated === false ? $image : $rotated;
    }
}
