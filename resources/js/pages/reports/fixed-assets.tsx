import { Head, Link, router } from '@inertiajs/react';
import { Hammer, Plus, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { LabelBadge } from '@/components/app/badges';
import { DetailRow } from '@/components/app/detail-row';
import { Money } from '@/components/app/money';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PrintHeader } from '@/features/reports/print-header';
import { ReportLead } from '@/features/reports/report-lead';
import { ReportToolbar } from '@/features/reports/report-toolbar';
import { TermHint } from '@/features/reports/term-hint';
import { formatDate, formatRupiah } from '@/lib/format';
import { parseDecimal } from '@/lib/number';
import { index, update } from '@/routes/fixed-assets';
import reports from '@/routes/reports';
import { create as createTransaction } from '@/routes/transactions';
import type { FixedAsset, FixedAssetsProps } from '@/types/domain';
import { TransactionKind, TransactionType } from '@/types/enums';

const years = (months: number) =>
    `${String(months / 12).replace('.', ',')} tahun`;

const usedPercent = (accumulated: number, cost: number) =>
    cost > 0 ? (accumulated / cost) * 100 : 0;

/** The useful life, editable until a month has been depreciated. */
function LifeField({ asset }: { asset: FixedAsset }) {
    const [value, setValue] = useState(
        asset.lifeMonths === null ? '4' : String(asset.lifeMonths / 12),
    );
    const [saving, setSaving] = useState(false);
    const id = `life-${asset.id}`;
    const months = Math.round(parseDecimal(value) * 12);

    return (
        <form
            className="mt-3 flex flex-wrap items-end gap-2 border-t pt-3 print:hidden"
            onSubmit={(event) => {
                event.preventDefault();
                router.patch(
                    update.url(asset.id),
                    { useful_life_months: months },
                    {
                        preserveScroll: true,
                        onStart: () => setSaving(true),
                        onFinish: () => setSaving(false),
                    },
                );
            }}
        >
            <div className="flex flex-col gap-1.5">
                <Label htmlFor={id}>Umur pakai (tahun)</Label>
                <Input
                    id={id}
                    inputMode="decimal"
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    className="w-28"
                />
            </div>
            <Button
                type="submit"
                variant="outline"
                disabled={saving || !(months > 0)}
            >
                {saving ? 'Menyimpan…' : 'Simpan umur'}
            </Button>
        </form>
    );
}

/** Aset tetap: what each piece of peralatan cost, what is used up, and what is left. */
export default function FixedAssets({
    assets,
    business,
    story,
}: FixedAssetsProps) {
    const sum = (pick: (asset: FixedAsset) => number) =>
        assets.reduce((total, asset) => total + pick(asset), 0);
    const cost = sum((asset) => asset.cost);
    const accumulated = sum((asset) => asset.accumulated);
    const bookValue = sum((asset) => asset.bookValue);
    const addEquipment = (
        <Button asChild>
            <Link
                href={createTransaction({
                    query: {
                        jenis: TransactionType.Pembelian,
                        kategori: TransactionKind.Peralatan,
                    },
                })}
            >
                <Plus aria-hidden />
                Tambah peralatan
            </Link>
        </Button>
    );

    return (
        <>
            <Head title="Aset tetap" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PrintHeader
                    business={business.name}
                    title="Aset tetap"
                    scope={`per ${formatDate(new Date())}`}
                />
                <PageHeader
                    className="print:hidden"
                    title="Aset tetap"
                    description="Peralatan usaha, penyusutannya, dan nilai bukunya. Penyusutan dicatat otomatis tiap akhir bulan."
                    actions={
                        <div className="flex flex-wrap items-start gap-2">
                            <ReportToolbar />
                            {addEquipment}
                        </div>
                    }
                />

                <ReportLead story={story} />

                {assets.length === 0 ? (
                    <EmptyState
                        variant="page"
                        icon={Hammer}
                        title="Belum ada peralatan"
                        description="Peralatan muncul di sini setelah dicatat lewat Belanja, pilihan peralatan."
                        action={addEquipment}
                    />
                ) : (
                    <div className="grid grid-cols-1 items-start gap-4 md:gap-6 lg:grid-cols-12">
                        <SectionCard
                            title="Daftar peralatan"
                            className="lg:col-span-8"
                        >
                            <ul className="flex flex-col divide-y">
                                {assets.map((asset) => {
                                    const used = usedPercent(
                                        asset.accumulated,
                                        asset.cost,
                                    );

                                    return (
                                        <li
                                            key={asset.id}
                                            className="flex break-inside-avoid flex-col gap-2 py-4 first:pt-0 last:pb-0"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="font-medium">
                                                        {asset.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Dibeli{' '}
                                                        {formatDate(
                                                            `${asset.acquiredOn}T12:00:00`,
                                                        )}
                                                        {asset.lifeMonths !==
                                                            null &&
                                                            ` · umur ${years(asset.lifeMonths)}`}
                                                    </p>
                                                </div>
                                                <Money
                                                    value={asset.bookValue}
                                                    size="lg"
                                                />
                                            </div>
                                            <div
                                                role="img"
                                                aria-label={`${Math.round(used)} persen sudah disusutkan`}
                                                className="h-2 overflow-hidden rounded-full bg-secondary"
                                            >
                                                <div
                                                    className="h-full rounded-full bg-chart-1"
                                                    style={{
                                                        width: `${used}%`,
                                                    }}
                                                />
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                Harga {formatRupiah(asset.cost)}
                                                , sudah disusutkan{' '}
                                                {formatRupiah(
                                                    asset.accumulated,
                                                )}
                                            </p>
                                            {asset.lifeMonths === null && (
                                                <div>
                                                    <LabelBadge
                                                        icon={TriangleAlert}
                                                        label="Umur belum diisi"
                                                        tone="warning"
                                                    />
                                                </div>
                                            )}
                                            {!asset.locked && (
                                                <LifeField asset={asset} />
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </SectionCard>

                        <SectionCard
                            title="Berapa nilai yang tersisa?"
                            className="lg:sticky lg:top-20 lg:col-span-4"
                        >
                            <p className="mb-3 text-sm">
                                {Math.round(usedPercent(accumulated, cost))}%
                                dari harga beli sudah disusutkan.
                            </p>
                            <dl className="flex flex-col text-sm">
                                <DetailRow label="Harga beli">
                                    <Money value={cost} size="sm" />
                                </DetailRow>
                                <DetailRow label="Sudah disusutkan">
                                    <Money value={accumulated} size="sm" />
                                </DetailRow>
                                <DetailRow
                                    label={
                                        <span className="inline-flex items-center gap-1">
                                            Nilai buku
                                            <TermHint
                                                term="bookValue"
                                                label="Nilai buku"
                                            />
                                        </span>
                                    }
                                >
                                    <Money value={bookValue} size="md" />
                                </DetailRow>
                            </dl>
                        </SectionCard>
                    </div>
                )}
            </div>
        </>
    );
}

FixedAssets.layout = {
    breadcrumbs: [
        { title: 'Laporan', href: reports.index() },
        { title: 'Aset tetap', href: index() },
    ],
};
