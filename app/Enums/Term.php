<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * Accounting terms in plain words (PRD: istilah diberi penjelasan). One
 * source for the ⓘ hints on screen (lais:enums writes termDefinitions) and
 * for "apa itu …?" in Tanya AI and the bot, where the model only picks the term.
 * The values are the keys the screens already use.
 */
enum Term: string
{
    use EnumValues;

    case Sales = 'sales';
    case Cogs = 'cogs';
    case Debit = 'debit';
    case Credit = 'credit';
    case BookValue = 'bookValue';
    case GrossProfit = 'grossProfit';
    case Expenses = 'expenses';
    case StockDifference = 'stockDifference';
    case NetProfit = 'netProfit';
    case OpeningEquity = 'openingEquity';
    case Deposits = 'deposits';
    case Drawings = 'drawings';
    case Receivables = 'receivables';
    case Inventory = 'inventory';
    case Equipment = 'equipment';
    case AccumulatedDepreciation = 'accumulatedDepreciation';
    case Depreciation = 'depreciation';
    case Payables = 'payables';
    case CustomerDeposits = 'customerDeposits';
    case Capital = 'capital';
    case ProfitToDate = 'profitToDate';
    case CashChange = 'cashChange';
    case OpeningBalance = 'openingBalance';
    case ChartOfAccounts = 'chartOfAccounts';
    case Assets = 'assets';
    case Liabilities = 'liabilities';
    case Income = 'income';
    case Costs = 'costs';
    case Margin = 'margin';
    case BreakEven = 'breakEven';

