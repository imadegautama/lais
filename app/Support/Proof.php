<?php

namespace App\Support;

use App\Http\Requests\AssistantRequest;
use App\Models\Transaction;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Bukti transaksi (PRD M2): a transfer screenshot or a receipt photo.
 * It shows account numbers, so it lives on a private disk (`PROOF_DISK`:
 * local in development, the private R2 bucket in production) and is only
 * served to the owner (TransactionController::proof). Files are never
 * deleted, like the records they belong to.
 */
class Proof
{
    /** Validation rules, the same on every form that takes a proof. */
    public const RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

    /** @var array<string, string> */
    public const MESSAGES = [
        'proof.image' => 'Bukti harus berupa gambar JPG, PNG, atau WebP.',
        'proof.mimes' => 'Bukti harus berupa gambar JPG, PNG, atau WebP.',
        'proof.max' => 'Ukuran bukti maksimal 5 MB.',
    ];

    public static function disk(): Filesystem
    {
        return Storage::disk((string) config('filesystems.proof_disk'));
    }

    /**
     * Store the photo and point the transaction at it. Called after the
     * transaction is saved, so a failed record never leaves a stray file.
     *
     * @param  UploadedFile|string  $photo  an upload, or a data URI from the assistant
     */
    public static function attach(Transaction $transaction, UploadedFile|string $photo): void
    {
        $folder = "bukti/{$transaction->business_id}";

        if ($photo instanceof UploadedFile) {
            $path = $photo->store($folder, (string) config('filesystems.proof_disk'));
        } else {
            if (! AssistantRequest::isPhoto($photo) || preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s', $photo, $parts) !== 1) {
                throw new InvalidArgumentException('Not a photo data URI.');
            }

            $path = $folder.'/'.Str::random(40).'.'.($parts[1] === 'jpeg' ? 'jpg' : $parts[1]);
            self::disk()->put($path, (string) base64_decode($parts[2], true));
        }

        $transaction->forceFill(['proof_path' => $path ?: null])->save();
    }
}
