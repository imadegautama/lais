import { Head, Link } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
import { useMemo } from 'react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { FilterBar, SearchFilter } from '@/components/app/filter-bar';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { ContactDialog } from '@/features/contacts/contact-dialog';
import { localPhone } from '@/features/contacts/phone';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/contacts';
import type { ContactRow, ContactsIndexProps } from '@/types/domain';
import { ContactType, contactTypeLabels } from '@/types/enums';

const tabs = Object.values(ContactType);

const balanceWord = (tab: ContactType) =>
    tab === ContactType.Pelanggan ? 'Sisa piutang' : 'Sisa utang';

function buildColumns(tab: ContactType): Array<DataTableColumn<ContactRow>> {
    return [
        {
            id: 'nama',
            accessorKey: 'name',
            header: 'Nama',
            cell: ({ row }) => (
                <Link
                    href={show(row.original.id)}
                    className="rounded-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {row.original.name}
                </Link>
            ),
        },
        {
            id: 'wa',
            header: 'Nomor WA',
            enableSorting: false,
            cell: ({ row }) => (
                <span className="num text-muted-foreground">
                    {localPhone(row.original.phone) ?? 'Belum ada'}
                </span>
            ),
        },
        {
            id: 'transaksi',
            accessorKey: 'transactions',
            header: 'Transaksi',
            sortFn: 'basic',
            cell: ({ row }) => (
                <span className="num">{row.original.transactions}</span>
            ),
        },
        {
            id: 'terakhir',
            accessorKey: 'lastAt',
            header: 'Terakhir',
            sortFn: 'basic',
            cell: ({ row }) => (
                <span className="num text-muted-foreground">
                    {row.original.lastAt
                        ? formatDate(row.original.lastAt)
                        : 'Belum ada'}
                </span>
            ),
        },
        {
            id: 'sisa',
            header: balanceWord(tab),
            enableSorting: false,
            cell: ({ row }) => <Money value={row.original.balance} size="sm" />,
        },
    ];
}

function ContactItem({ row, tab }: { row: ContactRow; tab: ContactType }) {
    return (
        <Link
            href={show(row.id)}
            className="flex items-start justify-between gap-3 rounded-sm text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:min-h-11"
        >
            <span className="min-w-0">
                <span className="block font-medium">{row.name}</span>
                <span className="block text-xs text-muted-foreground">
                    {localPhone(row.phone) ?? 'Nomor WA belum ada'} ·{' '}
                    {row.transactions} transaksi
                </span>
            </span>
            <span className="flex shrink-0 flex-col items-end gap-0.5">
                <Money value={row.balance} size="sm" />
                <span className="text-xs text-muted-foreground">
                    {balanceWord(tab)}
                </span>
            </span>
        </Link>
    );
}

/** Kontak (UI-PAGES C12): everyone the business deals with, by side. */
export default function ContactsIndex({
    tab,
    counts,
    contacts,
}: ContactsIndexProps) {
    const table = useTableQuery(contacts, 'contacts');
    const columns = useMemo(() => buildColumns(tab), [tab]);
    const searching = table.search.value.trim() !== '';
    const add = (
        <ContactDialog
            type={tab}
            trigger={
                <Button type="button">
                    <Plus aria-hidden />
                    Tambah kontak
                </Button>
            }
        />
    );

    return (
        <>
            <Head title="Kontak" />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Kontak"
                    description="Pelanggan dan supplier usaha, dengan riwayat dan sisa tagihannya."
                    actions={add}
                />

                <nav aria-label="Jenis kontak">
                    <ul className="flex gap-1.5">
                        {tabs.map((option) => (
                            <li key={option}>
                                <Link
                                    href={index({ query: { jenis: option } })}
                                    preserveScroll
                                    aria-current={
                                        option === tab ? 'page' : undefined
                                    }
                                    className={cn(
                                        'flex h-9 items-center gap-2 rounded-lg border px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:h-11',
                                        option === tab
                                            ? 'border-primary bg-brand-soft text-primary-text'
                                            : 'bg-card text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {contactTypeLabels[option]}
                                    <span className="num text-xs">
                                        {counts[option]}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                <SectionCard
                    title={`Daftar ${contactTypeLabels[tab].toLowerCase()}`}
                >
                    <div className="flex flex-col gap-3">
                        <FilterBar>
                            <SearchFilter
                                placeholder="Cari nama atau nomor WA"
                                {...table.search}
                            />
                        </FilterBar>
                        <AppDataTable
                            caption={`Daftar ${contactTypeLabels[tab].toLowerCase()}`}
                            columns={columns}
                            data={contacts.data}
                            getRowId={(row) => String(row.id)}
                            server={table}
                            layout={{
                                transaksi: {
                                    align: 'right',
                                    className: 'w-28',
                                },
                                terakhir: { className: 'w-32' },
                                sisa: { align: 'right', className: 'w-36' },
                            }}
                            renderMobileItem={(row) => (
                                <ContactItem row={row} tab={tab} />
                            )}
                            empty={
                                searching ? (
                                    <EmptyState
                                        icon={Users}
                                        title="Tidak ada kontak yang cocok"
                                        description="Coba nama atau nomor lain."
                                        action={
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    table.search.onChange('')
                                                }
                                            >
                                                Tampilkan semua kontak
                                            </Button>
                                        }
                                    />
                                ) : (
                                    <EmptyState
                                        icon={Users}
                                        title={`Belum ada ${contactTypeLabels[tab].toLowerCase()}`}
                                        description="Kontak muncul sendiri dari transaksi dan pesanan, atau tambahkan sendiri."
                                        action={add}
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

ContactsIndex.layout = {
    breadcrumbs: [{ title: 'Kontak', href: index() }],
};
