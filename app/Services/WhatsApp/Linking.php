<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Linking an owner to the bot: the web shows a code, the owner sends it to
 * the bot from their own phone. Receiving it from that account proves the
 * account is theirs, like an OTP, and gives WhatsApp's linked id (…@lid) too.
 */
class Linking
{
    public const TTL_MINUTES = 10;

    /**
     * A fresh code for this owner; the one before stops working.
     *
     * @return array{code: string, expiresAt: string}
     */
    public function issue(User $user): array
    {
        $this->forget($user);
        $code = (string) random_int(100000, 999999);
        $expires = now()->addMinutes(self::TTL_MINUTES);

        Cache::put("wa-link:{$code}", $user->id, $expires);
        Cache::put("wa-link-user:{$user->id}", ['code' => $code, 'expiresAt' => $expires->toIso8601String()], $expires);

        return ['code' => $code, 'expiresAt' => $expires->toIso8601String()];
    }

    /**
     * @return array{code: string, expiresAt: string}|null
     */
    public function pending(User $user): ?array
    {
        $pending = Cache::get("wa-link-user:{$user->id}");

        return is_array($pending) && is_string($pending['code'] ?? null) && is_string($pending['expiresAt'] ?? null)
            ? ['code' => $pending['code'], 'expiresAt' => $pending['expiresAt']]
            : null;
    }

    /**
     * The sender of "LAIS 123456". Returns who was linked, or a reason it was not.
     */
    public function redeem(string $code, Address $from): User|string
    {
        // Six digits can be guessed one message at a time, so each sender gets a few tries.
        $tries = "wa-link-try:{$from->replyTo}";

        if (RateLimiter::tooManyAttempts($tries, 5)) {
            return 'Terlalu banyak percobaan. Tunggu 10 menit, lalu buat kode baru di Pengaturan > Bot.';
        }

        RateLimiter::hit($tries, 600);
        $userId = Cache::get("wa-link:{$code}");
        $user = is_int($userId) ? User::query()->find($userId) : null;

        if ($user === null) {
            return 'Kode itu tidak dikenal atau sudah kedaluwarsa. Buat kode baru di Pengaturan > Bot, lalu kirim lagi.';
        }

        if (User::query()->whereKeyNot($user->id)->where(self::sender($from))->exists()) {
            return 'Akun chat ini sudah terhubung ke pengguna Lais lain. Putuskan dulu dari sana di Pengaturan > Bot.';
        }

        DB::transaction(fn () => $user->forceFill([
            'whatsapp' => $from->phone,
            'whatsapp_lid' => $from->lid,
            'telegram_chat_id' => $from->telegram,
            'whatsapp_linked_at' => now(),
        ])->save());
        $this->forget($user);
        RateLimiter::clear($tries);

        return $user;
    }

    public function unlink(User $user): void
    {
        $user->forceFill(['whatsapp' => null, 'whatsapp_lid' => null, 'telegram_chat_id' => null, 'whatsapp_linked_at' => null])->save();
    }

    /**
     * The owner behind a sender, by number, linked id, or Telegram chat.
     */
    public function owner(Address $from): ?User
    {
        if ($from->phone === null && $from->lid === null && $from->telegram === null) {
            return null;
        }

        return User::query()->whereNotNull('whatsapp_linked_at')->where(self::sender($from))->first();
    }

    /**
     * @return Closure(Builder<User>): Builder<User>
     */
    private static function sender(Address $from): Closure
    {
        return fn (Builder $query) => $query
            ->when($from->phone, fn ($query, $phone) => $query->orWhere('whatsapp', $phone))
            ->when($from->lid, fn ($query, $lid) => $query->orWhere('whatsapp_lid', $lid))
            ->when($from->telegram, fn ($query, $chat) => $query->orWhere('telegram_chat_id', $chat));
    }

    private function forget(User $user): void
    {
        $pending = $this->pending($user);

        if ($pending !== null) {
            Cache::forget("wa-link:{$pending['code']}");
        }

        Cache::forget("wa-link-user:{$user->id}");
    }
}
