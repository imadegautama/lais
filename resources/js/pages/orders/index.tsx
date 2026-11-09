import { Head, Link, usePoll } from '@inertiajs/react';
import {
    Bike,
    ChevronRight,
    ClipboardList,
    SearchX,
    Store,
} from 'lucide-react';
import { useMemo } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { PaymentStatusBadge } from '@/components/app/badges';
import { FilterBar, SearchFilter } from '@/components/app/filter-bar';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { ManualOrderSheet } from '@/features/orders/manual-order-sheet';
import {
    NextStepButtons,
    OrderPaymentDialog,
} from '@/features/orders/order-actions';
import { ShopBar } from '@/features/orders/shop-card';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatDate, formatNameList, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/orders';
import {
    OrderStatus,
    orderFulfillmentLabels,
    orderStatusLabels,
} from '@/types/enums';
import type {
    MoneyAccountOption,
    OrdersIndexProps,
    OwnerOrder,
} from '@/types/domain';

const emptyText: Record<OrderStatus, string> = {
    menunggu: 'Pesanan baru dari toko online muncul di sini.',
    diproses: 'Pesanan yang sudah diterima dan sedang disiapkan.',
    siap: 'Pesanan yang siap diambil atau sedang diantar.',
    selesai: 'Pesanan yang sudah selesai tercatat sebagai penjualan.',
    ditolak: 'Pesanan yang ditolak atau dibatalkan.',
};

/** Payments come in once accepted, Selesai included, until nothing is left. */
const payable = (order: OwnerOrder) =>
    order.status !== 'menunggu' &&
    order.status !== 'ditolak' &&
    order.paid < order.total;

/**
 * Pesanan (UI-PAGES C9): the shop's switches, then the orders of one status.
 * A table on wide screens, a list on phones; searched and paged on the
 * server, so history never drops off. It looks for news every 30 seconds.
 */
