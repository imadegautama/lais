<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * What happened in an audit-log entry. created/updated/deleted/restored are
 * the names spatie/laravel-activitylog gives model events; the rest are
 * logged explicitly.
 */
enum ActivityEvent: string
{
    use EnumValues;

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';
    case Cancelled = 'cancelled';
    case Replaced = 'replaced';
    case RecipeUpdated = 'recipe_updated';
    case StockAdjusted = 'stock_adjusted';
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case Lockout = 'lockout';
    case PasswordChanged = 'password_changed';
    case PasswordReset = 'password_reset';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';

    /**
     * The verb that opens the entry's sentence: "Mengubah produk Nasi Goreng".
     */
    public function verb(): string
    {
        return match ($this) {
            self::Created => 'Menambah',
            self::Updated, self::Replaced => 'Mengubah',
            self::Deleted => 'Mengarsipkan',
            self::Restored => 'Memulihkan',
            self::Cancelled => 'Membatalkan',
            self::RecipeUpdated => 'Mengubah resep',
            self::StockAdjusted => 'Mengoreksi stok',
            self::Login => 'Masuk ke akun',
            self::Logout => 'Keluar dari akun',
            self::LoginFailed => 'Gagal masuk ke akun',
            self::Lockout => 'Masuk diblokir sementara karena terlalu banyak percobaan',
            self::PasswordChanged => 'Mengganti password',
            self::PasswordReset => 'Mengatur ulang password',
            self::TwoFactorEnabled => 'Mengaktifkan verifikasi dua langkah',
            self::TwoFactorDisabled => 'Mematikan verifikasi dua langkah',
        };
    }
}
