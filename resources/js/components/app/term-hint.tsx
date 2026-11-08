import { Info } from 'lucide-react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

/**
 * Plain words for accounting terms (PRD: istilah diberi penjelasan), shared by
 * the reports and the setup so every screen explains a term the same way.
 */
export const terms = {
    sales: 'Semua penjualan di periode ini, yang lunas maupun yang belum dibayar.',
    cogs: 'HPP: modal bahan dan barang dari produk yang terjual.',
    debit: 'Debit: sisi kiri pembukuan. Menambah aset dan biaya, mengurangi utang, modal, dan pendapatan.',
    credit: 'Kredit: sisi kanan pembukuan. Menambah utang, modal, dan pendapatan, mengurangi aset dan biaya.',
    bookValue:
        'Nilai buku: harga beli dikurangi penyusutan yang sudah dicatat.',
    grossProfit: 'Penjualan dikurangi HPP, sebelum biaya operasional.',
    expenses:
        'Pengeluaran rutin yang habis dipakai, seperti listrik, gas, sewa, dan upah.',
    stockDifference:
        'Stok yang rusak, hilang, atau beda dengan hitungan saat koreksi stok.',
    netProfit: 'Yang tersisa setelah semua biaya. Kalau minus, usaha rugi.',
    openingEquity:
        'Bagian pemilik di awal periode: semua yang dimiliki usaha dikurangi utangnya.',
    deposits:
        'Uang pribadi yang masuk ke usaha, termasuk belanja yang dibayar pakai uang pribadi dan stok awal produk baru.',
    drawings:
        'Uang usaha yang diambil untuk keperluan pribadi (prive). Tidak mengurangi laba, tapi mengurangi modal.',
    receivables: 'Piutang: uang pelanggan yang belum dibayar.',
    inventory: 'Nilai stok bahan dan barang, sebesar harga belinya.',
    equipment: 'Alat yang dipakai lama, dicatat sebesar harga belinya.',
    accumulatedDepreciation:
        'Bagian harga peralatan yang sudah dibebankan sebagai biaya selama dipakai.',
    depreciation:
        'Harga peralatan yang dibagi rata selama umur pakainya. Ini biaya tanpa uang keluar.',
    payables: 'Utang: belanja yang belum dibayar ke supplier.',
    customerDeposits:
        'Uang muka pesanan yang sudah diterima tapi pesanannya belum selesai.',
    capital: 'Modal yang pernah dimasukkan pemilik, termasuk saldo awal.',
    profitToDate: 'Laba dan rugi sejak pembukuan dibuka sampai tanggal ini.',
    cashChange:
        'Selisih uang tunai dan non-tunai antara awal dan akhir periode.',
    // Used by the setup (onboarding) to explain where each number goes.
    openingBalance:
        'Saldo awal: uang, stok, alat, utang, dan piutang yang sudah ada saat mulai pakai Lais.',
    chartOfAccounts:
        'Daftar akun (COA): daftar "laci" pembukuan. Setiap transaksi masuk ke laci yang sesuai, jadi laporan tersusun sendiri.',
    assets: 'Aset: semua yang dimiliki usaha, seperti uang, stok, piutang, dan peralatan.',
    liabilities:
        'Kewajiban: yang masih harus dibayar usaha, seperti utang ke supplier dan uang muka pesanan.',
    income: 'Pendapatan: uang yang didapat usaha, terutama dari penjualan.',
    costs: 'Beban: biaya yang habis dipakai untuk menjalankan usaha, seperti sewa, listrik, dan upah.',
} as const;

export type Term = keyof typeof terms;

/** ⓘ beside a term: opens on tap, click, or keyboard, never on hover alone. */
export function TermHint({ term, label }: { term: Term; label: string }) {
    // "HPP" stays an acronym; "Laba kotor" reads as "laba kotor".
    const spoken = label === label.toUpperCase() ? label : label.toLowerCase();

    return (
        <Popover>
            <PopoverTrigger
                className="inline-flex size-6 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none print:hidden pointer-coarse:size-11"
                aria-label={`Apa itu ${spoken}?`}
            >
                <Info className="size-4" aria-hidden />
            </PopoverTrigger>
            <PopoverContent className="w-64 text-sm print:hidden" side="top">
                {terms[term]}
            </PopoverContent>
        </Popover>
    );
}
