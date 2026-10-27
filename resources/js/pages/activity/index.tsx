import { Head } from '@inertiajs/react';
import { History, SearchX } from 'lucide-react';
import { ActivityItem } from '@/components/app/activity-list';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { SourceLabel } from '@/components/app/badges';
import {
    ChipFilter,
    FilterBar,
    SearchFilter,
} from '@/components/app/filter-bar';
import { PageHeader } from '@/components/app/page-header';
import { PeriodPicker } from '@/components/app/period-picker';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/activity';
import type { ActivityEntry, ActivityIndexProps } from '@/types/domain';
import {
    ActivityCategory,
    activityCategoryLabels,
    Period,
    periodLabels,
} from '@/types/enums';

type PeriodChoice = Period | 'semua';

const periods: Array<{ key: PeriodChoice; label: string }> = [
    { key: 'semua', label: 'Semua' },
    ...Object.values(Period).map((key) => ({ key, label: periodLabels[key] })),
];

const categoryOptions = Object.values(ActivityCategory).map((value) => ({
    value,
    label: activityCategoryLabels[value],
}));

// When it happened, what happened, and who did it, through which channel:
// the questions an owner brings to an audit log.
const columns: Array<DataTableColumn<ActivityEntry>> = [
    {
        id: 'waktu',
        accessorFn: (row) => new Date(row.at),
        header: 'Waktu',
        sortFn: 'datetime',
        cell: ({ row }) => (
            <span className="num text-muted-foreground">
                {formatDate(row.original.at, 'datetime')}
            </span>
        ),
    },
    {
        id: 'aktivitas',
        accessorKey: 'description',
        header: 'Aktivitas',
        enableSorting: false,
        cell: ({ row }) => (
            <ActivityItem entry={row.original} linked meta={false} />
        ),
    },
    {
        id: 'siapa',
        accessorKey: 'actor',
        header: 'Siapa',
        enableSorting: false,
    },
    {
        id: 'lewat',
        accessorKey: 'source',
        header: 'Lewat',
        enableSorting: false,
        cell: ({ row }) =>
            row.original.source && <SourceLabel source={row.original.source} />,
    },
];

export default function ActivityIndex({ activities }: ActivityIndexProps) {
    const table = useTableQuery(activities, 'activities');
    const period = ((table.query.periode as string[] | undefined)?.[0] ??
        'semua') as PeriodChoice;
    const filtered =
        table.query.cari !== '' ||
        ((table.query.kategori as string[] | undefined)?.length ?? 0) > 0 ||
        period !== 'semua';

    return (
        <>
            <Head title="Log aktivitas" />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Log aktivitas"
                    description="Siapa mencatat, mengubah, atau membatalkan apa, dan kapan. Isinya tidak bisa diubah atau dihapus."
                />

                <SectionCard
                    title="Semua aktivitas"
                    description="Terbaru di atas. Klik aktivitas untuk membuka datanya."
                >
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <PeriodPicker
                                fill
                                periods={periods}
                                value={period}
                                onChange={(next) =>
                                    table.set({
                                        periode: next === 'semua' ? [] : [next],
                                    })
                                }
                            />
                            <FilterBar>
                                <ChipFilter
                                    label="Saring kategori"
                                    options={categoryOptions}
                                    {...table.filter('kategori')}
                                />
                                <SearchFilter
                                    placeholder="Cari aktivitas atau nama"
                                    {...table.search}
                                />
                            </FilterBar>
                        </div>
                        <AppDataTable
                            caption="Log aktivitas"
                            columns={columns}
                            data={activities.data}
                            getRowId={(row) => String(row.id)}
                            initialSorting={[{ id: 'waktu', desc: true }]}
                            server={table}
                            loadingLabel="Memuat log aktivitas"
                            layout={{
                                waktu: { className: 'w-40 whitespace-nowrap' },
                                siapa: { className: 'w-40' },
                                lewat: { className: 'w-32' },
                            }}
                            mobileGroupBy={(row) => formatDate(row.at, 'long')}
                            renderMobileItem={(row) => (
                                <ActivityItem entry={row} linked meta="time" />
                            )}
                            empty={
                                filtered ? (
                                    <EmptyState
                                        icon={SearchX}
                                        title="Tidak ada yang cocok"
                                        description="Coba periode lain, kata lain, atau hapus saringan."
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
                                ) : (
                                    <EmptyState
                                        icon={History}
                                        title="Belum ada aktivitas"
                                        description="Setiap pencatatan, perubahan, dan pembatalan akan tercatat di sini, lengkap dengan siapa dan kapan."
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

ActivityIndex.layout = {
    breadcrumbs: [{ title: 'Log aktivitas', href: index() }],
};