    /** The name the owner asks about. */
    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Penjualan (omzet)',
            self::Cogs => 'HPP',
            self::Debit => 'Debit',
            self::Credit => 'Kredit',
            self::BookValue => 'Nilai buku',
            self::GrossProfit => 'Laba kotor',
            self::Expenses => 'Biaya operasional',
            self::StockDifference => 'Selisih stok',
            self::NetProfit => 'Laba bersih',
            self::OpeningEquity => 'Modal awal periode',
            self::Deposits => 'Setoran modal',
            self::Drawings => 'Prive',
            self::Receivables => 'Piutang',
            self::Inventory => 'Persediaan',
            self::Equipment => 'Peralatan',
            self::AccumulatedDepreciation => 'Akumulasi penyusutan',
            self::Depreciation => 'Penyusutan',
            self::Payables => 'Utang',
            self::CustomerDeposits => 'Uang muka pesanan',
            self::Capital => 'Modal pemilik',
            self::ProfitToDate => 'Laba ditahan',
            self::CashChange => 'Perubahan kas',
            self::OpeningBalance => 'Saldo awal',
            self::ChartOfAccounts => 'Daftar akun (COA)',
            self::Assets => 'Aset',
            self::Liabilities => 'Kewajiban',
            self::Income => 'Pendapatan',
            self::Costs => 'Beban',
            self::Margin => 'Margin',
            self::BreakEven => 'BEP (titik impas)',
        };
    }

    public function definition(): string
    {
        return match ($this) {
            self::Sales => 'Semua penjualan di periode ini, yang lunas maupun yang belum dibayar.',
            self::Cogs => 'HPP: modal bahan dan barang dari produk yang terjual.',
            self::Debit => 'Debit: sisi kiri pembukuan. Menambah aset dan biaya, mengurangi utang, modal, dan pendapatan.',
            self::Credit => 'Kredit: sisi kanan pembukuan. Menambah utang, modal, dan pendapatan, mengurangi aset dan biaya.',
            self::BookValue => 'Nilai buku: harga beli dikurangi penyusutan yang sudah dicatat.',
            self::GrossProfit => 'Penjualan dikurangi HPP, sebelum biaya operasional.',
            self::Expenses => 'Pengeluaran rutin yang habis dipakai, seperti listrik, gas, sewa, dan upah.',
            self::StockDifference => 'Stok yang rusak, hilang, atau beda dengan hitungan saat koreksi stok.',
            self::NetProfit => 'Yang tersisa setelah semua biaya. Kalau minus, usaha rugi.',
            self::OpeningEquity => 'Bagian pemilik di awal periode: semua yang dimiliki usaha dikurangi utangnya.',
            self::Deposits => 'Uang pribadi yang masuk ke usaha, termasuk belanja yang dibayar pakai uang pribadi dan stok awal produk baru.',
            self::Drawings => 'Uang usaha yang diambil untuk keperluan pribadi (prive). Tidak mengurangi laba, tapi mengurangi modal.',
            self::Receivables => 'Piutang: uang pelanggan yang belum dibayar.',
            self::Inventory => 'Nilai stok bahan dan barang, sebesar harga belinya.',
            self::Equipment => 'Alat yang dipakai lama, dicatat sebesar harga belinya.',
            self::AccumulatedDepreciation => 'Bagian harga peralatan yang sudah dibebankan sebagai biaya selama dipakai.',
            self::Depreciation => 'Harga peralatan yang dibagi rata selama umur pakainya. Ini biaya tanpa uang keluar.',
            self::Payables => 'Utang: belanja yang belum dibayar ke supplier.',
            self::CustomerDeposits => 'Uang muka pesanan yang sudah diterima tapi pesanannya belum selesai.',
            self::Capital => 'Modal yang pernah dimasukkan pemilik, termasuk saldo awal.',
            self::ProfitToDate => 'Laba dan rugi sejak pembukuan dibuka sampai tanggal ini.',
            self::CashChange => 'Selisih uang tunai dan non-tunai antara awal dan akhir periode.',
            self::OpeningBalance => 'Saldo awal: uang, stok, alat, utang, dan piutang yang sudah ada saat mulai pakai Lais.',
            self::ChartOfAccounts => 'Daftar akun (COA): daftar "laci" pembukuan. Setiap transaksi masuk ke laci yang sesuai, jadi laporan tersusun sendiri.',
            self::Assets => 'Aset: semua yang dimiliki usaha, seperti uang, stok, piutang, dan peralatan.',
            self::Liabilities => 'Kewajiban: yang masih harus dibayar usaha, seperti utang ke supplier dan uang muka pesanan.',
            self::Income => 'Pendapatan: uang yang didapat usaha, terutama dari penjualan.',
            self::Costs => 'Beban: biaya yang habis dipakai untuk menjalankan usaha, seperti sewa, listrik, dan upah.',
            self::Margin => 'Margin: bagian harga jual yang tersisa setelah dikurangi modal produknya. Harga Rp10.000 dengan modal Rp6.000 berarti margin 40%.',
            self::BreakEven => 'BEP (titik impas): penjualan minimal supaya semua biaya tertutup, belum untung dan belum rugi.',
        };
    }

    /** The answer to "apa itu …?": the definition, named when it does not name itself. */
    public function answer(): string
    {
        $definition = $this->definition();

        return str_starts_with($definition, $this->label().':')
            ? $definition
            : $this->label().': '.lcfirst($definition);
    }

    /**
     * Where the owner sees it in their own books.
     *
     * @return array{label: string, href: string}|null
     */
    public function link(): ?array
    {
        $page = fn (string $label, string $route, array $query = []) => ['label' => $label, 'href' => route($route, $query, absolute: false)];

        return match ($this) {
            self::Sales, self::Cogs, self::GrossProfit, self::Expenses, self::NetProfit, self::StockDifference, self::Income, self::Costs => $page('Buka Laba rugi', 'reports.profit-loss'),
            self::OpeningEquity, self::Deposits, self::Drawings, self::Capital, self::ProfitToDate => $page('Buka Perubahan modal', 'reports.equity'),
            self::Assets, self::Liabilities, self::CustomerDeposits => $page('Buka Posisi keuangan', 'reports.balance-sheet'),
            self::Receivables => $page('Buka Utang-piutang', 'debts.index'),
            self::Payables => $page('Buka Utang-piutang', 'debts.index', ['jenis' => 'utang']),
            self::Inventory => $page('Buka Produk & stok', 'items.index'),
            self::Equipment, self::BookValue, self::Depreciation, self::AccumulatedDepreciation => $page('Buka Aset tetap', 'fixed-assets.index'),
            self::CashChange => $page('Buka Buku kas', 'reports.cash-book'),
            self::ChartOfAccounts, self::Debit, self::Credit => $page('Buka Daftar akun', 'reports.chart'),
            self::Margin => $page('Buka laporan Produk', 'reports.products'),
            self::OpeningBalance, self::BreakEven => null,
        };
    }
}
