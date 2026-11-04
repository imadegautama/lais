import { formatDate, formatRupiah } from '@/lib/format';
import type { OrderStatus, PublicOrder } from '@/types/domain';

/** The steps a customer sees on the nota; Ditolak replaces them with a banner. */
export const orderSteps: ReadonlyArray<{
    status: OrderStatus;
    label: string;
    at: (order: PublicOrder) => string | null;
}> = [
    {
        status: 'menunggu',
        label: 'Menunggu konfirmasi',
        at: (order) => order.createdAt,
    },
    { status: 'diproses', label: 'Diproses', at: (order) => order.acceptedAt },
    { status: 'siap', label: 'Siap / diantar', at: (order) => order.readyAt },
    { status: 'selesai', label: 'Selesai', at: (order) => order.completedAt },
];

/** Index of the step the order is on; Siap can be skipped on the way to Selesai. */
export function stepIndex(status: OrderStatus): number {
    return orderSteps.findIndex((step) => step.status === status);
}

/** The owner's next move on an order, as its button label. */
export const nextAction: Partial<Record<OrderStatus, string>> = {
    menunggu: 'Terima',
    diproses: 'Tandai siap',
    siap: 'Selesai',
};

/**
 * The message the owner sends from their own WhatsApp ("Kirim kabar",
 * UI-PAGES C10): where the order stands, what is left to pay, and the nota.
 */
export function statusMessage(
    order: PublicOrder,
    shopName: string,
    notaUrl: string,
): string {
    const rest = order.total - order.paid;
    const owed =
        rest > 0 ? ` Sisa yang perlu dibayar ${formatRupiah(rest)}.` : '';
    const nota = `\n\nStatus dan nota: ${notaUrl}`;
    const greeting = `Halo ${order.customerName},`;

    switch (order.status) {
        case 'menunggu':
            return `${greeting} pesanan ${order.code} di ${shopName} sudah kami terima dan sedang dicek.${nota}`;
        case 'diproses':
            return `${greeting} pesanan ${order.code} di ${shopName} sedang kami siapkan. Totalnya ${formatRupiah(order.total)}.${owed}${nota}`;
        case 'siap':
            return order.fulfillment === 'antar'
                ? `${greeting} pesanan ${order.code} sedang diantar ke alamat Anda.${owed}${nota}`
                : `${greeting} pesanan ${order.code} sudah siap diambil di ${shopName}.${owed}${nota}`;
        case 'selesai':
            return `Terima kasih, ${order.customerName}! Pesanan ${order.code} sudah selesai.${rest > 0 ? owed : ' Pembayarannya sudah lunas.'}${nota}`;
        case 'ditolak':
            return `Mohon maaf, ${order.customerName}, pesanan ${order.code} di ${shopName} tidak bisa kami proses${order.rejectReason ? ` (${order.rejectReason})` : ''}.${order.paid > 0 ? ` Uang muka ${formatRupiah(order.paid)} kami kembalikan.` : ''}${nota}`;
    }
}

/** The customer's opening line to the seller about an order ("Hubungi penjual"). */
export function askSellerMessage(order: PublicOrder | null): string {
    return order
        ? `Halo, saya ${order.customerName} mau tanya pesanan ${order.code} (${formatDate(order.createdAt, 'short')}).`
        : 'Halo, saya mau tanya soal pesanan.';
}
