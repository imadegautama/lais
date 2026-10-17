import { ReceiptText } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { CategoryBadge, SourceLabel } from '@/components/app/badges';
import { Money } from '@/components/app/money';
import { EmptyState } from '@/components/app/states';
import { formatDate } from '@/lib/format';
import type { TransactionRow } from '@/types/domain';

// Columns follow the decision the owner makes in this list: what came
// in or went out, and when. The amount stays visible on every width.
const columns: Array<DataTableColumn<TransactionRow>> = [
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
            <span className="line-clamp-2 font-medium">
                {row.original.description}
            </span>
        ),
    },
    {
        id: 'jenis',
        accessorKey: 'type',
        header: 'Jenis',
        enableSorting: false,
        cell: ({ row }) => <CategoryBadge type={row.original.type} />,
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
        cell: ({ row }) => (
            <Money
                value={row.original.amount}
                direction={row.original.direction}
                size="sm"
            />
        ),
    },
];

type TransactionTableProps = {
    rows: TransactionRow[];
    pageSize?: number;
    isLoading?: boolean;
    error?: ReactNode;
    empty?: ReactNode;
};

export function TransactionTable({
    rows,
    pageSize,
    isLoading,
    error,
    empty,
}: TransactionTableProps) {
    return (
        <AppDataTable
            caption="Daftar transaksi"
            columns={columns}
            data={rows}
            getRowId={(row) => row.id}
            initialSorting={[{ id: 'waktu', desc: true }]}
            pageSize={pageSize}
            isLoading={isLoading}
            loadingLabel="Memuat transaksi"
            error={error}
            layout={{
                waktu: { className: 'w-32 whitespace-nowrap' },
                nominal: { align: 'right', className: 'w-32' },
            }}
            mobileGroupBy={(row) => formatDate(row.occurredAt, 'long')}
            renderMobileItem={(row) => (
                <div className="flex flex-col gap-1.5">
                    <div className="flex items-start justify-between gap-3">
                        <span className="line-clamp-2 text-sm font-medium">
                            {row.description}
                        </span>
                        <Money
                            value={row.amount}
                            direction={row.direction}
                            size="sm"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <CategoryBadge type={row.type} />
                        <span className="num text-xs text-muted-foreground">
                            {formatDate(row.occurredAt, 'time')}
                        </span>
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
