import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import {
    ItemKindBadge,
    MarginLabel,
    StockStatusBadge,
} from '@/components/app/badges';
import { Money } from '@/components/app/money';
import { Button } from '@/components/ui/button';
import type { ServerTable } from '@/hooks/use-table-query';
import { formatQty } from '@/lib/format';
import { edit } from '@/routes/items';
import type { ItemRow } from '@/types/domain';
import { ItemKind } from '@/types/enums';
import { StockAdjustDialog } from './stock-adjust-dialog';

function StockCell({ item }: { item: ItemRow }) {
    if (item.kind === ItemKind.Komposisi) {
        return (
            <span className="text-xs text-muted-foreground">Dari resep</span>
        );
    }

    return (
        <span className="inline-flex flex-wrap items-center justify-end gap-1.5">
            <span className="num text-sm">
                {formatQty(item.stock, item.unit)}
            </span>
            <StockStatusBadge
                lowStock={item.lowStock}
                negativeStock={item.negativeStock}
            />
        </span>
    );
}

function RowActions({ item }: { item: ItemRow }) {
    return (
        <div className="flex flex-wrap gap-2 md:flex-nowrap md:justify-end">
            {item.kind !== ItemKind.Komposisi && (
                <StockAdjustDialog
                    item={item}
                    trigger={
                        <Button variant="outline" size="sm">
                            Koreksi stok
                            <span className="sr-only"> {item.name}</span>
                        </Button>
                    }
                />
            )}
            <Button variant="outline" size="sm" asChild>
                <Link href={edit(item.id)}>
                    Ubah<span className="sr-only"> {item.name}</span>
                </Link>
            </Button>
        </div>
    );
}

function NameCell({ item }: { item: ItemRow }) {
    return (
        <div className="flex min-w-0 flex-col gap-1">
            <span className="font-medium">{item.name}</span>
            {item.aliases.length > 0 && (
                <span className="line-clamp-1 text-xs text-muted-foreground">
                    Juga disebut {item.aliases.join(', ')}
                </span>
            )}
        </div>
    );
}

// Columns follow the owner's two questions here: is the price right,
// and is anything running out. Column ids are the server sort keys (?urut=).
const columns: Array<DataTableColumn<ItemRow>> = [
    {
        id: 'nama',
        accessorKey: 'name',
        header: 'Nama',
        cell: ({ row }) => <NameCell item={row.original} />,
    },
    {
        id: 'jenis',
        accessorKey: 'kind',
        header: 'Jenis',
        enableSorting: false,
        cell: ({ row }) => <ItemKindBadge kind={row.original.kind} />,
    },
    {
        id: 'harga',
        accessorKey: 'price',
        header: 'Harga jual',
        cell: ({ row }) =>
            row.original.price === null ? (
                <span className="text-xs text-muted-foreground">
                    Tidak dijual
                </span>
            ) : (
                <Money value={row.original.price} size="sm" />
            ),
    },
    {
        id: 'biaya',
        accessorKey: 'unitCost',
        header: 'HPP / biaya',
        cell: ({ row }) => <Money value={row.original.unitCost} size="sm" />,
    },
    {
        id: 'margin',
        accessorKey: 'marginPercent',
        header: 'Margin',
        // The owner's question is "which margin is thinnest", so the first click sorts upward.
        sortDescFirst: false,
        // Bahan already reads "Tidak dijual" under price; no second label here.
        cell: ({ row }) =>
            row.original.marginPercent !== null && (
                <MarginLabel percent={row.original.marginPercent} />
            ),
    },
    {
        id: 'stok',
        accessorKey: 'stock',
        header: 'Stok',
        cell: ({ row }) => <StockCell item={row.original} />,
    },
    {
        id: 'aksi',
        header: () => <span className="sr-only">Aksi</span>,
        enableSorting: false,
        cell: ({ row }) => <RowActions item={row.original} />,
    },
];

/** Phone layout of one row (the table becomes a list below 768 px). */
function ItemListRow({ item }: { item: ItemRow }) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-start justify-between gap-3">
                <NameCell item={item} />
                {item.price !== null && <Money value={item.price} size="sm" />}
            </div>
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <ItemKindBadge kind={item.kind} />
                <span className="text-xs text-muted-foreground">
                    {item.kind === ItemKind.Bahan ? 'Biaya' : 'HPP'}{' '}
                    <Money
                        value={item.unitCost}
                        size="sm"
                        className="text-xs font-medium"
                    />
                </span>
                {item.marginPercent !== null && (
                    <MarginLabel percent={item.marginPercent} />
                )}
                <StockCell item={item} />
            </div>
            <RowActions item={item} />
        </div>
    );
}

/** Products and stock, sorted and paged on the server (UI-PAGES C5). */
export function ItemTable({
    rows,
    server,
    empty,
}: {
    rows: ItemRow[];
    server: ServerTable;
    empty: ReactNode;
}) {
    return (
        <AppDataTable
            caption="Daftar produk dan stok"
            columns={columns}
            data={rows}
            server={server}
            loadingLabel="Memuat daftar produk"
            getRowId={(row) => String(row.id)}
            layout={{
                harga: { align: 'right' },
                biaya: { align: 'right' },
                stok: { align: 'right' },
                aksi: { align: 'right', className: 'w-px' },
            }}
            renderMobileItem={(item) => <ItemListRow item={item} />}
            empty={empty}
        />
    );
}
