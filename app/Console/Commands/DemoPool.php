<?php

namespace App\Console\Commands;

use App\Services\DemoAccounts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lais:demo')]
#[Description('Siapkan akun "Coba demo" dan hapus yang sudah habis waktunya')]
class DemoPool extends Command
{
    public function handle(DemoAccounts $demo): int
    {
        $demo->sweep();

        return self::SUCCESS;
    }
}
