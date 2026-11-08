import { Head, Link, router } from '@inertiajs/react';
import {
    Archive,
    ListTree,
    Lock,
    Pencil,
    Plus,
    RotateCcw,
    Store,
} from 'lucide-react';
import { useState } from 'react';
import { LabelBadge } from '@/components/app/badges';
import { SearchFilter } from '@/components/app/filter-bar';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { AccountDialog } from '@/features/reports/account-dialog';
import { archive } from '@/routes/accounts';
import reports from '@/routes/reports';
import type { ChartAccount, ChartProps } from '@/types/domain';
import { AccountType, accountTypeLabels } from '@/types/enums';

function AccountRow({
    account,
    query,
}: {
    account: ChartAccount;
    query: Record<string, string>;
}) {
    return (
        <li className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <Link
                        href={reports.ledger(account.code, { query })}
                        className="rounded-sm text-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:inline-block pointer-coarse:py-3"
                    >
                        <span className="num text-muted-foreground">
                            {account.code}
                        </span>{' '}
                        {account.name}
                    </Link>
                    {account.system && (
                        <Lock
                            className="ml-1.5 inline size-3.5 align-[-2px] text-muted-foreground"
                            role="img"
                            aria-label="Akun sistem, terkunci"
                        />
                    )}
                    {!account.system && (
                        <div className="mt-1 flex flex-wrap items-center gap-1.5">
                            <LabelBadge
                                icon={Store}
                                label="Akun usaha"
                                tone="success"
                            />
                            {account.archived && (
                                <LabelBadge icon={Archive} label="Diarsipkan" />
                            )}
                        </div>
                    )}
                </div>
                <Money value={account.balance} size="md" />
            </div>

            {!account.system && (
                <div className="flex flex-wrap gap-2">
                    <AccountDialog
                        account={account}
                        trigger={
                            <Button type="button" variant="outline" size="sm">
                                <Pencil aria-hidden />
                                Ganti nama
                            </Button>
                        }
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.patch(archive.url(account.id), undefined, {
                                preserveScroll: true,
                            })
                        }
                    >
                        {account.archived ? (
                            <RotateCcw aria-hidden />
                        ) : (
                            <Archive aria-hidden />
                        )}
                        {account.archived ? 'Aktifkan' : 'Arsipkan'}
                    </Button>
                </div>
            )}
        </li>
    );
}

/** Daftar akun (COA): the fixed chart and what the business added to it. */
export default function Chart({ accounts, query }: ChartProps) {
    const [search, setSearch] = useState('');
    const wanted = search.trim().toLowerCase();
    const shown = accounts.filter(
        (account) =>
            wanted === '' ||
            account.name.toLowerCase().includes(wanted) ||
            account.code.startsWith(wanted),
    );
    const total = (type: AccountType) =>
        accounts
            .filter((account) => account.type === type)
            .reduce((sum, account) => sum + account.balance, 0);
    const types = Object.values(AccountType).filter((type) =>
        shown.some((account) => account.type === type),
    );

    return (
        <>
            <Head title="Daftar akun" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Daftar akun (COA)"
                    description="Semua akun pembukuan dengan saldonya hari ini. Akun bergembok terkunci karena dipakai jurnal otomatis."
                    actions={
                        <AccountDialog
                            trigger={
                                <Button type="button">
                                    <Plus aria-hidden />
                                    Tambah akun
                                </Button>
                            }
                        />
                    }
                />

                <SearchFilter
                    placeholder="Cari nama atau kode akun"
                    value={search}
                    onChange={setSearch}
                />

                {types.length === 0 ? (
                    <EmptyState
                        icon={ListTree}
                        title={`Tidak ada akun yang cocok dengan "${search}"`}
                        description="Coba nama atau kode lain."
                        action={
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSearch('')}
                            >
                                Tampilkan semua akun
                            </Button>
                        }
                    />
                ) : (
                    // Columns, not a grid: a short group never leaves a hole beside a long one.
                    <div className="columns-1 gap-4 md:gap-6 lg:columns-2 2xl:columns-3">
                        {types.map((type) => (
                            <div
                                key={type}
                                className="mb-4 break-inside-avoid md:mb-6"
                            >
                                <SectionCard
                                    title={accountTypeLabels[type]}
                                    action={
                                        <Money value={total(type)} size="md" />
                                    }
                                >
                                    <ul className="flex flex-col divide-y">
                                        {shown
                                            .filter(
                                                (account) =>
                                                    account.type === type,
                                            )
                                            .map((account) => (
                                                <AccountRow
                                                    key={account.id}
                                                    account={account}
                                                    query={query}
                                                />
                                            ))}
                                    </ul>
                                </SectionCard>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Chart.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Daftar akun', href: reports.chart() },
    ],
};
