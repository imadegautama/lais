import {
    createPaginatedRowModel,
    createSortedRowModel,
    rowPaginationFeature,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_basic,
    sortFn_datetime,
    sortFn_text,
    tableFeatures,
    useTable,
    type ColumnDef,
    type RowData,
    type SortingState,
} from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { Fragment, useEffect, useRef, type ReactNode } from 'react';
import { ErrorState, LoadingBlock } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ServerTable } from '@/hooks/use-table-query';
import { cn } from '@/lib/utils';

// Only the features the app uses; everything else is tree-shaken out.
export const dataTableFeatures = tableFeatures({
    rowSortingFeature,
    rowPaginationFeature,
    sortedRowModel: createSortedRowModel(),
    paginatedRowModel: createPaginatedRowModel(),
    sortFns: {
        alphanumeric: sortFn_alphanumeric,
        basic: sortFn_basic,
        datetime: sortFn_datetime,
        text: sortFn_text,
    },
});

export type DataTableFeatures = typeof dataTableFeatures;

export type DataTableColumn<TData extends RowData> = ColumnDef<
    DataTableFeatures,
    TData
>;

type ColumnLayout = { align?: 'left' | 'right'; className?: string };

type AppDataTableProps<TData extends RowData> = {
    /** Accessible name of the table, read before the rows. */
    caption: string;
    columns: Array<DataTableColumn<TData>>;
    data: TData[];
    getRowId: (row: TData) => string;
    /** Per-column alignment and widths, keyed by column id. */
    layout?: Record<string, ColumnLayout>;
    /** Phones get a list, not a squeezed table (DESIGN.md, "Tabel"). */
    renderMobileItem: (row: TData) => ReactNode;
    /** Optional group heading for the phone list, e.g. the date. */
    mobileGroupBy?: (row: TData) => string;
    initialSorting?: SortingState;
    /** Rows per page. Omit to show every row. */
    pageSize?: number;
    isLoading?: boolean;
    loadingLabel?: string;
    /** Rendered instead of the table, e.g. <ErrorState />. */
    error?: ReactNode;
    /** Rendered when there are no rows, e.g. <EmptyState />. */
    empty: ReactNode;
    /**
     * Server mode (useTableQuery): sorting and pages come from the URL and
     * `data` is already one page. Without it everything happens in the browser.
     */
    server?: ServerTable;
};

