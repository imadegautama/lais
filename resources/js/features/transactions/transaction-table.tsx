import { Link } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import {
    CategoryBadge,
    methodLabel,
    SourceLabel,
    VoidBadge,
} from '@/components/app/badges';
import { Money } from '@/components/app/money';
import { EmptyState } from '@/components/app/states';
import type { ServerTable } from '@/hooks/use-table-query';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show } from '@/routes/transactions';
import type { TransactionRow } from '@/types/domain';
import { PaymentMethod, TransactionType } from '@/types/enums';

/** "Tunai · Bu Sari", "Non-tunai · BCA", or "Kas tunai → BCA" under the description. */
function MethodLine({ row }: { row: TransactionRow }) {
    // The description already names the rekening or contact of an opening balance.
    if (row.type === TransactionType.SaldoAwal) {
        return null;
    }

    if (row.type === TransactionType.PindahDana) {
        return (
            <span className="text-xs text-muted-foreground">
                {row.moneyAccount} → {row.toMoneyAccount}
            </span>
        );
    }

    return (
        <span className="text-xs text-muted-foreground">
            {methodLabel(row.type, row.method)}
            {row.method === PaymentMethod.NonTunai &&
                row.moneyAccount &&
                ` · ${row.moneyAccount}`}
            {row.contact && ` · ${row.contact}`}
        </span>
    );
}

function Description({
    row,
    linked,
}: {
    row: TransactionRow;
    linked: boolean;
}) {
    const text = (
        <span
            className={cn(
                'line-clamp-2 font-medium',
                row.voided && 'text-muted-foreground line-through',
            )}
        >
            {row.description}
        </span>
    );

    return linked ? (
        <Link
            href={show(row.id)}
            className="rounded-sm hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {text}
        </Link>
    ) : (
        text
    );
}

function Amount({ row }: { row: TransactionRow }) {
    return (
        <Money
            value={row.amount}
            direction={row.voided ? 'none' : row.direction}
            size="sm"
            className={cn(row.voided && 'text-muted-foreground line-through')}
        />
    );
}

// Columns follow the decision the owner makes in this list: what came
// in or went out, and when. The amount stays visible on every width.
function buildColumns(linked: boolean): Array<DataTableColumn<TransactionRow>> {
    return [
        {
            id: 'waktu',
            accessorFn: (row) => new Date(row.occurredAt),
            header: 'Waktu',
            sortFn: 'datetime',
            cell: ({ row }) => (
                <span className="num text-muted-foreground">
                    {formatDate(row.original.occurredAt, 'datetime')}
                </span>
            ),
        },
        {
            id: 'keterangan',
            accessorKey: 'description',
            header: 'Keterangan',
            enableSorting: false,
            cell: ({ row }) => (
                <div className="flex flex-col gap-0.5">
                    <Description row={row.original} linked={linked} />
                    <MethodLine row={row.original} />
                </div>
            ),
        },
        {
            id: 'jenis',
            accessorKey: 'type',
            header: 'Jenis',
            enableSorting: false,
            cell: ({ row }) =>
                row.original.voided ? (
                    <VoidBadge />
                ) : (
                    <CategoryBadge type={row.original.type} />
                ),
        },
        {
            id: 'sumber',
            accessorKey: 'source',
            header: 'Sumber',
            enableSorting: false,
            cell: ({ row }) => <SourceLabel source={row.original.source} />,
        },
        {
            id: 'nominal',
            accessorKey: 'amount',
            header: 'Nominal',
            sortFn: 'basic',
            cell: ({ row }) => <Amount row={row.original} />,
        },
    ];
}

type TransactionTableProps = {
    rows: TransactionRow[];
    pageSize?: number;
    isLoading?: boolean;
    error?: ReactNode;
    empty?: ReactNode;
    server?: ServerTable;
    /** Descriptions link to the transaction detail (C4). */
    linked?: boolean;
};

export function TransactionTable({
    rows,
    pageSize,
    isLoading,
    error,
    empty,
    server,
    linked = false,
}: TransactionTableProps) {
    const columns = useMemo(() => buildColumns(linked), [linked]);

    return (
        <AppDataTable
            caption="Daftar transaksi"
            columns={columns}
            data={rows}
            getRowId={(row) => String(row.id)}
            initialSorting={[{ id: 'waktu', desc: true }]}
            pageSize={pageSize}
            isLoading={isLoading}
            loadingLabel="Memuat transaksi"
            error={error}
            server={server}
            layout={{
                waktu: { className: 'w-32 whitespace-nowrap' },
                nominal: { align: 'right', className: 'w-32' },
            }}
            mobileGroupBy={(row) => formatDate(row.occurredAt, 'long')}
            renderMobileItem={(row) => (
                <div className="flex flex-col gap-1.5">
                    <div className="flex items-start justify-between gap-3">
                        <Description row={row} linked={linked} />
                        <Amount row={row} />
                    </div>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        {row.voided ? (
                            <VoidBadge />
                        ) : (
                            <CategoryBadge type={row.type} />
                        )}
                        <span className="num text-xs text-muted-foreground">
                            {formatDate(row.occurredAt, 'time')}
                        </span>
                        <MethodLine row={row} />
                        <SourceLabel source={row.source} />
                    </div>
                </div>
            )}
            empty={
                empty ?? (
                    <EmptyState
                        icon={ReceiptText}
                        title="Belum ada transaksi"
                        description="Catat penjualan pertama lewat tombol Catat atau kirim pesan ke bot WhatsApp."
                    />
                )
            }
        />
    );
}
