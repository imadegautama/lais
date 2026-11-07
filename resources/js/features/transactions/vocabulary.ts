import type { TransactionType } from '@/types/enums';

/** Words of the record form per type. Labels themselves come from types/enums.ts. */
export const recordCopy: Record<
    TransactionType,
    {
        save: string;
        /** Heading of the sub-kind choice; a sale has none. */
        kindTitle: string;
        /** Heading of the card that says where the money comes from or goes. */
        paymentTitle: string;
        notePlaceholder: string;
    }
> = {
    penjualan: {
        save: 'Simpan penjualan',
        kindTitle: '',
        paymentTitle: 'Pembayaran',
        notePlaceholder: 'Pesanan meja 3',
    },
    pembelian: {
        save: 'Simpan belanja',
        kindTitle: 'Yang dibeli',
        paymentTitle: 'Pembayaran',
        notePlaceholder: 'Belanja di pasar',
    },
    biaya: {
        save: 'Simpan biaya',
        kindTitle: 'Jenis biaya',
        paymentTitle: 'Pembayaran',
        notePlaceholder: 'Token bulan Oktober',
    },
    pemasukan_lain: {
        save: 'Simpan pemasukan',
        kindTitle: '',
        paymentTitle: 'Uang masuk ke',
        notePlaceholder: 'Hibah dari dinas koperasi',
    },
    utang_piutang: {
        save: 'Simpan pembayaran',
        kindTitle: 'Jenis pembayaran',
        paymentTitle: 'Pembayaran',
        notePlaceholder: 'Cicilan pertama',
    },
    modal: {
        save: 'Simpan',
        kindTitle: 'Jenis catatan modal',
        paymentTitle: 'Pembayaran',
        notePlaceholder: 'Buat bayar sekolah',
    },
    pindah_dana: {
        save: 'Simpan pindah dana',
        kindTitle: '',
        paymentTitle: 'Dari dan ke',
        notePlaceholder: 'Setoran harian',
    },
    saldo_awal: {
        save: 'Simpan saldo awal',
        kindTitle: 'Saldo awal untuk',
        paymentTitle: 'Keterangan',
        notePlaceholder: 'Uang di laci saat mulai',
    },
};
