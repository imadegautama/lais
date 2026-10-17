import type { TransactionType } from '@/types/enums';

/** Words of the record form per type. Labels themselves come from types/enums.ts. */
export const recordCopy: Record<
    TransactionType,
    {
        save: string;
        /** Heading of the sub-kind choice; a sale has none. */
        kindTitle: string;
        notePlaceholder: string;
    }
> = {
    penjualan: {
        save: 'Simpan penjualan',
        kindTitle: '',
        notePlaceholder: 'Pesanan meja 3',
    },
    pembelian: {
        save: 'Simpan belanja',
        kindTitle: 'Yang dibeli',
        notePlaceholder: 'Belanja di pasar',
    },
    biaya: {
        save: 'Simpan biaya',
        kindTitle: 'Jenis biaya',
        notePlaceholder: 'Token bulan Oktober',
    },
    utang_piutang: {
        save: 'Simpan pembayaran',
        kindTitle: 'Jenis pembayaran',
        notePlaceholder: 'Cicilan pertama',
    },
    modal: {
        save: 'Simpan',
        kindTitle: 'Jenis catatan modal',
        notePlaceholder: 'Buat bayar sekolah',
    },
};
