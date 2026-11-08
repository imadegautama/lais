import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Calculator,
    Check,
    CircleCheck,
    CircleX,
    LockKeyhole,
    ShieldCheck,
    Store,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { LabelBadge } from '@/components/app/badges';
import { DemoDataBadge } from '@/components/app/demo-data-badge';
import AppLogoIcon from '@/components/app-logo-icon';
import { Money } from '@/components/app/money';
import { LogoWordmark } from '@/components/logo-wordmark';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, register } from '@/routes';
import { show as showShop } from '@/routes/shop';
import type { DemoShop } from '@/types/domain';
import { businessCategoryLabels } from '@/types/enums';
import { ChatDemo } from './chat-demo';
import { businessPhotos, sampleRecords } from './examples';
import { delay, WordReveal } from './word-reveal';

const SOURCE_URL =
    'https://prnewswire.com/apac/news-releases/only-46-of-msmes-fully-separate-their-business-and-personal-finances-ocbc-simplifies-the-process-for-entrepreneurs-to-open-a-business-checking-account-100-digital-302240246.html';

const shell = 'mx-auto w-full max-w-[1280px] px-4 md:px-8';
const creditLink =
    'underline underline-offset-2 hover:text-foreground pointer-coarse:py-[15px]';
export const heading =
    'text-3xl font-semibold tracking-tight text-balance md:text-4xl';

function SignUpButton() {
    const { auth } = usePage().props;

    return (
        <Button asChild size="lg">
            <Link href={auth.user ? dashboard() : register()}>
                {auth.user ? 'Buka dashboard' : 'Daftar gratis'}
            </Link>
        </Button>
    );
}