export function AppDataTable<TData extends RowData>({
    caption,
    columns,
    data,
    getRowId,
    layout = {},
    renderMobileItem,
    mobileGroupBy,
    initialSorting = [],
    pageSize,
    isLoading = false,
    loadingLabel = 'Memuat data',
    error,
    empty,
    server,
}: AppDataTableProps<TData>) {
    const table = useTable({
        features: dataTableFeatures,
        columns,
        data,
        getRowId,
        initialState: {
            sorting: initialSorting,
            pagination: { pageIndex: 0, pageSize: pageSize ?? 10_000 },
        },
        ...(server && {
            manualSorting: true,
            manualPagination: true,
            enableSortingRemoval: false,
            rowCount: server.meta.total,
            pageCount: server.meta.lastPage,
            state: { sorting: server.sorting, pagination: server.pagination },
            onSortingChange: server.onSortingChange,
            onPaginationChange: server.onPaginationChange,
        }),
    });
    const top = useRef<HTMLDivElement>(null);
    const serverPage = server?.meta.page;

    // After paging from the bottom, bring the new page's first row into view.
    useEffect(() => {
        if (serverPage && (top.current?.getBoundingClientRect().top ?? 0) < 0) {
            top.current?.scrollIntoView({ block: 'start' });
        }
    }, [serverPage]);

    if (error) {
        return <>{error}</>;
    }

    if (isLoading) {
        return <LoadingBlock label={loadingLabel} rows={5} />;
    }

    const failure = server?.failed && (
        <ErrorState
            title={`${caption} gagal dimuat`}
            description="Periksa koneksi, lalu coba lagi. Yang tampil di bawah masih data sebelumnya."
            onRetry={server.retry}
        />
    );

    if (data.length === 0) {
        return (
            <div className="flex flex-col gap-3">
                {failure}
                {empty}
            </div>
        );
    }

    const rows = table.getRowModel().rows;
    const total = server?.meta.total ?? data.length;
    const pageCount = server?.meta.lastPage ?? table.getPageCount();
    const pageIndex = server
        ? server.meta.page - 1
        : table.state.pagination.pageIndex;
    const size = server?.meta.perPage ?? table.state.pagination.pageSize;
    const first = pageIndex * size + 1;
    const last = Math.min((pageIndex + 1) * size, total);

    return (
        <div
            ref={top}
            className="flex scroll-mt-4 flex-col gap-3"
            aria-busy={server?.isLoading || undefined}
        >
            {failure}
            {server && (
                <span role="status" className="sr-only">
                    {server.isLoading ? loadingLabel : ''}
                </span>
            )}
            <div
                className={cn(
                    'flex flex-col gap-3 transition-opacity',
                    server?.isLoading && 'opacity-60',
                )}
            >
                <div className="hidden md:block">
                    <Table>
                        <caption className="sr-only">{caption}</caption>
                        <TableHeader>
                            {table.getHeaderGroups().map((group) => (
                                <TableRow
                                    key={group.id}
                                    className="hover:bg-transparent"
                                >
                                    {group.headers.map((header) => {
                                        const sorted =
                                            header.column.getIsSorted();
                                        const align =
                                            layout[header.column.id]?.align;

                                        return (
                                            <TableHead
                                                key={header.id}
                                                aria-sort={
                                                    sorted === 'asc'
                                                        ? 'ascending'
                                                        : sorted === 'desc'
                                                          ? 'descending'
                                                          : undefined
                                                }
                                                className={cn(
                                                    'h-10 text-xs font-semibold text-muted-foreground',
                                                    align === 'right' &&
                                                        'text-right',
                                                    layout[header.column.id]
                                                        ?.className,
                                                )}
                                            >
                                                {header.isPlaceholder ? null : header.column.getCanSort() ? (
                                                    <button
                                                        type="button"
                                                        onClick={header.column.getToggleSortingHandler()}
                                                        className={cn(
                                                            '-mx-1.5 inline-flex items-center gap-1 rounded-md px-1.5 py-1 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                            align === 'right' &&
                                                                'flex-row-reverse',
                                                        )}
                                                    >
                                                        <table.FlexRender
                                                            header={header}
                                                        />
                                                        {sorted === 'asc' ? (
                                                            <ArrowUp
                                                                className="size-3.5"
                                                                aria-hidden
                                                            />
                                                        ) : sorted ===
                                                          'desc' ? (
                                                            <ArrowDown
                                                                className="size-3.5"
                                                                aria-hidden
                                                            />
                                                        ) : (
                                                            <ArrowUpDown
                                                                className="size-3.5 opacity-60"
                                                                aria-hidden
                                                            />
                                                        )}
                                                    </button>
                                                ) : (
                                                    <table.FlexRender
                                                        header={header}
                                                    />
                                                )}
                                            </TableHead>
                                        );
                                    })}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getAllCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className={cn(
                                                'py-3',
                                                layout[cell.column.id]
                                                    ?.align === 'right' &&
                                                    'text-right',
                                                layout[cell.column.id]
                                                    ?.className,
                                            )}
                                        >
                                            <table.FlexRender cell={cell} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <ul className="flex flex-col md:hidden" aria-label={caption}>
                    {rows.map((row, index) => {
                        const group = mobileGroupBy?.(row.original);
                        const previous =
                            index > 0
                                ? mobileGroupBy?.(rows[index - 1].original)
                                : undefined;

                        return (
                            <Fragment key={row.id}>
                                {group && group !== previous && (
                                    <li
                                        aria-hidden
                                        className="pt-3 pb-1 text-xs font-semibold text-muted-foreground first:pt-0"
                                    >
                                        {group}
                                    </li>
                                )}
                                <li className="border-b py-3 last:border-0">
                                    {renderMobileItem(row.original)}
                                </li>
                            </Fragment>
                        );
                    })}
                </ul>
            </div>

            {pageCount > 1 && (
                <nav
                    aria-label={`Halaman ${caption}`}
                    className="flex flex-wrap items-center justify-between gap-2 border-t pt-3"
                >
                    <p
                        className="text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        {first}–{last} dari {total} · halaman {pageIndex + 1}{' '}
                        dari {pageCount}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                        >
                            <ChevronLeft aria-hidden />
                            Sebelumnya
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                        >
                            Berikutnya
                            <ChevronRight aria-hidden />
                        </Button>
                    </div>
                </nav>
            )}
        </div>
    );
}
