import { formatDate } from '@/lib/format';
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

/** The customer's opening line to the seller about an order ("Hubungi penjual"). */
export function askSellerMessage(order: PublicOrder | null): string {
    return order
        ? `Halo, saya ${order.customerName} mau tanya pesanan ${order.code} (${formatDate(order.createdAt, 'short')}).`
        : 'Halo, saya mau tanya soal pesanan.';
}