export function Hero({ demoShop }: { demoShop: DemoShop | null }) {
    return (
        <section aria-labelledby="judul" className="px-2 pt-2 md:px-4 md:pt-4">
            <div className="mx-auto max-w-[1400px] rounded-3xl bg-landing-glow pt-24 pb-8 md:pt-28 md:pb-12">
                <div className="mx-auto grid max-w-[1280px] items-center gap-10 px-4 md:px-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,41rem)]">
                    <div className="landing-hero-copy max-w-xl">
                        <h1
                            id="judul"
                            className="landing-intro text-4xl leading-[1.1] font-semibold tracking-tight text-balance md:text-5xl xl:text-[3.5rem]"
                        >
                            <WordReveal text="Cukup chat, pembukuan usahamu tersusun sendiri." />
                        </h1>
                        <p
                            data-intro
                            style={delay(380)}
                            className="mt-4 text-base text-pretty md:text-lg"
                        >
                            Ketik penjualan seperti biasa. Lais mengurangi stok
                            sesuai resep, menghitung HPP, memisahkan uang
                            pribadi, dan menyusun laporannya.
                        </p>
                        <div
                            data-intro
                            style={delay(460)}
                            className="mt-6 flex flex-wrap gap-2"
                        >
                            <SignUpButton />
                            {demoShop && (
                                <Button asChild size="lg" variant="outline">
                                    <Link href={showShop(demoShop.slug)}>
                                        Lihat toko contoh
                                    </Link>
                                </Button>
                            )}
                        </div>
                        <ul
                            data-intro
                            style={delay(540)}
                            className="mt-5 flex flex-wrap gap-x-5 gap-y-1.5 text-sm"
                        >
                            {[
                                'Catat tanpa istilah akuntansi',
                                'Lewat Telegram dan web',
                            ].map((point) => (
                                <li
                                    key={point}
                                    className="flex items-center gap-1.5"
                                >
                                    <Check
                                        className="size-4 text-brand-strong"
                                        aria-hidden
                                    />
                                    {point}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <ChatDemo />
                </div>
            </div>
        </section>
    );
}

/**
 * Messages ride in from the left, pass the AI in the middle, and leave as
 * records (landing.css): two lanes moving together, each shown on one side.
 */
export function SampleMessages() {
    return (
        <section
            aria-labelledby="contoh-pesan"
            className={cn(
                shell,
                'flex flex-col gap-3 pt-6 md:flex-row md:items-center md:gap-5',
            )}
        >
            <h2
                id="contoh-pesan"
                className="shrink-0 text-sm font-medium text-muted-foreground"
            >
                Cukup tulis seperti ini
            </h2>
            <div
                role="region"
                aria-label="Contoh pesan yang berubah menjadi catatan. Sorot, sentuh, atau fokus untuk berhenti."
                tabIndex={0}
                // A tap focuses it, so touch can pause it too (landing.css).
                onPointerDown={(event) => event.currentTarget.focus()}
                className="landing-conveyor w-full min-w-0 rounded-2xl outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 motion-reduce:hidden md:flex-1"
            >
                <div
                    aria-hidden
                    className="flex justify-between px-4 pb-1 text-xs text-muted-foreground"
                >
                    <span>Chat masuk</span>
                    <span>Tercatat</span>
                </div>
                <div className="landing-conveyor-belt relative h-20">
                    <Lane face="message" className="landing-conveyor-in" />
                    <Lane face="record" className="landing-conveyor-out" />
                    <span
                        aria-hidden
                        className="absolute inset-y-2 left-1/2 w-px -translate-x-1/2 bg-border"
                    />
                    <span
                        aria-hidden
                        className="absolute top-1/2 left-1/2 z-10 flex -translate-x-1/2 -translate-y-1/2 items-center gap-1.5 rounded-full border bg-card px-2.5 py-1 text-xs font-semibold shadow-tile"
                    >
                        <AppLogoIcon className="h-3.5" />
                        AI
                    </span>
                </div>
            </div>
            <ul className="sr-only motion-reduce:not-sr-only motion-reduce:grid motion-reduce:gap-2 motion-reduce:md:flex-1 motion-reduce:lg:grid-cols-2">
                {sampleRecords.map((sample) => (
                    <li
                        key={sample.message}
                        className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm"
                    >
                        <span className="rounded-2xl rounded-br-md bg-brand-soft px-3 py-1">
                            {sample.message}
                        </span>
                        <ArrowRight
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden
                        />
                        <span className="sr-only">dicatat sebagai</span>
                        <span className="font-medium">{sample.record}</span>
                        <Money
                            value={sample.amount}
                            direction={sample.direction}
                            size="sm"
                        />
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * One lane of the conveyor, the list twice so the loop closes. Every card is
 * the same width in both lanes, so a message and its record meet at the
 * middle line.
 */
function Lane({
    face,
    className,
}: {
    face: 'message' | 'record';
    className: string;
}) {
    return (
        <div
            aria-hidden
            className={cn(
                'absolute inset-0 flex items-center overflow-hidden',
                className,
            )}
        >
            <div className="landing-conveyor-track flex w-max">
                {[...sampleRecords, ...sampleRecords].map((sample, index) => (
                    <div
                        key={`${sample.message}-${index}`}
                        className="w-52 shrink-0 pr-3 md:w-64"
                    >
                        {face === 'message' ? (
                            <p className="flex h-16 items-center rounded-2xl rounded-br-md bg-brand-soft px-3 text-sm">
                                <span className="line-clamp-2">
                                    {sample.message}
                                </span>
                            </p>
                        ) : (
                            <div className="flex h-16 items-center gap-2.5 rounded-2xl border bg-card px-3 shadow-tile">
                                <CircleCheck
                                    className="size-5 shrink-0 text-success"
                                    aria-hidden
                                />
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-semibold">
                                        {sample.record}
                                    </span>
                                    <span className="flex items-baseline gap-1.5 text-xs text-muted-foreground">
                                        <Money
                                            value={sample.amount}
                                            direction={sample.direction}
                                            size="sm"
                                            className="text-xs"
                                        />
                                        <span className="truncate">
                                            {sample.detail}
                                        </span>
                                    </span>
                                </span>
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

const consequences = [
    {
        title: 'Uang tercampur',
        text: 'Belanja bahan memakai uang dapur, lalu laba jadi tidak jelas.',
    },
    {
        title: 'Stok tidak terpantau',
        text: 'Bahan habis baru ketahuan saat pesanan sudah masuk.',
    },
    {
        title: 'Laba cuma perkiraan',
        text: 'Kas naik belum tentu untung, kas turun belum tentu rugi.',
    },
];

export function Problem() {
    return (
        <section
            aria-labelledby="masalah"
            className={cn(shell, 'py-12 md:py-16')}
        >
            <div className="grid gap-8 lg:grid-cols-12 lg:items-center">
                <div className="lg:col-span-5">
                    <h2
                        id="masalah"
                        data-reveal="words"
                        className="text-2xl font-semibold tracking-tight text-balance md:text-3xl"
                    >
                        <WordReveal text="Baru 46% UMKM yang sepenuhnya memisahkan uang usaha dan uang pribadi." />
                    </h2>
                    <p
                        data-reveal="up"
                        style={delay(250)}
                        className="mt-2 text-sm text-muted-foreground"
                    >
                        Survei{' '}
                        <a
                            href={SOURCE_URL}
                            target="_blank"
                            rel="noreferrer"
                            className="underline underline-offset-2 hover:text-foreground pointer-coarse:py-[14px]"
                        >
                            OCBC Business Fitness Index 2024
                        </a>
                        , 620 responden di kota besar.
                    </p>
                </div>
                <ul className="grid gap-6 sm:grid-cols-3 lg:col-span-7">
                    {consequences.map(({ title, text }, index) => (
                        <li
                            key={title}
                            data-reveal="up"
                            style={delay(index * 100)}
                        >
                            <p className="font-semibold">{title}</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {text}
                            </p>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

function Door({
    order,
    title,
    text,
    children,
}: {
    order: number;
    title: string;
    text: string;
    children: ReactNode;
}) {
    return (
        <li
            data-reveal="up"
            style={delay(order * 110)}
            className="flex flex-col gap-4 rounded-2xl border bg-card p-4 shadow-card"
        >
            <div className="flex min-h-32 flex-col justify-center gap-2 rounded-xl bg-surface-warm p-4 md:min-h-44">
                {children}
            </div>
            <div className="px-1 pb-1">
                <h3 className="text-lg font-semibold">{title}</h3>
                <p className="mt-1 text-sm text-muted-foreground">{text}</p>
            </div>
        </li>
    );
}

function MiniRow({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <p className="flex items-center gap-2 rounded-lg border bg-card px-3 py-2 text-sm">
            <Icon className="size-4 shrink-0 text-brand-strong" aria-hidden />
            {children}
        </p>
    );
}

export function HowItWorks() {
    return (
        <section
            id="cara-kerja"
            aria-labelledby="cara-kerja-judul"
            className={cn(shell, 'scroll-mt-6 py-12 md:py-16')}
        >
            <div className="mx-auto max-w-2xl text-center">
                <h2
                    id="cara-kerja-judul"
                    data-reveal="words"
                    className={heading}
                >
                    <WordReveal text="Satu pembukuan, tiga pintu masuk" />
                </h2>
                <p
                    data-reveal="up"
                    style={delay(200)}
                    className="mt-3 text-muted-foreground"
                >
                    Dari pintu mana pun catatan masuk, angkanya berakhir di buku
                    yang sama.
                </p>
            </div>
            <ul className="mt-10 grid gap-4 md:grid-cols-3 md:gap-6">
                <Door
                    order={0}
                    title="Catat lewat chat"
                    text="Ketik di Telegram atau Tanya AI, atau kirim foto nota. Lais membuat draf, kamu yang menyetujui."
                >
                    <p className="max-w-[85%] self-end rounded-2xl rounded-br-md bg-brand-soft px-3 py-2 text-sm">
                        jual 2 es teh tunai
                    </p>
                    <p className="max-w-[85%] self-start rounded-2xl rounded-bl-md border bg-card px-3 py-2 text-sm">
                        Penjualan tersimpan.
                    </p>
                </Door>
                <Door
                    order={1}
                    title="Kelola di web"
                    text="Dashboard, stok, kas, utang-piutang, dan laporan ada di satu tempat, di HP maupun laptop."
                >
                    <MiniRow icon={TriangleAlert}>Stok telur menipis</MiniRow>
                    <MiniRow icon={Calculator}>Laba bulan ini naik</MiniRow>
                </Door>
                <Door
                    order={2}
                    title="Terima pesanan dari toko"
                    text="Pelanggan memesan lewat link toko tanpa akun. Saat pesanan selesai, penjualan dan stok ikut tercatat."
                >
                    <MiniRow icon={Store}>Pesanan baru masuk</MiniRow>
                    <MiniRow icon={CircleCheck}>Selesai dan tercatat</MiniRow>
                </Door>
            </ul>
        </section>
    );
}

export function Audience() {
    return (
        <section
            aria-labelledby="untuk-siapa"
            className={cn(shell, 'py-12 md:py-16')}
        >
            <h2
                id="untuk-siapa"
                data-reveal="words"
                className={cn(heading, 'text-center')}
            >
                <WordReveal text="Untuk usaha yang punya stok" />
            </h2>
            <ul className="mt-8 grid grid-cols-2 gap-x-3 gap-y-6 md:gap-x-5 lg:grid-cols-4">
                {businessPhotos.map((photo, index) => (
                    <li
                        key={photo.category}
                        data-reveal="up"
                        style={delay(index * 90)}
                        className="min-w-0"
                    >
                        <div className="aspect-[4/5] overflow-hidden rounded-2xl bg-secondary">
                            <img
                                data-reveal="zoom"
                                style={delay(index * 90)}
                                src={`/images/landing/${photo.file}-800.webp`}
                                srcSet={`/images/landing/${photo.file}-480.webp 480w, /images/landing/${photo.file}-800.webp 800w`}
                                sizes="(min-width: 1024px) 290px, 46vw"
                                width={800}
                                height={1000}
                                loading="lazy"
                                alt={photo.alt}
                                className="landing-photo size-full object-cover"
                            />
                        </div>
                        <p className="mt-3 font-semibold">
                            {businessCategoryLabels[photo.category]}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {photo.text}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Foto:{' '}
                            <a
                                href={photo.page}
                                target="_blank"
                                rel="noreferrer"
                                className={creditLink}
                            >
                                {photo.author}
                            </a>
                            ,{' '}
                            <a
                                href={photo.licenseUrl}
                                target="_blank"
                                rel="noreferrer"
                                className={creditLink}
                            >
                                {photo.license}
                            </a>
                            , dipotong
                        </p>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Ledger({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="landing-drift rounded-xl border bg-card p-4 shadow-card">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-semibold">{title}</p>
                <DemoDataBadge />
            </div>
            <dl className="mt-3 flex flex-col divide-y text-sm">{children}</dl>
        </div>
    );
}

function LedgerRow({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center justify-between gap-3 py-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

function Note({ children }: { children: ReactNode }) {
    return (
        <div className="landing-drift-more">
            <p
                data-reveal="pop"
                style={delay(350)}
                className="relative z-10 mt-3 flex w-fit items-center gap-2 rounded-xl border bg-card px-3 py-2 text-sm font-medium shadow-toast md:-mt-3 md:-mr-10 md:ml-auto"
            >
                <BadgeCheck
                    className="size-4 shrink-0 text-success"
                    aria-hidden
                />
                {children}
            </p>
        </div>
    );
}

export function SeparateMoney() {
    return (
        <section
            aria-labelledby="uang-terpisah"
            className={cn(
                shell,
                'grid items-center gap-8 py-12 md:grid-cols-2 md:gap-14 md:py-16',
            )}
        >
            <div data-reveal="left">
                <h2 id="uang-terpisah" className={heading}>
                    Uang usaha dan uang pribadi punya tempatnya sendiri.
                </h2>
                <p className="mt-4 text-muted-foreground">
                    Ambil uang untuk rumah, kas turun tetapi laba tetap. Belanja
                    usaha memakai uang pribadi, tercatat sebagai setoran modal.
                    Kamu selalu tahu berapa uang yang benar-benar milik usaha.
                </p>
            </div>
            <div
                data-reveal="right"
                className="rounded-3xl bg-landing-glow p-4 md:p-8"
            >
                <Ledger title="Ambil 200rb buat rumah">
                    <LedgerRow label="Kas tunai">
                        <Money value={200_000} direction="out" size="sm" />
                    </LedgerRow>
                    <LedgerRow label="Diambil untuk pribadi">
                        <Money value={200_000} size="sm" />
                    </LedgerRow>
                    <LedgerRow label="Laba">
                        <span className="font-medium">Tetap</span>
                    </LedgerRow>
                </Ledger>
                <Note>Uang pribadi bukan biaya usaha</Note>
            </div>
        </section>
    );
}

export function Reports() {
    return (
        <section
            aria-labelledby="laporan"
            className={cn(
                shell,
                'grid items-center gap-8 py-12 md:grid-cols-2 md:gap-14 md:py-16',
            )}
        >
            <div
                data-reveal="left"
                className="rounded-3xl bg-landing-glow p-4 md:order-first md:p-8"
            >
                <Ledger title="Laba rugi bulan ini">
                    <LedgerRow label="Penjualan">
                        <Money value={49_000} size="sm" />
                    </LedgerRow>
                    <LedgerRow label="HPP">
                        <Money value={24_100} direction="out" size="sm" />
                    </LedgerRow>
                    <LedgerRow label="Laba bersih">
                        <Money value={24_900} size="sm" />
                    </LedgerRow>
                </Ledger>
                <Note>Selisih laba dan kas terjelaskan</Note>
            </div>
            <div data-reveal="right" className="order-first md:order-none">
                <h2 id="laporan" className={heading}>
                    Laporan tersusun sendiri, mengacu pada SAK EMKM.
                </h2>
                <p className="mt-4 text-muted-foreground">
                    Laba rugi, neraca, perubahan modal, dan catatan atas laporan
                    keuangan dihitung langsung dari jurnal. Buku besar, jurnal
                    umum, dan neraca saldo juga tersedia, dan semuanya bisa
                    dicetak.
                </p>
            </div>
        </section>
    );
}

const safeguards: Array<{ icon: LucideIcon; title: string; text: string }> = [
    {
        icon: ShieldCheck,
        title: 'Tidak tersimpan tanpa konfirmasi',
        text: 'AI hanya membuat draf. Catatan masuk ke buku setelah kamu setuju.',
    },
    {
        icon: Calculator,
        title: 'Angka dari sistem, bukan dari AI',
        text: 'Harga, HPP, dan saldo dihitung dari data usahamu. Jawaban dengan angka di luar data itu ditolak.',
    },
    {
        icon: LockKeyhole,
        title: 'Data tiap usaha terpisah',
        text: 'Setiap usaha hanya bisa membuka datanya sendiri.',
    },
];

export function Trust() {
    return (
        <section
            aria-labelledby="dipercaya"
            className="px-2 py-8 md:px-4 md:py-10"
        >
            <div className="mx-auto max-w-[1400px] rounded-3xl bg-landing-glow py-10 md:py-14">
                <div
                    className={cn(
                        shell,
                        'grid items-center gap-8 lg:grid-cols-2 lg:gap-14',
                    )}
                >
                    <div>
                        <h2
                            id="dipercaya"
                            data-reveal="words"
                            className={heading}
                        >
                            <WordReveal text="AI membantu mencatat, keputusannya tetap di kamu." />
                        </h2>
                        <p
                            data-reveal="up"
                            style={delay(250)}
                            className="mt-4 mb-6"
                        >
                            Lais mengerti bahasa sehari-hari, tetapi tidak
                            diberi cara untuk menyimpan sendiri atau mengarang
                            angka.
                        </p>
                        <SignUpButton />
                    </div>
                    <ul className="flex flex-col gap-3">
                        {safeguards.map(
                            ({ icon: Icon, title, text }, index) => (
                                <li
                                    key={title}
                                    data-reveal="right"
                                    style={delay(index * 120)}
                                    className="flex items-start gap-3 rounded-2xl border bg-card p-4 shadow-card"
                                >
                                    <span
                                        aria-hidden
                                        className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand-strong"
                                    >
                                        <Icon className="size-[18px]" />
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block font-semibold">
                                            {title}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            {text}
                                        </span>
                                    </span>
                                </li>
                            ),
                        )}
                    </ul>
                </div>
            </div>
        </section>
    );
}

const orderSteps = [
    'Pelanggan memilih produk, lalu mengisi nama dan nomor WA.',
    'Kamu menerima pesanannya, pelanggan melihat statusnya di nota.',
    'Saat selesai, stok, kas, dan laba tercatat sendiri.',
];

export function ExampleShop({ shop }: { shop: DemoShop }) {
    return (
        <section
            id="toko-contoh"
            aria-labelledby="toko-contoh-judul"
            className={cn(shell, 'scroll-mt-6 py-12 md:py-16')}
        >
            <div className="mx-auto max-w-2xl text-center">
                <h2
                    id="toko-contoh-judul"
                    data-reveal="words"
                    className={heading}
                >
                    <WordReveal text="Setiap usaha dapat toko sendiri" />
                </h2>
                <p
                    data-reveal="up"
                    style={delay(200)}
                    className="mt-3 text-muted-foreground"
                >
                    Coba jadi pelanggan di toko contoh dan lihat alurnya dari
                    awal.
                </p>
            </div>
            <div
                data-reveal="up"
                className="mt-10 grid gap-6 rounded-3xl border bg-card p-4 shadow-card md:p-6 lg:grid-cols-12 lg:gap-10"
            >
                <div className="min-w-0 lg:col-span-7">
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <span
                            aria-hidden
                            className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand-strong"
                        >
                            <Store className="size-[18px]" />
                        </span>
                        <p className="text-lg font-semibold">{shop.name}</p>
                        <LabelBadge
                            icon={shop.open ? CircleCheck : CircleX}
                            label={shop.open ? 'Buka' : 'Tutup'}
                            tone={shop.open ? 'success' : 'danger'}
                        />
                        <DemoDataBadge />
                    </div>
                    <ul className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {shop.items.map((item, index) => (
                            <li
                                key={item.id}
                                data-reveal="pop"
                                style={delay(200 + index * 90)}
                                className="min-w-0"
                            >
                                {item.photoUrl ? (
                                    <img
                                        src={item.photoUrl}
                                        alt=""
                                        width={200}
                                        height={200}
                                        loading="lazy"
                                        className="aspect-square w-full rounded-lg object-cover"
                                    />
                                ) : (
                                    <span
                                        aria-hidden
                                        className="flex aspect-square w-full items-center justify-center rounded-lg bg-secondary text-2xl font-semibold text-muted-foreground"
                                    >
                                        {item.name.charAt(0)}
                                    </span>
                                )}
                                <p className="mt-2 truncate text-sm">
                                    {item.name}
                                </p>
                                <Money value={item.price} size="sm" />
                            </li>
                        ))}
                    </ul>
                </div>
                <div className="flex flex-col justify-center lg:col-span-5">
                    <ol className="flex flex-col gap-3">
                        {orderSteps.map((step, index) => (
                            <li key={step} className="flex items-start gap-3">
                                <span
                                    aria-hidden
                                    className="num flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-semibold"
                                >
                                    {index + 1}
                                </span>
                                <span className="text-sm">{step}</span>
                            </li>
                        ))}
                    </ol>
                    <Button asChild size="lg" className="mt-5 w-fit">
                        <Link href={showShop(shop.slug)}>Coba pesan</Link>
                    </Button>
                </div>
            </div>
        </section>
    );
}

export function Closing() {
    return (
        <section
            aria-labelledby="penutup"
            className="px-2 pt-4 pb-2 md:px-4 md:pb-4"
        >
            <div className="mx-auto flex max-w-[1400px] flex-col items-center gap-5 rounded-3xl bg-landing-glow px-4 py-14 text-center md:py-20">
                <div data-reveal="pop">
                    <LogoWordmark className="h-8" />
                </div>
                <h2 id="penutup" data-reveal="words" className={heading}>
                    <WordReveal text="Catat penjualan pertamamu hari ini." />
                </h2>
                <div data-reveal="up" style={delay(300)}>
                    <SignUpButton />
                </div>
            </div>
        </section>
    );
}
