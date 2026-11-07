import { formatDate, formatRupiah } from '@/lib/format';
import type { DebtTab } from '@/types/domain';

/** "Hari ini" for a bill from today, else "18 hari". */
export function ageLabel(days: number): string {
    return days === 0 ? 'Hari ini' : `${days} hari`;
}

/** Age of an open bill: under a week is fine, a month is late (UI-PAGES C11). */
export function ageTone(days: number): 'neutral' | 'warning' | 'danger' {
    return days > 30 ? 'danger' : days >= 7 ? 'warning' : 'neutral';
}

export const tabCopy: Record<
    DebtTab,
    { label: string; who: string; empty: string; pay: string }
> = {
    piutang: {
        label: 'Piutang',
        who: 'Pelanggan yang belum bayar',
        empty: 'Semua pelanggan sudah lunas. Penjualan yang belum dibayar muncul di sini.',
        pay: 'Terima bayaran',
    },
    utang: {
        label: 'Utang',
        who: 'Supplier yang belum dibayar',
        empty: 'Tidak ada utang ke supplier. Belanja yang belum dibayar muncul di sini.',
        pay: 'Bayar utang',
    },
};

/**
 * The reminder the owner sends from their own WhatsApp ("Ingatkan"):
 * polite, with the amount and since when, never a threat.
 */
export function reminderMessage(
    name: string,
    balance: number,
    since: string,
    businessName: string,
): string {
    return `Halo ${name}, semoga sehat selalu. Kami dari ${businessName} mau mengingatkan, masih ada tagihan ${formatRupiah(balance)} sejak ${formatDate(since, 'short')}. Kalau sudah dibayar, abaikan pesan ini ya. Terima kasih.`;
}
