<?php

namespace App\Services;

use App\Models\User;
use App\Support\Media;
use App\Support\Proof;
use Illuminate\Support\Facades\DB;

/**
 * Removes a user with their whole business: Hapus akun, and "Coba demo"
 * accounts once they run out. MySQL checks NO ACTION and RESTRICT keys row by
 * row while it cascades, so a plain delete fails on any business with books
 * (transactions → contacts, transaction lines → items, recipes → materials).
 * The books go first, child before parent; deleting the business then
 * cascades what is left. Files go once the rows are gone.
 */
class UserRemoval
{
    public function remove(User $user): void
    {
        $business = $user->business;
        $files = $business === null ? null : [
            'media' => [...DB::table('items')->where('business_id', $business->id)->whereNotNull('photo_path')->pluck('photo_path'), ...array_filter([$business->qris_path])],
            'proofs' => DB::table('transactions')->where('business_id', $business->id)->whereNotNull('proof_path')->pluck('proof_path')->all(),
        ];

        DB::transaction(function () use ($user, $business) {
            if ($business !== null) {
                $id = $business->id;
                // Orders and transactions point at each other; one side lets go first.
                DB::table('orders')->where('business_id', $id)->update(['transaction_id' => null]);
                DB::table('fixed_assets')->where('business_id', $id)->delete();
                DB::table('journal_entries')->where('business_id', $id)->delete();
                DB::table('transactions')->where('business_id', $id)->delete();
                DB::table('orders')->where('business_id', $id)->delete();
                DB::table('recipe_lines')->whereIn('product_id', DB::table('items')->where('business_id', $id)->select('id'))->delete();
                $business->delete();
            }

            $user->delete();
        });

        if ($files !== null) {
            Media::disk()->delete($files['media']);
            Proof::disk()->delete($files['proofs']);
        }
    }
}
