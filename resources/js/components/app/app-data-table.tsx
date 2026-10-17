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
import { Fragment, type ReactNode } from 'react';
import { LoadingBlock } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
    });

    if (error) {
        return <>{error}</>;
    }

    if (isLoading) {
        return <LoadingBlock label={loadingLabel} rows={5} />;
    }

    if (data.length === 0) {
        return <>{empty}</>;
    }

    const rows = table.getRowModel().rows;
    const pageCount = table.getPageCount();
    const { pageIndex, pageSize: size } = table.state.pagination;
    const first = pageIndex * size + 1;
    const last = Math.min((pageIndex + 1) * size, data.length);

    return (
        <div className="flex flex-col gap-3">
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
                                    const sorted = header.column.getIsSorted();
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
                                                    ) : sorted === 'desc' ? (
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
                                            layout[cell.column.id]?.align ===
                                                'right' && 'text-right',
                                            layout[cell.column.id]?.className,
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

            {pageCount > 1 && (
                <nav
                    aria-label={`Halaman ${caption}`}
                    className="flex flex-wrap items-center justify-between gap-2 border-t pt-3"
                >
                    <p
                        className="text-xs text-muted-foreground"
                        aria-live="polite"
                    >
                        {first}–{last} dari {data.length} · halaman{' '}
                        {pageIndex + 1} dari {pageCount}
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
