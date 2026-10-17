import { Head, Link } from '@inertiajs/react';
import {
    CircleCheck,
    CircleX,
    Coins,
    Package,
    PackageSearch,
    Plus,
    TriangleAlert,
    Warehouse,
} from 'lucide-react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import {
    ItemKindBadge,
    itemKinds,
    MarginLabel,
    StockStatusBadge,
} from '@/components/app/badges';
import {
    ChipFilter,
    FilterBar,
    SearchFilter,
} from '@/components/app/filter-bar';
import { MetricCount, MetricTile } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { StockAdjustDialog } from '@/components/app/stock-adjust-dialog';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatQty } from '@/lib/format';
import { create, edit, index } from '@/routes/items';
import type { ItemKind, ItemRow, ItemsIndexProps } from '@/types/domain';

function StockCell({ item }: { item: ItemRow }) {
    if (item.kind === 'komposisi') {
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
            {item.kind !== 'komposisi' && (
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

const kindOptions = (Object.keys(itemKinds) as ItemKind[]).map((kind) => ({
    value: kind,
    label: itemKinds[kind].label,
    icon: itemKinds[kind].icon,
}));

/** "Air Mineral 600 ml" / "Beras dan Telur" / "Beras, Telur, dan 3 lainnya" */
function nameList(names: string[], count: number): string {
    return count <= 2
        ? names.join(' dan ')
        : `${names.join(', ')}, dan ${count - 2} lainnya`;
}

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
                    {item.kind === 'bahan' ? 'Biaya' : 'HPP'}{' '}
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

// The page's one focus card: what the stock is worth, and whether anything needs attention.
function InventorySummary({ summary }: Pick<ItemsIndexProps, 'summary'>) {
    const low = summary.lowStockCount;
    const negative = summary.negativeStockCount;

    return (
        <SectionCard
            title="Persediaan sekarang"
            description="Dinilai dengan biaya rata-rata tiap bahan dan produk."
            icon={Warehouse}
            tone="focus"
        >
            <div className="grid grid-cols-2 gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <MetricTile
                    label="Nilai persediaan"
                    icon={Coins}
                    value={<Money value={summary.inventoryValue} size="xl" />}
                    detail={`${summary.stockedCount} bahan baku dan produk biasa`}
                    className="col-span-2 md:col-span-1"
                />
                <MetricTile
                    label="Stok menipis"
                    icon={low > 0 ? TriangleAlert : CircleCheck}
                    tone={low > 0 ? 'warning' : 'neutral'}
                    value={<MetricCount value={low} unit="item" />}
                    detail={
                        low > 0
                            ? nameList(summary.lowStockNames, low)
                            : 'Semua di atas stok minimum.'
                    }
                />
                <MetricTile
                    label="Stok minus"
                    icon={negative > 0 ? CircleX : CircleCheck}
                    tone={negative > 0 ? 'danger' : 'neutral'}
                    value={<MetricCount value={negative} unit="item" />}
                    detail={
                        negative > 0
                            ? `${nameList(summary.negativeStockNames, negative)}. Catat belanja atau koreksi stok.`
                            : 'Tidak ada stok minus.'
                    }
                />
            </div>
        </SectionCard>
    );
}

export default function ItemsIndex({ items, summary }: ItemsIndexProps) {
    const table = useTableQuery(items, 'items');

    return (
        <>
            <Head title="Produk & stok" />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Produk & stok"
                    description="Harga jual, HPP, dan sisa stok semua yang kamu jual dan pakai."
                    actions={
                        summary.itemCount > 0 && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus aria-hidden />
                                    Tambah produk
                                </Link>
                            </Button>
                        )
                    }
                />

                {summary.itemCount === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="Belum ada produk"
                        description="Mulai dari yang paling sering dijual. Produk resep butuh bahan baku, jadi bahan bisa ditambah dulu."
                        action={
                            <div className="flex flex-wrap gap-2">
                                <Button asChild>
                                    <Link
                                        href={create({
                                            query: { jenis: 'produk' },
                                        })}
                                    >
                                        Tambah produk biasa
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={create({
                                            query: { jenis: 'komposisi' },
                                        })}
                                    >
                                        Tambah produk dengan resep
                                    </Link>
                                </Button>
                            </div>
                        }
                    />
                ) : (
                    <>
                        <InventorySummary summary={summary} />

                        <SectionCard
                            title="Daftar produk"
                            description={
                                <>
                                    {summary.itemCount} item.
                                    <span className="hidden md:inline">
                                        {' '}
                                        Urutkan dengan klik judul kolom.
                                    </span>
                                </>
                            }
                        >
                            <div className="flex flex-col gap-4">
                                <FilterBar>
                                    <ChipFilter
                                        label="Saring jenis"
                                        options={kindOptions}
                                        {...table.filter('jenis')}
                                    />
                                    <SearchFilter
                                        placeholder="Cari nama atau alias"
                                        {...table.search}
                                    />
                                </FilterBar>
                                <AppDataTable
                                    caption="Daftar produk dan stok"
                                    columns={columns}
                                    data={items.data}
                                    server={table}
                                    loadingLabel="Memuat daftar produk"
                                    getRowId={(row) => String(row.id)}
                                    layout={{
                                        harga: { align: 'right' },
                                        biaya: { align: 'right' },
                                        stok: { align: 'right' },
                                        aksi: {
                                            align: 'right',
                                            className: 'w-px',
                                        },
                                    }}
                                    renderMobileItem={(item) => (
                                        <ItemListRow item={item} />
                                    )}
                                    empty={
                                        <EmptyState
                                            icon={PackageSearch}
                                            title="Tidak ada yang cocok"
                                            description="Coba kata lain atau hapus saringan jenis."
                                            action={
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={table.reset}
                                                >
                                                    Hapus saringan
                                                </Button>
                                            }
                                        />
                                    }
                                />
                            </div>
                        </SectionCard>
                    </>
                )}
            </div>
        </>
    );
}

ItemsIndex.layout = {
    breadcrumbs: [{ title: 'Produk & stok', href: index() }],
};
