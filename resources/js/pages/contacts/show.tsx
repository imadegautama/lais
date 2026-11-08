import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Pencil, ReceiptText, UserRound } from 'lucide-react';
import {
    AppDataTable,
    type DataTableColumn,
} from '@/components/app/app-data-table';
import { LabelBadge, OrderStatusBadge } from '@/components/app/badges';
import { DetailRow } from '@/components/app/detail-row';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { ContactDialog } from '@/features/contacts/contact-dialog';
import { EraseButton } from '@/features/contacts/erase-button';
import { localPhone } from '@/features/contacts/phone';
import { TransactionTable } from '@/features/transactions/transaction-table';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/contacts';
import { show as showDebt } from '@/routes/debts';
import { show as showOrder } from '@/routes/orders';
import { create as createTransaction } from '@/routes/transactions';
import type { ContactOrder, ContactShowProps } from '@/types/domain';
import { ContactType, contactTypeLabels } from '@/types/enums';

const orderColumns: Array<DataTableColumn<ContactOrder>> = [
    {
        id: 'kode',
        accessorKey: 'code',
        header: 'Pesanan',
        enableSorting: false,
        cell: ({ row }) => (
            <Link
                href={showOrder(row.original.code)}
                className="rounded-sm font-medium hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:inline-block pointer-coarse:py-3"
            >
                {row.original.code}
            </Link>
        ),
    },
    {
        id: 'tanggal',
        header: 'Tanggal',
        enableSorting: false,
        cell: ({ row }) => (
            <span className="num text-muted-foreground">
                {formatDate(row.original.createdAt)}
            </span>
        ),
    },
    {
        id: 'status',
        header: 'Status',
        enableSorting: false,
        cell: ({ row }) => <OrderStatusBadge status={row.original.status} />,
    },
    {
        id: 'total',
        header: 'Total',
        enableSorting: false,
        cell: ({ row }) => <Money value={row.original.total} size="sm" />,
    },
];

function OrderItem({ order }: { order: ContactOrder }) {
    return (
        <Link
            href={showOrder(order.code)}
            className="flex items-start justify-between gap-3 rounded-sm text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:min-h-11"
        >
            <span className="flex min-w-0 flex-col gap-1">
                <span className="font-medium">{order.code}</span>
                <OrderStatusBadge status={order.status} />
            </span>
            <Money value={order.total} size="sm" />
        </Link>
    );
}

/** One customer or supplier: who they are, what they did, and their data. */
export default function ContactShow({
    contact,
    balance,
    blockedBy,
    transactions,
    orders,
}: ContactShowProps) {
    const receivable = contact.type === ContactType.Pelanggan;
    const phone = localPhone(contact.phone);

    return (
        <>
            <Head title={contact.name} />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <Button variant="ghost" className="-ml-3 w-fit" asChild>
                    <Link href={index({ query: { jenis: contact.type } })}>
                        <ArrowLeft aria-hidden />
                        Kontak
                    </Link>
                </Button>
                <PageHeader
                    title={contact.name}
                    meta={
                        <LabelBadge
                            icon={UserRound}
                            label={contactTypeLabels[contact.type]}
                        />
                    }
                    description={
                        phone ? `Nomor WA ${phone}` : 'Nomor WA belum ada.'
                    }
                    actions={
                        <ContactDialog
                            contact={contact}
                            trigger={
                                <Button type="button" variant="outline">
                                    <Pencil aria-hidden />
                                    Ubah kontak
                                </Button>
                            }
                        />
                    }
                />

                <div className="grid grid-cols-1 items-start gap-4 md:gap-6 xl:grid-cols-12">
                    <div className="flex min-w-0 flex-col gap-4 md:gap-6 xl:col-span-8">
                        <SectionCard title="Riwayat transaksi">
                            <TransactionTable
                                rows={transactions}
                                pageSize={20}
                                linked
                                empty={
                                    <EmptyState
                                        icon={ReceiptText}
                                        title="Belum ada transaksi"
                                        description="Riwayatnya terisi sendiri dari catatan yang memakai kontak ini."
                                        action={
                                            <Button asChild>
                                                <Link
                                                    href={createTransaction()}
                                                >
                                                    Catat transaksi
                                                </Link>
                                            </Button>
                                        }
                                    />
                                }
                            />
                        </SectionCard>

                        {orders.length > 0 && (
                            <SectionCard title="Riwayat pesanan">
                                <AppDataTable
                                    caption="Riwayat pesanan"
                                    columns={orderColumns}
                                    data={orders}
                                    getRowId={(order) => order.code}
                                    pageSize={20}
                                    layout={{
                                        total: {
                                            align: 'right',
                                            className: 'w-32',
                                        },
                                    }}
                                    renderMobileItem={(order) => (
                                        <OrderItem order={order} />
                                    )}
                                    empty={null}
                                />
                            </SectionCard>
                        )}
                    </div>

                    <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 md:gap-6 xl:sticky xl:top-20 xl:col-span-4 xl:flex xl:flex-col">
                        <SectionCard title="Sisa tagihan">
                            <dl className="flex flex-col text-sm">
                                <DetailRow
                                    label={
                                        receivable
                                            ? 'Belum dibayar'
                                            : 'Belum kita bayar'
                                    }
                                >
                                    <Money value={balance} size="md" />
                                </DetailRow>
                            </dl>
                            <Button
                                variant="outline"
                                className="mt-3 w-full"
                                asChild
                            >
                                <Link href={showDebt(contact.id)}>
                                    Lihat tagihan dan pembayaran
                                </Link>
                            </Button>
                        </SectionCard>

                        <SectionCard title="Data kontak">
                            <p className="mb-2 text-sm text-muted-foreground">
                                {blockedBy ??
                                    'Kalau orang ini meminta datanya dihapus, hapus di sini. Catatan keuangannya tetap ada.'}
                            </p>
                            {blockedBy === null && (
                                <EraseButton
                                    id={contact.id}
                                    name={contact.name}
                                />
                            )}
                        </SectionCard>
                    </div>
                </div>
            </div>
        </>
    );
}

ContactShow.layout = {
    breadcrumbs: [{ title: 'Kontak', href: index() }],
};
