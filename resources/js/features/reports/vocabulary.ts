import type { CashStepKey } from '@/types/domain';

/** Plain words for the accounting terms on the reports (PRD: istilah diberi penjelasan). */
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
} as const;

export type Term = keyof typeof terms;

/** How Lais records each line, for Catatan atas laporan keuangan (SAK EMKM par. 6.2). */
export const accountingPolicies = [
    {
        title: 'Kas',
        body: 'Uang tunai dan saldo rekening non-tunai, dirinci per rekening.',
    },
    {
        title: 'Piutang usaha',
        body: 'Sebesar tagihan penjualan yang belum dibayar pelanggan.',
    },
    {
        title: 'Persediaan',
        body: 'Sebesar biaya perolehan dengan rumus rata-rata tertimbang, dan menjadi HPP saat barang dijual. Ongkos kirim pembelian yang dicatat sebagai biaya tidak masuk ke nilai persediaan.',
    },
    {
        title: 'Peralatan',
        body: 'Sebesar harga beli dikurangi akumulasi penyusutan. Disusutkan dengan metode garis lurus tanpa nilai sisa, per bulan penuh mulai bulan perolehan, sesuai umur pakai yang ditetapkan pemilik.',
    },
    {
        title: 'Pendapatan',
        body: 'Diakui sebesar nilai penuh saat barang diberikan kepada pelanggan, baik sudah dibayar maupun belum.',
    },
    {
        title: 'Uang muka pesanan',
        body: 'Dicatat sebagai kewajiban sampai pesanannya selesai, lalu menjadi bagian dari penjualan.',
    },
    {
        title: 'Biaya',
        body: 'Diakui saat terjadi, termasuk yang belum dibayar.',
    },
    {
        title: 'Pengambilan pribadi',
        body: 'Uang usaha yang diambil pemilik mengurangi modal, bukan biaya.',
    },
    {
        title: 'Pajak penghasilan',
        body: 'Mengikuti peraturan perpajakan yang berlaku. Lais belum menghitung beban pajak secara otomatis.',
    },
] as const;

/** Laba vs kas: what each step did to cash, by its direction. */
export function cashStepLabel(key: CashStepKey, amount: number): string {
    const up = amount > 0;
    const labels: Record<CashStepKey, [string, string]> = {
        inventory: ['Stok berkurang, jadi uang', 'Uang jadi stok'],
        receivables: [
            'Pelanggan melunasi piutang',
            'Penjualan yang belum dibayar',
        ],
        equipment: ['Peralatan berkurang', 'Uang dipakai beli peralatan'],
        depreciation: [
            'Penyusutan, biaya tanpa uang keluar',
            'Penyusutan dibatalkan',
        ],
        payables: ['Belanja yang belum dibayar', 'Bayar utang ke supplier'],
        customerDeposits: [
            'Uang muka pesanan masuk',
            'Uang muka pesanan terpakai',
        ],
        deposits: ['Setoran modal', 'Modal ditarik'],
        drawings: ['Prive dikembalikan', 'Diambil untuk pribadi'],
    };

    return labels[key][up ? 0 : 1];
}
