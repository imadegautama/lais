import { Head, Link } from '@inertiajs/react';
import { Package, PackageSearch, Plus } from 'lucide-react';
import { itemKinds } from '@/components/app/badges';
import {
    ChipFilter,
    FilterBar,
    SearchFilter,
} from '@/components/app/filter-bar';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { InventorySummary } from '@/features/items/inventory-summary';
import { ItemTable } from '@/features/items/item-table';
import { useTableQuery } from '@/hooks/use-table-query';
import { create, index } from '@/routes/items';
import type { ItemsIndexProps } from '@/types/domain';
import { ItemKind } from '@/types/enums';

const kindOptions = (Object.keys(itemKinds) as ItemKind[]).map((kind) => ({
    value: kind,
    label: itemKinds[kind].label,
    icon: itemKinds[kind].icon,
}));

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
                        variant="page"
                        icon={Package}
                        title="Belum ada produk"
                        description="Mulai dari yang paling sering dijual. Produk resep butuh bahan baku, jadi bahan bisa ditambah dulu."
                        action={
                            <div className="flex flex-wrap gap-2">
                                <Button asChild>
                                    <Link
                                        href={create({
                                            query: { jenis: ItemKind.Produk },
                                        })}
                                    >
                                        Tambah produk biasa
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={create({
                                            query: {
                                                jenis: ItemKind.Komposisi,
                                            },
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
                                <ItemTable
                                    rows={items.data}
                                    server={table}
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
