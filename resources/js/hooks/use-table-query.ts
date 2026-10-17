import { router, usePage } from '@inertiajs/react';
import {
    functionalUpdate,
    type OnChangeFn,
    type PaginationState,
    type SortingState,
} from '@tanstack/react-table';
import { useEffect, useRef, useState } from 'react';
import type { TablePage, TableQueryState } from '@/types/ui';

const SEARCH_DELAY_MS = 300;

/** What AppDataTable needs to run in server mode. */
export type ServerTable = {
    meta: TablePage<unknown>['meta'];
    sorting: SortingState;
    onSortingChange: OnChangeFn<SortingState>;
    pagination: PaginationState;
    onPaginationChange: OnChangeFn<PaginationState>;
    isLoading: boolean;
    failed: boolean;
    retry: () => void;
};

type Visit = { query: TableQueryState; page: number };

/** "-margin" <-> [{ id: 'margin', desc: true }] */
const toSorting = (urut: string): SortingState =>
    urut === ''
        ? []
        : [{ id: urut.replace(/^-/, ''), desc: urut.startsWith('-') }];

const toUrut = (sorting: SortingState) =>
    sorting[0] ? `${sorting[0].desc ? '-' : ''}${sorting[0].id}` : '';

/** Keeps only what is applied, so the URL stays short and shareable. */
function toParams({ query, page }: Visit): Record<string, string | string[]> {
    const params = Object.fromEntries(
        Object.entries(query).filter(([, value]) => value.length > 0),
    );

    return page > 1 ? { ...params, halaman: String(page) } : params;
}

/**
 * URL state for a table built by App\Support\TableQuery. Filters, search,
 * sort, and page changes reload only `prop`, so the rest of the page stays put.
 */
export function useTableQuery<TRow>(table: TablePage<TRow>, prop: string) {
    const path = usePage().url.split('?')[0];
    const [pending, setPending] = useState<Visit | null>(null);
    const [failed, setFailed] = useState<Visit | null>(null);
    const [isLoading, setLoading] = useState(false);
    const [searchText, setSearchText] = useState(table.query.cari);
    const timer = useRef<number | undefined>(undefined);
    const visitId = useRef(0);
    const current: Visit = pending ?? {
        query: table.query,
        page: table.meta.page,
    };
    // The debounced search reads this, so a chip clicked while typing is kept.
    const latest = useRef(current);

    useEffect(() => {
        latest.current = current;
    });

    useEffect(() => () => window.clearTimeout(timer.current), []);

    const visit = (next: Visit) => {
        window.clearTimeout(timer.current);
        const id = ++visitId.current;
        setPending(next);

        router.get(path, toParams(next), {
            only: [prop],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                setFailed(null);
                setLoading(true);
            },
            onHttpException: () => {
                setFailed(next);

                return false;
            },
            onNetworkError: () => {
                setFailed(next);

                return false;
            },
            onFinish: () => {
                // A newer visit cancelled this one; let that one settle the state.
                if (id === visitId.current) {
                    setLoading(false);
                    setPending(null);
                }
            },
        });
    };

    const set = (changes: Partial<TableQueryState>) =>
        visit({
            query: { ...current.query, ...changes } as TableQueryState,
            page: 1,
        });

    const sorting = toSorting(current.query.urut);
    const pagination: PaginationState = {
        pageIndex: current.page - 1,
        pageSize: table.meta.perPage,
    };

    return {
        query: current.query,
        set,
        /** Props for a multi-value filter control such as ChipFilter. */
        filter: (key: string) => ({
            value: (current.query[key] as string[] | undefined) ?? [],
            onChange: (value: string[]) => set({ [key]: value }),
        }),
        /** Props for SearchFilter: the text updates at once, the visit after a pause. */
        search: {
            value: searchText,
            onChange: (text: string) => {
                setSearchText(text);
                window.clearTimeout(timer.current);
                timer.current = window.setTimeout(
                    () =>
                        visit({
                            query: {
                                ...latest.current.query,
                                cari: text.trim(),
                            },
                            page: 1,
                        }),
                    SEARCH_DELAY_MS,
                );
            },
        },
        /** Clears search and filters, keeps the sort. */
        reset: () => {
            setSearchText('');
            visit({
                query: Object.fromEntries(
                    Object.entries(current.query).map(([key, value]) => [
                        key,
                        key === 'urut' ? value : Array.isArray(value) ? [] : '',
                    ]),
                ) as TableQueryState,
                page: 1,
            });
        },
        meta: table.meta,
        sorting,
        onSortingChange: ((updater) =>
            set({
                urut: toUrut(functionalUpdate(updater, sorting)),
            })) satisfies OnChangeFn<SortingState>,
        pagination,
        onPaginationChange: ((updater) =>
            visit({
                query: current.query,
                page: functionalUpdate(updater, pagination).pageIndex + 1,
            })) satisfies OnChangeFn<PaginationState>,
        isLoading,
        failed: failed !== null,
        retry: () => {
            if (failed) {
                visit(failed);
            }
        },
    } satisfies ServerTable & Record<string, unknown>;
}
