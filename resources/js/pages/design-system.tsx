import { Head } from '@inertiajs/react';
import {
    CircleCheck,
    CircleX,
    Coins,
    Plus,
    Search,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { ActionButton, SoonTag } from '@/components/app/action-button';
import {
    CategoryBadge,
    ItemKindBadge,
    MarginLabel,
    OrderStatusBadge,
    PaymentStatusBadge,
    StockStatusBadge,
    SourceLabel,
    transactionTypes,
} from '@/components/app/badges';
import { DemoDataBadge } from '@/components/app/demo-data-badge';
import { FormActionBar } from '@/components/app/form-action-bar';
import {
    ChipFilter,
    FilterBar,
    SearchFilter,
} from '@/components/app/filter-bar';
import {
    DeltaBadge,
    MetricCount,
    MetricFocus,
    MetricInline,
    MetricTile,
} from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { MoneyInput } from '@/components/app/money-input';
import { PageHeader } from '@/components/app/page-header';
import { PeriodPicker } from '@/components/app/period-picker';
import { QtyStepper } from '@/components/app/qty-stepper';
import { QuickRecordSheet } from '@/components/app/quick-record-sheet';
import { ReceiptCard } from '@/components/app/receipt-card';
import { SalesBarChart } from '@/components/app/sales-bar-chart';
import { SectionCard } from '@/components/app/section-card';
import { SearchSelect } from '@/components/app/search-select';
import { EmptyState, ErrorState, LoadingBlock } from '@/components/app/states';
import { TransactionTable } from '@/components/app/transaction-table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatRupiah } from '@/lib/format';
import type {
    OrderStatus,
    PaymentStatus,
    PeriodKey,
    TransactionRow,
    TransactionSource,
    TransactionType,
} from '@/types/domain';

type Props = {
    transactions: TransactionRow[];
    salesChart: Array<{ date: string; sales: number }>;
};

const sections = [
    ['warna', 'Warna'],
    ['tipografi', 'Tipografi'],
    ['bentuk', 'Bentuk'],
    ['tombol', 'Tombol'],
    ['form', 'Form'],
    ['badge', 'Badge'],
    ['angka', 'Angka'],
    ['kartu', 'Kartu'],
    ['grafik', 'Grafik'],
    ['tabel', 'Tabel'],
    ['state', 'State'],
    ['umpan-balik', 'Umpan balik'],
    ['motif', 'Motif nota'],
] as const;

// Ratios measured with the antislop contrast checker; see docs/DESIGN.md.
const swatches: Array<{
    token: string;
    hex: string;
    use: string;
    ratio: string;
}> = [
    {
        token: 'background',
        hex: '#FFFFFF',
        use: 'Latar halaman dan kartu',
        ratio: 'Teks tinta 17,5:1',
    },
    {
        token: 'foreground',
        hex: '#1C1917',
        use: 'Teks utama (tinta)',
        ratio: '17,5:1 di putih',
    },
    {
        token: 'muted-foreground',
        hex: '#6B645E',
        use: 'Teks sekunder',
        ratio: '5,82:1 di putih',
    },
    {
        token: 'secondary',
        hex: '#F5F3F0',
        use: 'Panel lembut, badge netral',
        ratio: 'Teks muted 5,25:1',
    },
    {
        token: 'primary',
        hex: '#C25205',
        use: 'Tombol utama, FAB, centang',
        ratio: 'Teks putih 4,66:1',
    },
    {
        token: 'primary-text',
        hex: '#C2410C',
        use: 'Teks dan link oranye',
        ratio: '5,18:1 di putih',
    },
    {
        token: 'accent',
        hex: '#FFF7ED',
        use: 'Menu aktif, pilihan aktif',
        ratio: 'Teks #9A3412 6,88:1',
    },
    {
        token: 'brand-strong',
        hex: '#EA580C',
        use: 'Seri grafik disorot, ring fokus, hover',
        ratio: '3,56:1 (non-teks)',
    },
    {
        token: 'wash',
        hex: '#FAE3BD',
        use: 'Atas gradien halaman (bg-page-wash), pilihan pemilik',
        ratio: 'Teks muted 4,65:1. Tanpa teks oranye',
    },
    {
        token: 'surface-warm',
        hex: '#FFFAF2',
        use: 'Kotak ringkasan sehat, border #F2E4D3',
        ratio: 'Teks muted 5,60:1',
    },
    {
        token: 'input',
        hex: '#DAD5CF',
        use: 'Border field form',
        ratio: '1,46:1 (pilihan pemilik)',
    },
    {
        token: 'success',
        hex: '#047857',
        use: 'Naik, aman, uang masuk',
        ratio: '5,21:1 di soft',
    },
    {
        token: 'warning',
        hex: '#B45309',
        use: 'Stok menipis, peringatan',
        ratio: '4,84:1 di soft',
    },
    {
        token: 'destructive',
        hex: '#B91C1C',
        use: 'Turun, gagal, batalkan',
        ratio: '5,91:1 di soft',
    },
    {
        token: 'chart-2',
        hex: '#958D85',
        use: 'Seri grafik lain',
        ratio: '3,27:1 (non-teks)',
    },
    {
        token: 'chart-3',
        hex: '#0D9488',
        use: 'Seri pembanding (jarang)',
        ratio: '3,74:1 (non-teks)',
    },
];

const typeScale: Array<{
    name: string;
    className: string;
    sample: string;
    spec: string;
}> = [
    {
        name: 'Angka fokus',
        className: 'text-[32px] font-bold tracking-tight',
        sample: 'Rp1.250.000',
        spec: '28–32 px, 700',
    },
    {
        name: 'Judul halaman',
        className: 'text-2xl font-semibold tracking-tight',
        sample: 'Selamat siang, Bu Rina',
        spec: '24 px (20 di HP), 600',
    },
    {
        name: 'Judul kartu',
        className: 'text-base font-semibold',
        sample: 'Perlu tindakan hari ini',
        spec: '16 px, 600',
    },
    {
        name: 'Isi',
        className: 'text-sm',
        sample: 'Pesanan A012 dari Sari menunggu konfirmasi',
        spec: '14 px, 400',
    },
    {
        name: 'Isi form & toko',
        className: 'text-base',
        sample: 'Nama pemesan',
        spec: '16 px (mencegah zoom iOS)',
    },
    {
        name: 'Keterangan',
        className: 'text-xs text-muted-foreground',
        sample: '11 transaksi · vs kemarin',
        spec: '12 px, 400',
    },
    {
        name: 'Label grup',
        className: 'text-xs font-semibold text-muted-foreground',
        sample: 'Operasional',
        spec: '12 px, 600, sentence case',
    },
];

function Section({
    id,
    title,
    intro,
    children,
}: {
    id: string;
    title: string;
    intro: ReactNode;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-title`}
            className="flex scroll-mt-20 flex-col gap-4"
        >
            <div>
                <h2 id={`${id}-title`} className="text-lg font-semibold">
                    {title}
                </h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {intro}
                </p>
            </div>
            {children}
        </section>
    );
}

function Panel({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={`rounded-xl border bg-card p-4 shadow-card md:p-5 ${className}`}
        >
            {children}
        </div>
    );
}

export default function DesignSystem({ transactions, salesChart }: Props) {
    const [money, setMoney] = useState<number | null>(45_000);
    const [qty, setQty] = useState(3);
    const [kg, setKg] = useState(1.5);
    const [period, setPeriod] = useState<PeriodKey>('hari_ini');
    const [delivery, setDelivery] = useState(false);
    const [types, setTypes] = useState<string[]>([]);
    const [unit, setUnit] = useState('porsi');
    const [material, setMaterial] = useState('');
    const [query, setQuery] = useState('');
    const [tableState, setTableState] = useState<
        'normal' | 'kosong' | 'memuat' | 'memuat ulang' | 'gagal'
    >('normal');

    const filtered = useMemo(
        () =>
            transactions.filter(
                (row) =>
                    (types.length === 0 || types.includes(row.type)) &&
                    row.description
                        .toLowerCase()
                        .includes(query.trim().toLowerCase()),
            ),
        [transactions, types, query],
    );

    return (
        <>
            <Head title="Design system" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Design system"
                    meta={<DemoDataBadge />}
                    description="Referensi visual untuk semua halaman. Hanya tersedia di lingkungan lokal. Aturan lengkapnya di docs/DESIGN.md."
                />

                <nav
                    aria-label="Isi halaman"
                    className="flex flex-wrap gap-1.5"
                >
                    {sections.map(([id, label]) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            className="rounded-md border bg-card px-2.5 py-1.5 text-xs font-medium hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:py-3"
                        >
                            {label}
                        </a>
                    ))}
                </nav>

                <Section
                    id="warna"
                    title="Warna"
                    intro="Netral hangat dan tinta sebagai dasar, oranye untuk satu aksen per area, teal hanya untuk seri pembanding di grafik. Warna status selalu didampingi ikon atau kata."
                >
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {swatches.map((swatch) => (
                            <div
                                key={swatch.token}
                                className="flex items-center gap-3 rounded-lg border bg-card p-3"
                            >
                                <span
                                    aria-hidden
                                    className="size-12 shrink-0 rounded-md border"
                                    style={{
                                        backgroundColor: `var(--${swatch.token})`,
                                    }}
                                />
                                <div className="min-w-0 text-sm">
                                    <p className="font-semibold">
                                        {swatch.token}{' '}
                                        <span className="num font-normal text-muted-foreground">
                                            {swatch.hex}
                                        </span>
                                    </p>
                                    <p className="text-muted-foreground">
                                        {swatch.use}
                                    </p>
                                    <p className="num text-xs text-muted-foreground">
                                        {swatch.ratio}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </Section>

                <Section
                    id="tipografi"
                    title="Tipografi"
                    intro="Plus Jakarta Sans (Tokotype, Jakarta). Angka memakai tabular-nums supaya rata di kolom, dengan prefix Rp sedikit lebih kecil."
                >
                    <Panel className="flex flex-col divide-y">
                        {typeScale.map((row) => (
                            <div
                                key={row.name}
                                className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 md:flex-row md:items-baseline md:gap-6"
                            >
                                <span className="w-40 shrink-0 text-xs font-semibold text-muted-foreground">
                                    {row.name}
                                    <span className="block font-normal">
                                        {row.spec}
                                    </span>
                                </span>
                                <span
                                    className={`num min-w-0 break-words ${row.className}`}
                                >
                                    {row.sample}
                                </span>
                            </div>
                        ))}
                    </Panel>
                </Section>

                <Section
                    id="bentuk"
                    title="Bentuk"
                    intro="Radius bertingkat: kartu 16 px, tombol dan input 12 px, badge 8 px. Tombol, kartu, dan kotak ringkasan memakai bahasa inner shadow yang sama: kilau putih di atas dan bayangan hangat tipis di bawah, makin pelan dari tombol ke kartu. Latar halaman memakai wash hangat di bawah header. Bayangan besar hanya untuk lapisan di atas halaman."
                >
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <Panel className="text-sm">
                            Kartu: rounded-xl, border, shadow-card (inner shadow
                            tipis + angkat hangat)
                        </Panel>
                        <div className="rounded-lg border border-input bg-card p-4 text-sm shadow-field">
                            Field: rounded-lg, border lembut, shadow-field
                        </div>
                        <div className="rounded-xl border bg-card p-4 text-sm shadow-lg">
                            Popover, dialog, sheet: shadow-lg
                        </div>
                    </div>
                </Section>

                <Section
                    id="tombol"
                    title="Tombol"
                    intro="Satu tombol primary per area. Oranye #C25205 dengan teks putih (4,66:1) dan inner shadow, di semua ukuran. Label menyebut tindakannya. Di layar sentuh semua tombol minimal 44 px."
                >
                    <Panel className="flex flex-col gap-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                onClick={() =>
                                    toast.success('Contoh: penjualan tersimpan')
                                }
                            >
                                <Plus aria-hidden />
                                Catat penjualan
                            </Button>
                            <Button variant="outline">Lihat detail</Button>
                            <Button variant="secondary">Simpan draf</Button>
                            <Button variant="ghost">Batal</Button>
                            <Button variant="destructive">
                                Batalkan transaksi
                            </Button>
                            <Button variant="link">Lihat semua</Button>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button size="sm">Kecil</Button>
                            <Button>Normal</Button>
                            <Button size="lg">Besar</Button>
                            <Button
                                size="icon"
                                variant="outline"
                                aria-label="Cari"
                            >
                                <Search aria-hidden />
                            </Button>
                            <Button disabled>Tidak aktif</Button>
                            <ActionButton
                                target={{ comingSoon: true }}
                                variant="outline"
                            >
                                Halaman belum ada
                            </ActionButton>
                        </div>
                    </Panel>
                </Section>

                <Section
                    id="form"
                    title="Form"
                    intro="Label selalu terlihat di atas field. Input uang mengerti cara pemilik menulis (15rb, 1,5jt) dan menunjukkan hasil bacaannya."
                >
                    <Panel className="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <MoneyInput
                            label="Harga jual"
                            value={money}
                            onChange={setMoney}
                        />
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="ds-name">Nama pemesan</Label>
                            <Input
                                id="ds-name"
                                placeholder="Nama Anda"
                                autoComplete="name"
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-medium">
                                Jumlah porsi
                            </span>
                            <QtyStepper
                                label="Nasi Goreng"
                                value={qty}
                                onChange={setQty}
                                min={1}
                            />
                        </div>
                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-medium">
                                Berat bahan
                            </span>
                            <QtyStepper
                                label="Beras"
                                value={kg}
                                onChange={setKg}
                                step={0.5}
                                unit="kg"
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="ds-method">Metode bayar</Label>
                            <Select defaultValue="tunai">
                                <SelectTrigger
                                    id="ds-method"
                                    className="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="tunai">Tunai</SelectItem>
                                    <SelectItem value="non_tunai">
                                        Non-tunai (QRIS/transfer)
                                    </SelectItem>
                                    <SelectItem value="kredit">
                                        Belum dibayar
                                    </SelectItem>
                                    <SelectItem value="pribadi">
                                        Uang pribadi
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-medium">Periode</span>
                            <PeriodPicker
                                periods={[
                                    { key: 'hari_ini', label: 'Hari ini' },
                                    { key: 'minggu_ini', label: 'Minggu ini' },
                                    { key: 'bulan_ini', label: 'Bulan ini' },
                                ]}
                                value={period}
                                onChange={setPeriod}
                            />
                        </div>
                        <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
                            <Label
                                htmlFor="ds-delivery"
                                className="flex flex-col items-start gap-0.5"
                            >
                                Diantar ke alamat
                                <span className="text-xs font-normal text-muted-foreground">
                                    Kalau mati, pesanan diambil sendiri.
                                </span>
                            </Label>
                            <Switch
                                id="ds-delivery"
                                checked={delivery}
                                onCheckedChange={setDelivery}
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="ds-note">Catatan</Label>
                            <Textarea
                                id="ds-note"
                                placeholder="Misalnya: pedas level 2"
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="ds-unit">
                                Satuan (SearchSelect, bisa ketik sendiri)
                            </Label>
                            <SearchSelect
                                id="ds-unit"
                                value={unit}
                                onChange={(next) => setUnit(next.toLowerCase())}
                                options={[
                                    ['pcs', 'Jumlah'],
                                    ['porsi', 'Jumlah'],
                                    ['kg', 'Berat'],
                                    ['liter', 'Volume'],
                                ].map(([value, group]) => ({
                                    value,
                                    label: value,
                                    group,
                                }))}
                                placeholder="Pilih satuan"
                                searchPlaceholder="Cari atau ketik satuan"
                                emptyText="Ketik satuan yang kamu pakai."
                                creatable={(text) =>
                                    `Pakai satuan "${text.toLowerCase()}"`
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="ds-material">
                                Bahan (SearchSelect berkelompok)
                            </Label>
                            <SearchSelect
                                id="ds-material"
                                value={material}
                                onChange={setMaterial}
                                options={[
                                    {
                                        value: 'beras',
                                        label: 'Beras',
                                        group: 'Bahan baku',
                                        description: 'Rp14.000/kg',
                                    },
                                    {
                                        value: 'telur',
                                        label: 'Telur',
                                        group: 'Bahan baku',
                                        description: 'Rp2.200/butir',
                                    },
                                    {
                                        value: 'kerupuk',
                                        label: 'Kerupuk',
                                        group: 'Produk biasa',
                                        description: 'Rp1.000/bungkus',
                                    },
                                ]}
                                placeholder="Pilih bahan"
                                searchPlaceholder="Cari bahan"
                                emptyText="Bahan tidak ditemukan."
                            />
                        </div>
                    </Panel>
                    <p className="mt-4 mb-2 text-sm text-muted-foreground">
                        FormActionBar: di halaman form bar ini menempel di bawah
                        layar, di atas bottom nav HP. Di sini tampil statis.
                    </p>
                    <div className="overflow-hidden rounded-xl border">
                        <FormActionBar className="static m-0 md:m-0">
                            <Button
                                onClick={() =>
                                    toast.success('Contoh: produk tersimpan')
                                }
                            >
                                Simpan produk
                            </Button>
                            <Button variant="outline">Batal</Button>
                        </FormActionBar>
                    </div>
                </Section>

                <Section
                    id="badge"
                    title="Badge"
                    intro="Badge hanya untuk status atau label nyata. Kategori transaksi dibedakan lewat ikon dan kata, dengan warna netral yang sama."
                >
                    <Panel className="flex flex-col gap-4">
                        <BadgeRow label="Kategori transaksi">
                            {(
                                Object.keys(
                                    transactionTypes,
                                ) as TransactionType[]
                            ).map((type) => (
                                <CategoryBadge key={type} type={type} />
                            ))}
                        </BadgeRow>
                        <BadgeRow label="Status pesanan">
                            {(
                                [
                                    'menunggu',
                                    'diproses',
                                    'siap',
                                    'selesai',
                                    'ditolak',
                                ] as OrderStatus[]
                            ).map((status) => (
                                <OrderStatusBadge
                                    key={status}
                                    status={status}
                                />
                            ))}
                        </BadgeRow>
                        <BadgeRow label="Status bayar">
                            {(['belum', 'dp', 'lunas'] as PaymentStatus[]).map(
                                (status) => (
                                    <PaymentStatusBadge
                                        key={status}
                                        status={status}
                                    />
                                ),
                            )}
                        </BadgeRow>
                        <BadgeRow label="Sumber catatan">
                            {(
                                [
                                    'wa',
                                    'web',
                                    'toko',
                                    'ocr',
                                ] as TransactionSource[]
                            ).map((source) => (
                                <SourceLabel key={source} source={source} />
                            ))}
                        </BadgeRow>
                        <BadgeRow label="Jenis produk">
                            {(['produk', 'komposisi', 'bahan'] as const).map(
                                (kind) => (
                                    <ItemKindBadge key={kind} kind={kind} />
                                ),
                            )}
                        </BadgeRow>
                        <BadgeRow label="Stok (hanya kalau bermasalah)">
                            <StockStatusBadge lowStock negativeStock={false} />
                            <StockStatusBadge lowStock={false} negativeStock />
                        </BadgeRow>
                        <BadgeRow label="Margin">
                            {[52, 22.5, 12, -4].map((percent) => (
                                <MarginLabel key={percent} percent={percent} />
                            ))}
                        </BadgeRow>
                        <BadgeRow label="Label sistem">
                            <DemoDataBadge />
                            <SoonTag />
                        </BadgeRow>
                    </Panel>
                </Section>

                <Section
                    id="angka"
                    title="Angka"
                    intro="Satu angka fokus per layar, angka lain lebih tenang. Delta hanya tampil jika ada periode pembanding yang nyata dan disebut namanya."
                >
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <Panel className="flex flex-col gap-4">
                            <MetricFocus
                                label="Laba bersih"
                                value={174_200}
                                delta={
                                    <DeltaBadge
                                        value={174_200}
                                        previous={137_000}
                                        comparisonLabel="vs kemarin di jam yang sama"
                                    />
                                }
                                hint="Penjualan dikurangi modal bahan yang terjual dan biaya operasional."
                            />
                            <div className="divide-y border-t pt-3">
                                <MetricInline
                                    label="Penjualan"
                                    value={<Money value={312_000} />}
                                    detail="11 transaksi"
                                    delta={
                                        <DeltaBadge
                                            value={312_000}
                                            previous={355_000}
                                            comparisonLabel="vs kemarin"
                                        />
                                    }
                                />
                                <MetricInline
                                    label="Diambil untuk pribadi"
                                    value={<Money value={150_000} />}
                                    delta={
                                        <DeltaBadge
                                            value={150_000}
                                            previous={150_000}
                                            comparisonLabel="vs kemarin"
                                            goodWhen="neutral"
                                        />
                                    }
                                />
                            </div>
                        </Panel>
                        <Panel className="flex flex-col gap-3 text-sm">
                            <p className="font-semibold">Nominal berarah</p>
                            <div className="flex flex-wrap items-baseline gap-4">
                                <Money value={45_000} direction="in" />
                                <Money value={520_000} direction="out" />
                                <Money value={12_253_000} />
                            </div>
                            <p className="text-muted-foreground">
                                Masuk berwarna hijau dengan tanda +, keluar
                                memakai tinta dengan tanda −. Saldo tanpa tanda.
                            </p>
                            <p className="font-semibold">
                                Delta tanpa pembanding
                            </p>
                            <p className="text-muted-foreground">
                                Bulan pertama belum punya data bulan lalu, jadi
                                delta tidak ditampilkan:
                                <span className="ml-2 inline-block rounded border border-dashed px-2 py-0.5 text-xs">
                                    <DeltaBadge
                                        value={312_000}
                                        previous={null}
                                        comparisonLabel="vs bulan lalu"
                                    />
                                    (kosong)
                                </span>
                            </p>
                        </Panel>
                        <Panel className="flex flex-col gap-3 md:col-span-2">
                            <p className="text-sm font-semibold">
                                Kotak ringkasan
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Untuk baris ringkasan di dalam kartu fokus.
                                Kuning dan merah hanya jika ada masalah nyata.
                                Angka sehat tetap abu dengan tanda centang.
                            </p>
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                <MetricTile
                                    label="Nilai persediaan"
                                    icon={Coins}
                                    value={<Money value={684_900} size="xl" />}
                                    detail="9 bahan baku dan produk biasa"
                                    className="col-span-2 md:col-span-1"
                                />
                                <MetricTile
                                    label="Stok menipis"
                                    icon={TriangleAlert}
                                    tone="warning"
                                    value={
                                        <MetricCount value={2} unit="item" />
                                    }
                                    detail="Beras dan Telur"
                                />
                                <MetricTile
                                    label="Stok minus"
                                    icon={CircleX}
                                    tone="danger"
                                    value={
                                        <MetricCount value={1} unit="item" />
                                    }
                                    detail="Gula pasir. Catat belanja atau koreksi stok."
                                />
                                <MetricTile
                                    label="Stok minus"
                                    icon={CircleCheck}
                                    value={
                                        <MetricCount value={0} unit="item" />
                                    }
                                    detail="Tidak ada stok minus."
                                />
                            </div>
                        </Panel>
                    </div>
                </Section>

                <Section
                    id="kartu"
                    title="Kartu"
                    intro="Judul kartu menyebut tugas atau pertanyaannya. Varian fokus (latar oranye muda) hanya untuk satu kartu per layar."
                >
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <SectionCard
                            tone="focus"
                            title="Perlu tindakan hari ini"
                            description="Varian fokus: titik perhatian layar"
                        >
                            <p className="text-sm text-muted-foreground">
                                Isi kartu.
                            </p>
                        </SectionCard>
                        <SectionCard
                            title="Bahan hampir habis"
                            description="Varian biasa"
                            action={
                                <Button variant="outline" size="sm">
                                    Lihat
                                </Button>
                            }
                        >
                            <p className="text-sm text-muted-foreground">
                                Isi kartu dengan aksi di header.
                            </p>
                        </SectionCard>
                    </div>
                </Section>

                <Section
                    id="grafik"
                    title="Grafik"
                    intro="Judul grafik berupa pertanyaan, jawabannya ditulis sebagai kalimat di atas grafik. Satu seri disorot oranye, sisanya abu."
                >
                    <SectionCard
                        title="Hari apa paling ramai?"
                        description="Penjualan per hari, 30 hari terakhir"
                    >
                        <SalesBarChart data={salesChart} />
                    </SectionCard>
                </Section>

                <Section
                    id="tabel"
                    title="Tabel"
                    intro="Di desktop berbentuk tabel dengan kolom yang bisa diurutkan. Di bawah 768 px berubah jadi daftar kartu yang dikelompokkan per tanggal. Contoh ini memakai mode client (filter di browser). Halaman asli seperti Produk & stok memakai mode server: useTableQuery + TableQuery, 20 baris per halaman."
                >
                    <Panel className="flex flex-col gap-4">
                        <FilterBar>
                            <ChipFilter
                                label="Saring jenis transaksi"
                                options={(
                                    Object.keys(
                                        transactionTypes,
                                    ) as TransactionType[]
                                ).map((type) => ({
                                    value: type,
                                    label: transactionTypes[type].label,
                                    icon: transactionTypes[type].icon,
                                }))}
                                value={types}
                                onChange={setTypes}
                            />
                            <SearchFilter
                                placeholder="Cari keterangan"
                                value={query}
                                onChange={setQuery}
                            />
                        </FilterBar>
                        <TransactionTable
                            rows={filtered}
                            pageSize={10}
                            empty={
                                <EmptyState
                                    title="Tidak ada transaksi yang cocok"
                                    description="Hapus sebagian filter atau ubah kata pencarian."
                                    action={
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setTypes([]);
                                                setQuery('');
                                            }}
                                        >
                                            Hapus filter
                                        </Button>
                                    }
                                />
                            }
                        />
                    </Panel>
                </Section>

                <Section
                    id="state"
                    title="State"
                    intro="Setiap tampilan data punya tiga keadaan selain normal. Kosong menyebut sebab dan satu tindakan; memuat menyebut apa yang dimuat; gagal menyebut apa yang gagal dan cara melanjutkan."
                >
                    <Panel className="flex flex-col gap-4">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={tableState}
                            onValueChange={(value) =>
                                value &&
                                setTableState(value as typeof tableState)
                            }
                            aria-label="Pilih keadaan tabel"
                            className="w-fit bg-card"
                        >
                            {(
                                [
                                    'normal',
                                    'kosong',
                                    'memuat',
                                    'memuat ulang',
                                    'gagal',
                                ] as const
                            ).map((state) => (
                                <ToggleGroupItem
                                    key={state}
                                    value={state}
                                    className="px-3 capitalize data-[state=on]:bg-accent data-[state=on]:text-accent-foreground"
                                >
                                    {state}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <TransactionTable
                            rows={
                                tableState === 'kosong'
                                    ? []
                                    : transactions.slice(0, 5)
                            }
                            isLoading={tableState === 'memuat'}
                            server={
                                tableState === 'memuat ulang'
                                    ? {
                                          meta: {
                                              page: 1,
                                              perPage: 5,
                                              total: 40,
                                              lastPage: 8,
                                          },
                                          sorting: [
                                              { id: 'waktu', desc: true },
                                          ],
                                          onSortingChange: () =>
                                              toast(
                                                  'Di halaman asli, urutan baru diambil dari server.',
                                              ),
                                          pagination: {
                                              pageIndex: 0,
                                              pageSize: 5,
                                          },
                                          onPaginationChange: () =>
                                              toast(
                                                  'Di halaman asli, halaman berikutnya diambil dari server.',
                                              ),
                                          isLoading: true,
                                          failed: false,
                                          retry: () => setTableState('normal'),
                                      }
                                    : undefined
                            }
                            error={
                                tableState === 'gagal' && (
                                    <ErrorState
                                        title="Transaksi gagal dimuat"
                                        description="Server tidak menjawab. Data Anda aman; coba muat ulang."
                                        onRetry={() => setTableState('normal')}
                                    />
                                )
                            }
                        />
                        <div className="grid grid-cols-1 gap-3 border-t pt-4 md:grid-cols-3">
                            <EmptyState
                                title="Belum ada pesanan"
                                description="Bagikan link toko supaya pelanggan bisa memesan."
                            />
                            <LoadingBlock
                                label="Memuat laporan laba rugi"
                                rows={3}
                            />
                            <ErrorState
                                title="PDF gagal dibuat"
                                description="Coba lagi beberapa saat."
                                onRetry={() =>
                                    toast('Mencoba membuat PDF lagi')
                                }
                            />
                        </div>
                    </Panel>
                </Section>

                <Section
                    id="umpan-balik"
                    title="Umpan balik"
                    intro="Toast untuk hasil aksi yang singkat: kartu putih dengan chip ikon sesuai status (warna badge yang sama), muncul di atas bottom nav di HP. Dialog atau drawer untuk pilihan; drawer dipakai di HP supaya terjangkau jempol. Semua tertutup dengan Esc."
                >
                    <Panel className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                toast.success('Penjualan tersimpan', {
                                    description: `3 Nasi Goreng · ${formatRupiah(45_000)}`,
                                })
                            }
                        >
                            Toast berhasil
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                toast.error('Kecap manis gagal diarsipkan', {
                                    description:
                                        'Stoknya masih ada. Koreksi stoknya ke 0 dulu.',
                                })
                            }
                        >
                            Toast gagal
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                toast.warning('Stok telur menipis', {
                                    description:
                                        'Sisa 8 butir, batas 30 butir.',
                                })
                            }
                        >
                            Toast peringatan
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                toast.info('Stok Gula pasir sudah sesuai.')
                            }
                        >
                            Toast info
                        </Button>
                        <QuickRecordSheet
                            trigger={
                                <Button variant="outline">
                                    Buka pilihan Catat
                                </Button>
                            }
                        />
                    </Panel>
                </Section>

                <Section
                    id="motif"
                    title="Motif nota (usulan)"
                    intro="Tepi bergerigi seperti struk, khusus untuk permukaan yang berfungsi sebagai bukti transaksi: nota pelanggan, pratinjau konfirmasi, dan pratinjau pesan WA. Bisa dicoret jika tidak cocok."
                >
                    <div className="max-w-sm">
                        <ReceiptCard>
                            <p className="text-xs font-semibold text-muted-foreground">
                                Cek dulu ya
                            </p>
                            <p className="mt-1 text-sm font-semibold">
                                Penjualan · QRIS
                            </p>
                            <ul className="num mt-3 flex flex-col gap-1.5 text-sm">
                                <li className="flex justify-between gap-3">
                                    <span>3 × Nasi Goreng</span>
                                    <span>{formatRupiah(45_000)}</span>
                                </li>
                                <li className="flex justify-between gap-3">
                                    <span>2 × Es Teh</span>
                                    <span>{formatRupiah(10_000)}</span>
                                </li>
                            </ul>
                            <div className="mt-3 flex justify-between border-t border-dashed pt-3 text-sm font-semibold">
                                <span>Total</span>
                                <Money value={55_000} size="sm" />
                            </div>
                            <p className="mt-3 text-xs text-muted-foreground">
                                Balas 1 untuk simpan, 2 untuk batal.
                            </p>
                        </ReceiptCard>
                    </div>
                </Section>
            </div>
        </>
    );
}

function BadgeRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:gap-4">
            <span className="w-40 shrink-0 text-xs font-semibold text-muted-foreground">
                {label}
            </span>
            <div className="flex flex-wrap items-center gap-2">{children}</div>
        </div>
    );
}
