<?php

namespace App\Services;

use App\Models\User;
use Database\Seeders\DemoBusinessSeeder;
use Database\Seeders\DemoHistorySeeder;
use Database\Seeders\DemoPhotoSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Coba demo": each visitor gets an account of their own with the Warung Bu
 * Rina books, so judges never share data or a Telegram link. Filling one
 * takes several seconds, so a few wait ready in a pool (lais:demo, every five
 * minutes); a claimed one lasts HOURS and is then removed with its business.
 */
class DemoAccounts
{
    public const HOURS = 48;

    public function __construct(private UserRemoval $removal) {}

    /**
     * A ready account for this visitor, made on the spot if the pool is empty.
     */
    public function claim(): User
    {
        $user = DB::transaction(function () {
            $ready = User::query()->where('users.is_demo', true)->whereNull('users.demo_until')->oldest('id')->lockForUpdate()->first();
            $ready?->forceFill(['demo_until' => now()->addHours(self::HOURS)])->save();

            return $ready;
        });

        if ($user !== null) {
            return $user;
        }

        $user = $this->prepare();
        $user->forceFill(['demo_until' => now()->addHours(self::HOURS)])->save();

        return $user;
    }

    /**
     * Removes claimed accounts that ran out, and ready ones made before today
     * (their history would stop at an old date), then fills the pool back up.
     */
    public function sweep(): void
    {
        User::query()->where('users.is_demo', true)
            ->where(fn ($query) => $query->where('users.demo_until', '<', now())
                ->orWhere(fn ($ready) => $ready->whereNull('users.demo_until')->where('users.created_at', '<', today())))
            ->get()
            ->each(fn (User $user) => $this->removal->remove($user));

        $missing = (int) config('services.demo.pool') - User::query()->where('users.is_demo', true)->whereNull('users.demo_until')->count();

        for ($i = 0; $i < $missing; $i++) {
            $this->prepare();
        }
    }

    /**
     * A new account in the pool with its business, history, and photos; all
     * of it or nothing, so a failure never leaves a half account to claim.
     */
    public function prepare(): User
    {
        return DB::transaction(fn () => $this->fill());
    }

    private function fill(): User
    {
        $user = User::query()->forceCreate([
            'name' => 'Bu Rina',
            // .invalid never receives mail; nobody signs in with it, the visitor is signed in on claim.
            'email' => 'demo-'.Str::lower(Str::ulid()).'@lais.invalid',
            'password' => Str::password(32),
            'is_demo' => true,
            'email_verified_at' => now(),
        ]);

        // The seeders run as db:seed runs them: unguarded.
        Model::unguarded(function () use ($user) {
            $business = app(DemoBusinessSeeder::class)->open($user, 'warung-bu-rina-'.Str::lower(Str::random(6)));
            $history = app(DemoHistorySeeder::class);
            $history->days = (int) config('services.demo.days');
            $history->fill($business);
            app(DemoPhotoSeeder::class)->attach($business);
        });

        return $user;
    }
}
