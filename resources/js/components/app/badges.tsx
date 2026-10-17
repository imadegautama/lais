import {
    CircleCheck,
    CircleDashed,
    CircleX,
    Clock,
    HandCoins,
    Monitor,
    MessageCircle,
    NotebookPen,
    PackageCheck,
    ReceiptText,
    ScanText,
    ShoppingBasket,
    Store,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type {
    OrderStatus,
    PaymentStatus,
    TransactionSource,
    TransactionType,
} from '@/types/domain';

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'accent';

const toneClass: Record<Tone, string> = {
    neutral: 'bg-secondary text-secondary-foreground',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-destructive-soft text-destructive',
    accent: 'bg-accent text-accent-foreground',
};

function LabelBadge({
    icon: Icon,
    label,
    tone = 'neutral',
    className,
}: {
    icon: LucideIcon;
    label: string;
    tone?: Tone;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex w-fit shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium whitespace-nowrap',
                toneClass[tone],
                className,
            )}
        >
            <Icon className="size-3.5" aria-hidden />
            {label}
        </span>
    );
}

// Transaction categories are told apart by icon and label, not by five
// different colors (DESIGN.md, "Palet dibatasi").
export const transactionTypes: Record<
    TransactionType,
    { label: string; icon: LucideIcon }
> = {
    penjualan: { label: 'Penjualan', icon: HandCoins },
    pembelian: { label: 'Belanja', icon: ShoppingBasket },
    biaya: { label: 'Biaya', icon: ReceiptText },
    utang_piutang: { label: 'Utang-piutang', icon: NotebookPen },
    modal: { label: 'Modal', icon: Wallet },
};

export function CategoryBadge({ type }: { type: TransactionType }) {
    const { label, icon } = transactionTypes[type];

    return <LabelBadge icon={icon} label={label} />;
}

const sources: Record<TransactionSource, { label: string; icon: LucideIcon }> =
    {
        wa: { label: 'WhatsApp', icon: MessageCircle },
        web: { label: 'Web', icon: Monitor },
        toko: { label: 'Halaman toko', icon: Store },
        ocr: { label: 'Foto nota', icon: ScanText },
    };

/** Where a record came from. Plain text, no chip, so it stays quiet in lists. */
export function SourceLabel({ source }: { source: TransactionSource }) {
    const { label, icon: Icon } = sources[source];

    return (
        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
            <Icon className="size-3.5" aria-hidden />
            {label}
        </span>
    );
}

const orderStatuses: Record<
    OrderStatus,
    { label: string; icon: LucideIcon; tone: Tone }
> = {
    menunggu: { label: 'Menunggu konfirmasi', icon: Clock, tone: 'warning' },
    diproses: { label: 'Diproses', icon: CircleDashed, tone: 'accent' },
    siap: { label: 'Siap / diantar', icon: PackageCheck, tone: 'accent' },
    selesai: { label: 'Selesai', icon: CircleCheck, tone: 'success' },
    ditolak: { label: 'Ditolak', icon: CircleX, tone: 'danger' },
};

export function OrderStatusBadge({ status }: { status: OrderStatus }) {
    const { label, icon, tone } = orderStatuses[status];

    return <LabelBadge icon={icon} label={label} tone={tone} />;
}

const paymentStatuses: Record<
    PaymentStatus,
    { label: string; icon: LucideIcon; tone: Tone }
> = {
    belum: { label: 'Belum bayar', icon: CircleX, tone: 'danger' },
    dp: { label: 'DP', icon: CircleDashed, tone: 'warning' },
    lunas: { label: 'Lunas', icon: CircleCheck, tone: 'success' },
};

export function PaymentStatusBadge({ status }: { status: PaymentStatus }) {
    const { label, icon, tone } = paymentStatuses[status];

    return <LabelBadge icon={icon} label={label} tone={tone} />;
}
