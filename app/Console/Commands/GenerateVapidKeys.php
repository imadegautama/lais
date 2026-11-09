<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Makes the key pair web push signs with and writes it into .env. The
 * private key is never printed; on a server, set both in the env there.
 */
#[Signature('lais:vapid {--force : Ganti kunci yang sudah ada}')]
#[Description('Buat kunci VAPID untuk notifikasi HP (web push) di .env')]
class GenerateVapidKeys extends Command
{
    public function handle(): int
    {
        $path = base_path('.env');
        $env = is_file($path) ? (string) file_get_contents($path) : '';

        if (preg_match('/^VAPID_PRIVATE_KEY=.+$/m', $env) === 1 && ! $this->option('force')) {
            $this->warn('Kunci VAPID sudah ada. Pakai --force untuk menggantinya (perangkat yang sudah aktif harus mengaktifkan ulang).');

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();

        foreach (['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']] as $name => $value) {
            $env = preg_match("/^{$name}=.*$/m", $env) === 1
                ? (string) preg_replace("/^{$name}=.*$/m", "{$name}={$value}", $env)
                : rtrim($env, "\n")."\n{$name}={$value}\n";
        }

        file_put_contents($path, $env);
        $this->info('Kunci VAPID ditulis ke .env. Jalankan php artisan config:clear.');

        return self::SUCCESS;
    }
}
