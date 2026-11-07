<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Depreciation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Posts depreciation for every month that has ended, and catches up on any
 * that were missed. Safe to run again.
 */
#[Signature('lais:penyusutan {--usaha= : Hanya untuk satu id usaha}')]
#[Description('Catat penyusutan peralatan untuk bulan yang sudah berakhir')]
class PostDepreciation extends Command
{
    public function handle(Depreciation $depreciation): int
    {
        $id = $this->option('usaha');
        $posted = $depreciation->run(business: $id === null ? null : Business::query()->findOrFail((int) $id));

        $this->info("Penyusutan dicatat untuk {$posted} bulan aset.");

        return self::SUCCESS;
    }
}
