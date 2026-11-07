<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * A photo as a JPEG data URI no larger than the assistant reads: the same
 * shape the browser makes in lib/image.ts, for photos that arrive by WhatsApp.
 */
class ImageData
{
    public static function fromBytes(string $bytes, int $maxSide = 1600): string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new InvalidArgumentException('Not an image.');
        }

        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, $maxSide / max($width, $height));

        if ($scale < 1) {
            $image = imagescale($image, (int) round($width * $scale), (int) round($height * $scale)) ?: $image;
        }

        ob_start();
        imagejpeg($image, null, 82);

        return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
    }
}
