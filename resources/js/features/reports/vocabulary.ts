import type { CashStepKey } from '@/types/domain';

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
