import type { CashStepKey } from '@/types/domain';

/** Plain words for the accounting terms on the reports (PRD: istilah diberi penjelasan). */
export const terms = {
    sales: 'Semua penjualan di periode ini, yang lunas maupun yang belum dibayar.',
    cogs: 'HPP: modal bahan dan barang dari produk yang terjual.',
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
    equipment:
        'Alat yang dipakai lama, dicatat sebesar harga beli tanpa penyusutan.',
    payables: 'Utang: belanja yang belum dibayar ke supplier.',
    customerDeposits:
        'Uang muka pesanan yang sudah diterima tapi pesanannya belum selesai.',
    capital: 'Modal yang pernah dimasukkan pemilik, termasuk saldo awal.',
    profitToDate: 'Laba dan rugi sejak pembukuan dibuka sampai tanggal ini.',
    cashChange:
        'Selisih uang tunai dan non-tunai antara awal dan akhir periode.',
} as const;

export type Term = keyof typeof terms;

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
