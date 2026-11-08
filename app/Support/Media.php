<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Public files: product photos and the shop's QRIS. They sit on the local
 * `public` disk in development and tests, and on the public R2 bucket in
 * production (`MEDIA_DISK`, config/filesystems.php).
 */
class Media
{
    public static function diskName(): string
    {
        return (string) config('filesystems.media_disk');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    public static function url(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        // The local disk follows whichever host opened the page; R2 has its own domain.
        return self::diskName() === 'public' ? asset('storage/'.$path) : self::disk()->url($path);
    }
}
