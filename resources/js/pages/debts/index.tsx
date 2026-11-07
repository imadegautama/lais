import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { ChevronRight, Clock, HandCoins, Users } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { MetricTile } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { PaymentDialog } from '@/components/app/payment-dialog';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { RemindDialog } from '@/features/debts/remind-dialog';
import { ageLabel, ageTone, tabCopy } from '@/features/debts/vocabulary';
import { formatDate, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, pay, show } from '@/routes/debts';
import { create as createTransaction } from '@/routes/transactions';
import type { DebtRow, DebtsIndexProps, DebtTab } from '@/types/domain';

const tabs: DebtTab[] = ['piutang', 'utang'];

/**
 * Utang-piutang (UI-PAGES C11): who still owes the business, whom it owes,
 * and since when. Payments are recorded right here.
 */
export default function DebtsIndex({
    tab,
    totals,
    rows,
    moneyAccounts,
    businessName,
}: DebtsIndexProps) {
    const total = totals[tab];
    const copy = tabCopy[tab];
    const columns = useMemo(
        () => buildColumns({ tab, moneyAccounts, businessName }),
        [tab, moneyAccounts, businessName],
    );

    return (
        <>
            <Head title="Utang-piutang" />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Utang-piutang"
                    description="Siapa yang belum bayar, dan ke siapa usaha masih berutang."
                />

                <nav aria-label="Jenis tagihan">
                    <ul className="flex gap-1.5">
                        {tabs.map((option) => (
                            <li key={option}>
                                <Link
                                    href={index({
                                        query:
                                            option === 'utang'
                                                ? { jenis: 'utang' }
                                                : {},
                                    })}
                                    preserveScroll
                                    aria-current={
                                        option === tab ? 'page' : undefined
                                    }
                                    className={cn(
                                        'flex h-9 items-center gap-2 rounded-lg border px-3 text-sm font-medium whitespace-nowrap focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:h-11',
                                        option === tab
                                            ? 'border-primary bg-brand-soft text-primary-text'
                                            : 'bg-card text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {tabCopy[option].label}
                                    <span className="num text-xs">
                                        {formatRupiah(totals[option].balance)}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                {/* On a phone the total takes the row and the two counts share one, so the list stays near the top. */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:gap-4">
                    <MetricTile
                        className="col-span-2 sm:col-span-1"
                        label={
                            tab === 'piutang'
                                ? 'Belum dibayar pelanggan'
                                : 'Belum dibayar ke supplier'
                        }
                        icon={HandCoins}
                        value={<Money value={total.balance} size="xl" />}
                    />
                    <MetricTile
                        label="Kontak"
                        icon={Users}
                        value={
                            <span className="num text-[28px] leading-none font-bold md:text-[32px]">
                                {total.contacts}
                            </span>
                        }
                        detail={copy.who}
                    />
                    <MetricTile
                        label="Tagihan tertua"
                        icon={Clock}
                        tone={
                            total.oldestDays === null
                                ? 'neutral'
                                : ageTone(total.oldestDays)
                        }
                        value={
                            <span className="num text-[28px] leading-none font-bold md:text-[32px]">
                                {total.oldestDays === null
                                    ? '–'
                                    : ageLabel(total.oldestDays)}
                            </span>
                        }
                        detail="Dihitung dari tagihan paling lama yang belum lunas"
                    />
                </div>

                <SectionCard title={copy.who} icon={HandCoins}>
                    <AppDataTable
                        caption={copy.who}
                        columns={columns}
                        data={rows}
                        getRowId={(row) => String(row.id)}
                        initialSorting={[{ id: 'sejak', desc: false }]}
                        pageSize={20}
                        layout={{
                            kontak: { className: 'min-w-44' },
                            sejak: { className: 'w-32' },
                            umur: { className: 'w-28' },
                            sisa: { align: 'right', className: 'w-32' },
                            aksi: {
                                align: 'right',
                                className: 'w-px whitespace-nowrap',
                            },
                        }}
                        renderMobileItem={(row) => (
                            <DebtListItem
                                row={row}
                                tab={tab}
                                moneyAccounts={moneyAccounts}
                                businessName={businessName}
                            />
                        )}
                        empty={
                            <EmptyState
                                icon={HandCoins}
                                title={
                                    tab === 'piutang'
                                        ? 'Tidak ada piutang'
                                        : 'Tidak ada utang'
                                }
                                description={copy.empty}
                                action={
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={createTransaction({
                                                query: {
                                                    jenis:
                                                        tab === 'piutang'
                                                            ? 'penjualan'
                                                            : 'pembelian',
                                                },
                                            })}
                                        >
                                            {tab === 'piutang'
                                                ? 'Catat penjualan'
                                                : 'Catat belanja'}
                                        </Link>
                                    </Button>
                                }
                            />
                        }
                    />
                </SectionCard>
            </div>
        </>
    );
}

type RowProps = {
    row: DebtRow;
    tab: DebtTab;
} & Pick<DebtsIndexProps, 'moneyAccounts' | 'businessName'>;

function ContactName({ row }: { row: DebtRow }) {
    return (
        <div className="flex min-w-0 flex-col gap-0.5">
            <Link
                href={show(row.id)}
                className="rounded-sm font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                {row.name}
            </Link>
            <span className="text-xs text-muted-foreground">
                {row.open} tagihan belum lunas
            </span>
        </div>
    );
}

function Age({ row }: { row: DebtRow }) {
    return (
        <LabelBadge
            icon={Clock}
            label={ageLabel(row.ageDays)}
            tone={ageTone(row.ageDays)}
        />
    );
}

function Actions({ row, tab, moneyAccounts, businessName }: RowProps) {
    return (
        <>
            <PaymentDialog
                title={`${tabCopy[tab].pay}: ${row.name}`}
                description={`Sisa ${formatRupiah(row.balance)}. Tagihan paling lama dilunasi lebih dulu.`}
                rest={row.balance}
                url={pay.url(row.id)}
                moneyAccounts={moneyAccounts}
                label={tabCopy[tab].pay}
                outgoing={tab === 'utang'}
                size="sm"
            />
            {tab === 'piutang' && row.phone && (
                <RemindDialog
                    name={row.name}
                    phone={row.phone}
                    balance={row.balance}
                    since={row.since}
                    businessName={businessName}
                    size="sm"
                />
            )}
        </>
    );
}

// Columns follow the owner's question here: who, since when, and how much.
function buildColumns(
    props: Omit<RowProps, 'row'>,
): Array<DataTableColumn<DebtRow>> {
    return [
        {
            id: 'kontak',
            accessorKey: 'name',
            header: 'Kontak',
            cell: ({ row }) => <ContactName row={row.original} />,
        },
        {
            id: 'sejak',
            accessorFn: (row) => new Date(row.since),
            header: 'Sejak',
            sortFn: 'datetime',
            cell: ({ row }) => (
                <span className="num text-muted-foreground">
                    {formatDate(row.original.since, 'short')}
                </span>
            ),
        },
        {
            id: 'umur',
            header: 'Umur',
            enableSorting: false,
            cell: ({ row }) => <Age row={row.original} />,
        },
        {
            id: 'sisa',
            accessorKey: 'balance',
            header: 'Sisa',
            sortFn: 'basic',
            cell: ({ row }) => <Money value={row.original.balance} size="sm" />,
        },
        {
            id: 'aksi',
            header: 'Aksi',
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex items-center justify-end gap-1.5">
                    <Actions row={row.original} {...props} />
                    <Button variant="ghost" size="icon" asChild>
                        <Link
                            href={show(row.original.id)}
                            aria-label={`Rincian tagihan ${row.original.name}`}
                        >
                            <ChevronRight aria-hidden />
                        </Link>
                    </Button>
                </div>
            ),
        },
    ];
}

/** One contact on a phone: the same facts as a row, stacked, with its buttons. */
function DebtListItem(props: RowProps) {
    const { row } = props;

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-start justify-between gap-3">
                <ContactName row={row} />
                <Money value={row.balance} size="md" />
            </div>
            <p className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <Age row={row} />
                sejak {formatDate(row.since, 'short')}
            </p>
            <div className="flex flex-wrap items-center gap-2 pt-1">
                <Actions {...props} />
                <Button variant="ghost" size="sm" className="ml-auto" asChild>
                    <Link href={show(row.id)}>
                        Rincian
                        <ChevronRight aria-hidden />
                    </Link>
                </Button>
            </div>
        </div>
    );
}
