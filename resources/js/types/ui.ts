import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};

/** Query state a server table applied, as returned by App\Support\TableQuery. */
export type TableQueryState = { cari: string; urut: string } & Record<
    string,
    string | string[]
>;

/** One page of a server-driven table. */
export type TablePage<TRow> = {
    data: TRow[];
    meta: { page: number; perPage: number; total: number; lastPage: number };
    query: TableQueryState;
};
