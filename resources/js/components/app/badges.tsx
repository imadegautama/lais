import {
    CircleCheck,
    CircleDashed,
    Ban,
    CircleX,
    Clock,
    CookingPot,
    HandCoins,
    Monitor,
    MessageCircle,
    NotebookPen,
    Package,
    PackageCheck,
    ReceiptText,
    ScanText,
    Wheat,
    ShoppingBasket,
    Store,
    TriangleAlert,
    Wallet,
    type LucideIcon,
} from 'lucide-react';
import { formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    ItemKind,
    OrderStatus,
    PaymentMethod,
    PaymentStatus,
    TransactionKind,
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

export const itemKinds: Record<
    ItemKind,
    { label: string; icon: LucideIcon; hint: string }
> = {
    produk: {
        label: 'Produk biasa',
        icon: Package,
        hint: 'Dibeli atau dibuat lalu dijual apa adanya: air mineral, sabun jadi.',
    },
    bahan: {
        label: 'Bahan baku',
        icon: Wheat,
        hint: 'Tidak dijual langsung, dipakai di resep: beras, telur, minyak.',
    },
    komposisi: {
        label: 'Produk resep',
        icon: CookingPot,
        hint: 'Dibuat dari bahan saat dijual: nasi goreng, es teh, kue.',
    },
};

/** Item kinds share one neutral tone; icon and word tell them apart. */
export function ItemKindBadge({ kind }: { kind: ItemKind }) {
    const { label, icon } = itemKinds[kind];

    return <LabelBadge icon={icon} label={label} />;
}

/** Only a problem gets a badge; a healthy count stays plain text. */
export function StockStatusBadge({
    lowStock,
    negativeStock,
}: {
    lowStock: boolean;
    negativeStock: boolean;
}) {
    if (negativeStock) {
        return <LabelBadge icon={CircleX} label="Minus" tone="danger" />;
    }

    if (lowStock) {
        return (
            <LabelBadge icon={TriangleAlert} label="Menipis" tone="warning" />
        );
    }

    return null;
}

/** Below this the margin is flagged (UI-PAGES C5, C6). */
export const THIN_MARGIN_PERCENT = 15;

/** Margin as a percentage of price, toned per UI-PAGES C5. */
export function MarginLabel({ percent }: { percent: number }) {
    const [label, icon, tone]: [string, LucideIcon, Tone] =
        percent < 0
            ? ['Rugi', CircleX, 'danger']
            : percent < THIN_MARGIN_PERCENT
              ? ['Tipis', TriangleAlert, 'danger']
              : percent < 30
                ? ['Cukup', CircleDashed, 'warning']
                : ['Sehat', CircleCheck, 'success'];

    return (
        <LabelBadge
            icon={icon}
            label={`${label} ${formatPercent(Math.abs(percent))}`}
            tone={tone}
        />
    );
}

/** How the money moved, in the words of the transaction type. */
export function methodLabel(
    type: TransactionType,
    method: PaymentMethod,
): string {
    if (method === 'kredit') {
        return type === 'penjualan' ? 'Belum lunas' : 'Belum dibayar';
    }

    return { tunai: 'Tunai', non_tunai: 'Non-tunai', pribadi: 'Uang pribadi' }[
        method
    ];
}

export const transactionKinds: Record<TransactionKind, string> = {
    barang: 'Bahan / barang dagang',
    peralatan: 'Peralatan',
    listrik_air: 'Listrik & air',
    gas_bbm: 'Gas & BBM',
    sewa: 'Sewa',
    upah: 'Upah karyawan',
    transport: 'Transport & ongkir',
    pemasaran: 'Pemasaran',
    lain: 'Lain-lain',
    terima_piutang: 'Terima bayaran dari pelanggan',
    bayar_utang: 'Bayar utang ke supplier',
    setor: 'Setor modal',
    prive: 'Ambil untuk pribadi',
};

/** A cancelled transaction stays in the list, struck through, with this badge. */
export function VoidBadge() {
    return <LabelBadge icon={Ban} label="Dibatalkan" tone="danger" />;
}