export default function OrdersIndex({
    shop,
    status,
    counts,
    orders,
    items,
    moneyAccounts,
}: OrdersIndexProps) {
    usePoll(30_000, { only: ['orders', 'counts', 'pendingOrders'] });
    const table = useTableQuery(orders, 'orders');
    const columns = useMemo(() => buildColumns(moneyAccounts), [moneyAccounts]);
    const searching = Boolean(table.search.value.trim());

    return (
        <>
            <Head title="Pesanan" />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Pesanan"
                    description="Terima, siapkan, dan catat pembayaran pesanan pelanggan."
                    actions={<ManualOrderSheet items={items} />}
                />

                <ShopBar shop={shop} moneyAccounts={moneyAccounts} />

                <nav
                    aria-label="Status pesanan"
                    className="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
                >
                    <ul className="flex w-max gap-1.5">
                        {Object.values(OrderStatus).map((option) => (
                            <li key={option}>
                                <Link
                                    href={index({ query: { status: option } })}
                                    preserveScroll
                                    aria-current={
                                        option === status ? 'page' : undefined
                                    }
                                    className={cn(
                                        'flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium whitespace-nowrap focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:h-11',
                                        option === status
                                            ? 'border-primary bg-brand-soft text-primary-text'
                                            : 'bg-card text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {orderStatusLabels[option]}
                                    <span
                                        className={cn(
                                            'num rounded-md px-1.5 text-xs',
                                            option === 'menunggu' &&
                                                counts[option] > 0
                                                ? 'bg-destructive text-white'
                                                : 'bg-secondary text-secondary-foreground',
                                        )}
                                    >
                                        {counts[option]}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                <SectionCard
                    title={orderStatusLabels[status]}
                    description={`${orders.meta.total} pesanan`}
                    icon={ClipboardList}
                >
                    <div className="flex flex-col gap-4">
                        <FilterBar>
                            <SearchFilter
                                placeholder="Cari kode atau nama"
                                {...table.search}
                            />
                        </FilterBar>
                        <AppDataTable
                            caption={`Pesanan ${orderStatusLabels[status].toLowerCase()}`}
                            columns={columns}
                            data={orders.data}
                            getRowId={(order) => order.code}
                            server={table}
                            loadingLabel="Memuat pesanan"
                            layout={{
                                masuk: { className: 'min-w-44' },
                                item: {
                                    className: 'min-w-48 whitespace-normal',
                                },
                                waktu: { className: 'min-w-36' },
                                total: { align: 'right', className: 'w-28' },
                                bayar: { className: 'w-28' },
                                aksi: {
                                    align: 'right',
                                    className: 'w-px whitespace-nowrap',
                                },
                            }}
                            renderMobileItem={(order) => (
                                <OrderListItem
                                    order={order}
                                    moneyAccounts={moneyAccounts}
                                />
                            )}
                            empty={
                                searching ? (
                                    <EmptyState
                                        icon={SearchX}
                                        title="Tidak ada yang cocok"
                                        description="Tidak ada pesanan dengan kode, nama, atau nomor itu di tab ini."
                                        action={
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={table.reset}
                                            >
                                                Hapus pencarian
                                            </Button>
                                        }
                                    />
                                ) : (
                                    <EmptyState
                                        icon={ClipboardList}
                                        title={`Tidak ada pesanan ${orderStatusLabels[status].toLowerCase()}`}
                                        description={emptyText[status]}
                                    />
                                )
                            }
                        />
                    </div>
                </SectionCard>
            </div>
        </>
    );
}

function Fulfillment({ order }: { order: OwnerOrder }) {
    const Icon = order.fulfillment === 'antar' ? Bike : Store;

    return (
        <span className="inline-flex items-center gap-1 text-muted-foreground">
            <Icon className="size-4 shrink-0" aria-hidden />
            {orderFulfillmentLabels[order.fulfillment]}
            {order.wantedAt && `, ${formatDate(order.wantedAt, 'datetime')}`}
        </span>
    );
}

function Customer({ order }: { order: OwnerOrder }) {
    return (
        <div className="flex min-w-0 flex-col gap-0.5">
            <Link
                href={show(order.code)}
                className="rounded-sm font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                {order.code} · {order.customerName}
            </Link>
            <span className="text-xs text-muted-foreground">
                Masuk {formatDate(order.createdAt, 'datetime')}
                {order.source === 'web' && ' · dicatat pemilik'}
            </span>
        </div>
    );
}

function itemSummary(order: OwnerOrder): string {
    const names = order.lines.map((line) => `${line.qty} ${line.name}`);

    return formatNameList(names.slice(0, 3), names.length);
}

function Actions({
    order,
    moneyAccounts,
}: {
    order: OwnerOrder;
    moneyAccounts: MoneyAccountOption[];
}) {
    return (
        <>
            <NextStepButtons order={order} size="sm" />
            {payable(order) && (
                <OrderPaymentDialog
                    order={order}
                    moneyAccounts={moneyAccounts}
                    size="sm"
                />
            )}
        </>
    );
}

// Columns follow the owner's job here: who ordered what, when it is wanted,
// whether it is paid, and the next step. Ids are the server sort keys.
function buildColumns(
    moneyAccounts: MoneyAccountOption[],
): Array<DataTableColumn<OwnerOrder>> {
    return [
        {
            id: 'masuk',
            accessorFn: (order) => new Date(order.createdAt),
            header: 'Pesanan',
            sortFn: 'datetime',
            cell: ({ row }) => <Customer order={row.original} />,
        },
        {
            id: 'item',
            header: 'Item',
            enableSorting: false,
            cell: ({ row }) => itemSummary(row.original),
        },
        {
            id: 'waktu',
            accessorFn: (order) => order.wantedAt ?? '',
            header: 'Ambil / antar',
            cell: ({ row }) => <Fulfillment order={row.original} />,
        },
        {
            id: 'total',
            accessorKey: 'total',
            header: 'Total',
            sortFn: 'basic',
            cell: ({ row }) => (
                <span className="num font-medium">
                    {formatRupiah(row.original.total)}
                </span>
            ),
        },
        {
            id: 'bayar',
            header: 'Bayar',
            enableSorting: false,
            cell: ({ row }) => (
                <PaymentStatusBadge status={row.original.paymentStatus} />
            ),
        },
        {
            id: 'aksi',
            header: 'Aksi',
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex items-center justify-end gap-1.5">
                    <Actions
                        order={row.original}
                        moneyAccounts={moneyAccounts}
                    />
                    <Button variant="ghost" size="icon" asChild>
                        <Link
                            href={show(row.original.code)}
                            aria-label={`Buka pesanan ${row.original.code}`}
                        >
                            <ChevronRight aria-hidden />
                        </Link>
                    </Button>
                </div>
            ),
        },
    ];
}

/** One order on a phone: the same facts as a row, stacked, with its buttons. */
function OrderListItem({
    order,
    moneyAccounts,
}: {
    order: OwnerOrder;
    moneyAccounts: MoneyAccountOption[];
}) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-start justify-between gap-3">
                <Customer order={order} />
                <PaymentStatusBadge status={order.paymentStatus} />
            </div>
            <p className="text-sm">{itemSummary(order)}</p>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                <span className="num font-semibold">
                    {formatRupiah(order.total)}
                </span>
                <Fulfillment order={order} />
            </div>
            <div className="flex flex-wrap items-center gap-2 pt-1">
                <Actions order={order} moneyAccounts={moneyAccounts} />
                <Button variant="ghost" size="sm" className="ml-auto" asChild>
                    <Link href={show(order.code)}>
                        Detail
                        <ChevronRight aria-hidden />
                    </Link>
                </Button>
            </div>
        </div>
    );
}
