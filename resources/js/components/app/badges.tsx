import {
    ArrowLeftRight,
    CircleCheck,
    Flag,
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
    Send,
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
    TransactionSource,
    TransactionType,
} from '@/types/domain';
import {
    itemKindLabels,
    orderStatusLabels,
    paymentMethodLabels,
    paymentStatusLabels,
    transactionSourceLabels,
    transactionTypeLabels,
} from '@/types/enums';

type Vocabulary<K extends string> = Record<
    K,
    { label: string; icon: LucideIcon }
>;

/** Labels come from app/Enums (types/enums.ts); this file only adds the icon. */
function withIcons<K extends string>(
    labels: Record<K, string>,
    icons: Record<K, LucideIcon>,
): Vocabulary<K> {
    return Object.fromEntries(
        (Object.keys(labels) as K[]).map((key) => [
            key,
            { label: labels[key], icon: icons[key] },
        ]),
    ) as Vocabulary<K>;
}

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'accent';

const toneClass: Record<Tone, string> = {
    neutral: 'bg-secondary text-secondary-foreground',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-destructive-soft text-destructive',
    accent: 'bg-accent text-accent-foreground',
};

export function LabelBadge({
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
export const transactionTypes = withIcons<TransactionType>(
    transactionTypeLabels,
    {
        penjualan: HandCoins,
        pembelian: ShoppingBasket,
        biaya: ReceiptText,
        utang_piutang: NotebookPen,
        modal: Wallet,
        pindah_dana: ArrowLeftRight,
        saldo_awal: Flag,
    },
);

/**
 * The types the record tabs and the Catat sheet offer. Saldo awal is recorded
 * once, from onboarding or Kas & rekening, not as an everyday record.
 */
export type EverydayType = Exclude<TransactionType, 'saldo_awal'>;

export const everydayTypes = (
    Object.keys(transactionTypes) as TransactionType[]
).filter((type): type is EverydayType => type !== 'saldo_awal');

export function CategoryBadge({ type }: { type: TransactionType }) {
    const { label, icon } = transactionTypes[type];

    return <LabelBadge icon={icon} label={label} />;
}

export const transactionSources = withIcons<TransactionSource>(
    transactionSourceLabels,
    { wa: MessageCircle, tg: Send, web: Monitor, toko: Store, ocr: ScanText },
);

/** Where a record came from. Plain text, no chip, so it stays quiet in lists. */
export function SourceLabel({ source }: { source: TransactionSource }) {
    const { label, icon: Icon } = transactionSources[source];

    return (
        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
            <Icon className="size-3.5" aria-hidden />
            {label}
        </span>
    );
}

export const orderStatuses: Record<
    OrderStatus,
    { icon: LucideIcon; tone: Tone }
> = {
    menunggu: { icon: Clock, tone: 'warning' },
    diproses: { icon: CircleDashed, tone: 'accent' },
    siap: { icon: PackageCheck, tone: 'accent' },
    selesai: { icon: CircleCheck, tone: 'success' },
    ditolak: { icon: CircleX, tone: 'danger' },
};

export function OrderStatusBadge({ status }: { status: OrderStatus }) {
    const { icon, tone } = orderStatuses[status];

    return (
        <LabelBadge icon={icon} label={orderStatusLabels[status]} tone={tone} />
    );
}

const paymentStatuses: Record<PaymentStatus, { icon: LucideIcon; tone: Tone }> =
    {
        belum: { icon: CircleX, tone: 'danger' },
        dp: { icon: CircleDashed, tone: 'warning' },
        lunas: { icon: CircleCheck, tone: 'success' },
    };

export function PaymentStatusBadge({ status }: { status: PaymentStatus }) {
    const { icon, tone } = paymentStatuses[status];

    return (
        <LabelBadge
            icon={icon}
            label={paymentStatusLabels[status]}
            tone={tone}
        />
    );
}

export const itemKinds = withIcons<ItemKind>(itemKindLabels, {
    produk: Package,
    bahan: Wheat,
    komposisi: CookingPot,
});

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

/** How the money moved, in the words of the transaction type: a purchase on credit is "Belum dibayar". */
export function methodLabel(
    type: TransactionType,
    method: PaymentMethod,
): string {
    return method === 'kredit' && type !== 'penjualan'
        ? 'Belum dibayar'
        : paymentMethodLabels[method];
}

/** A cancelled transaction stays in the list, struck through, with this badge. */
export function VoidBadge() {
    return <LabelBadge icon={Ban} label="Dibatalkan" tone="danger" />;
}
