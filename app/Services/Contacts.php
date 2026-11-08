<?php

namespace App\Services;

use App\Enums\ActivityCategory;
use App\Enums\OrderStatus;
use App\Models\Contact;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Removing a person's data at the owner's request (PRD §4.5). The books stay
 * whole: a contact with history keeps its row under a neutral name, so every
 * record and report reads as before, and only one without history is deleted.
 * Journal descriptions and the activity log keep what they recorded, because
 * they are the audit trail.
 */
class Contacts
{
    public function __construct(private Ledger $ledger) {}

    /**
     * Why this contact cannot be removed yet, in the owner's words.
     */
    public function blockedBy(Contact $contact): ?string
    {
        if (($this->ledger->contactBalances($contact->business, $contact->id)[$contact->id] ?? 0) !== 0) {
            return "{$contact->name} masih punya sisa tagihan. Lunasi dulu, baru datanya bisa dihapus.";
        }

        $running = Order::query()->where('contact_id', $contact->id)
            ->whereIn('status', [OrderStatus::Menunggu, OrderStatus::Diproses, OrderStatus::Siap])->exists();

        return $running ? "{$contact->name} masih punya pesanan yang berjalan. Selesaikan dulu, baru datanya bisa dihapus." : null;
    }

    public function erase(Contact $contact): void
    {
        DB::transaction(function () use ($contact) {
            // Quietly, or the change itself would write the name and number into the log.
            activity(ActivityCategory::Kontak)->performedOn($contact)->log('Menghapus data kontak');

            $orders = Order::query()->where('contact_id', $contact->id);

            if (! $contact->transactions()->exists() && ! $orders->exists()) {
                $contact->deleteQuietly();

                return;
            }

            $name = "Kontak terhapus #{$contact->id}";
            $orders->update(['customer_name' => $name, 'customer_phone' => '', 'address' => null, 'note' => null]);
            $contact->forceFill(['name' => $name, 'phone' => null, 'erased_at' => now()])->saveQuietly();
        });
    }
}
