import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Calculator,
    Check,
    CircleCheck,
    CircleX,
    Clock,
    LockKeyhole,
    ShieldCheck,
    Smartphone,
    Store,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import { useRef, useState } from 'react';
import { LabelBadge, MarginLabel } from '@/components/app/badges';
import { DemoDataBadge } from '@/components/app/demo-data-badge';
import { Money } from '@/components/app/money';
import { LogoWordmark } from '@/components/logo-wordmark';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard, register } from '@/routes';
import { show as showShop } from '@/routes/shop';
import type { DemoShop } from '@/types/domain';
import { businessCategoryLabels } from '@/types/enums';
import { ChatDemo } from './chat-demo';
import { businessPhotos, problems, surveyFacts } from './examples';
import { useActiveStep } from './use-active-step';
import { delay, WordReveal } from './word-reveal';
import { isMouse } from './pointer';
import { installApp, useInstallWay } from '@/components/app/install-app';
import { InstallAppDialog } from '@/components/app/install-app-dialog';
import { TryDemoButton } from '@/components/app/try-demo-button';

const SOURCE_URL =
    'https://www.ocbc.id/id/tentang-ocbc/informasi/siaran-pers/2024/08/19/ocbc-business-fitness-index';

export const shell = 'mx-auto w-full max-w-[1280px] px-4 md:px-8';
const creditLink =
    'underline underline-offset-2 hover:text-foreground pointer-coarse:py-[15px]';
export const heading =
    'text-3xl font-semibold tracking-tight text-balance md:text-4xl';

/** "Pasang di HP", only on a phone or browser that can put Lais on its home screen. */
function InstallButton() {
    const way = useInstallWay();

    return (
        way && (
            <>
                <Button
                    type="button"
                    size="lg"
                    variant="ghost"
                    onClick={installApp}
                >
                    <Smartphone aria-hidden />
                    Pasang di HP
                </Button>
                <InstallAppDialog />
            </>
        )
    );
}

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
    const { auth } = usePage().props;

    return (
        <section aria-labelledby="judul" className="px-2 pt-2 md:px-4 md:pt-4">
            <div className="landing-texture mx-auto max-w-[1400px] rounded-3xl bg-landing-glow pt-24 pb-8 md:pt-28 md:pb-12">
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
                            Aplikasi pembukuan untuk UMKM. Ketik penjualan
                            seperti biasa, lalu Lais mengurangi stok sesuai
                            resep, menghitung HPP, memisahkan uang pribadi, dan
                            menyusun laporannya.
                        </p>
                        <div
                            data-intro
                            style={delay(460)}
                            className="mt-6 flex flex-wrap gap-2"
                        >
                            <SignUpButton />
                            {/* Visitors try an account of their own; the demo shop stays in the header and its section. */}
                            {!auth.user && <TryDemoButton size="lg" />}
                            {auth.user && demoShop && (
                                <Button asChild size="lg" variant="outline">
                                    <Link href={showShop(demoShop.slug)}>
                                        Lihat Demo Toko
                                    </Link>
                                </Button>
                            )}
                            <InstallButton />
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

function Ledger({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="landing-drift rounded-xl border bg-card p-4 shadow-card">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-semibold">{title}</p>
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

function MiniRow({
    icon: Icon,
    children,
}: {
    icon: LucideIcon;
    children: ReactNode;
}) {
    return (
        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg border bg-card px-3 py-2 text-sm">
            <Icon className="size-4 shrink-0 text-brand-strong" aria-hidden />
            <span>{children}</span>
        </p>
    );
}

/** One survey number as a hundred dots, filled one by one (landing.css). */
function Fact({ value, text }: { value: number; text: string }) {
    return (
        <div
            data-reveal="dots"
            className="flex flex-col items-start gap-5 sm:flex-row sm:items-center"
        >
            <div aria-hidden className="grid shrink-0 grid-cols-10 gap-1">
                {Array.from({ length: 100 }, (_, index) => (
                    <span
                        key={index}
                        style={{ '--i': index } as CSSProperties}
                        className={cn(
                            'size-2 rounded-full md:size-2.5',
                            index < value
                                ? 'landing-dot bg-primary-foreground'
                                : 'border border-primary-foreground/50',
                        )}
                    />
                ))}
            </div>
            <p>
                <span className="num block text-5xl font-semibold tracking-tight md:text-6xl">
                    {value} dari 100
                </span>
                <span className="mt-2 block text-base md:text-lg">{text}</span>
            </p>
        </div>
    );
}

/** The problem, in numbers first, then the four that the answers below take on. */
export function Problem() {
    return (
        <section
            aria-labelledby="masalah"
            className="px-2 py-8 md:px-4 md:py-10"
        >
            <div className="mx-auto max-w-[1400px] rounded-3xl bg-primary py-12 text-primary-foreground md:py-16">
                <div className={shell}>
                    <h2
                        id="masalah"
                        data-reveal="words"
                        className={cn(heading, 'max-w-3xl md:text-5xl')}
                    >
                        <WordReveal text="Usaha jalan terus, tapi untungnya ke mana?" />
                    </h2>
                    <div className="mt-10 grid gap-10 md:grid-cols-2">
                        {surveyFacts.map((fact) => (
                            <Fact key={fact.value} {...fact} />
                        ))}
                    </div>
                    <p className="mt-8 text-sm">
                        Sumber:{' '}
                        <a
                            href={SOURCE_URL}
                            target="_blank"
                            rel="noreferrer"
                            className="underline underline-offset-2 pointer-coarse:py-[14px]"
                        >
                            OCBC Business Fitness Index 2024
                        </a>
                        , OCBC dan NielsenIQ, 620 responden.
                    </p>
                    <ol className="mt-10 grid gap-x-8 gap-y-6 border-t border-primary-foreground/30 pt-8 sm:grid-cols-2 lg:grid-cols-4">
                        {problems.map((problem, index) => (
                            <li
                                key={problem.id}
                                data-reveal="up"
                                style={delay(index * 90)}
                                className="flex gap-3"
                            >
                                <span className="num flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-foreground font-semibold text-primary-text">
                                    {index + 1}
                                </span>
                                <span>
                                    <span className="block font-semibold">
                                        {problem.title}
                                    </span>
                                    <span className="mt-1 block text-sm">
                                        {problem.pain}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
            </div>
        </section>
    );
}

/** What each answer shows, in pieces of the app (sample numbers). */
const answerVisuals: Record<(typeof problems)[number]['id'], ReactNode> = {
    uang: (
        <>
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
        </>
    ),
    catat: (
        <div className="flex flex-col gap-2 rounded-xl border bg-card p-4 shadow-card">
            <p className="max-w-[85%] self-end rounded-2xl rounded-br-md bg-brand-soft px-3 py-2 text-sm">
                jual 2 es teh tunai
            </p>
            <p className="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-secondary px-3 py-2 text-sm">
                Penjualan · 2 gelas Es Teh = Rp10.000. Balas 1 untuk simpan.
            </p>
            <p className="max-w-[85%] self-end rounded-2xl rounded-br-md bg-brand-soft px-3 py-2 text-sm">
                1
            </p>
            <p className="max-w-[85%] self-start rounded-2xl rounded-bl-md bg-secondary px-3 py-2 text-sm">
                Penjualan tersimpan.
            </p>
        </div>
    ),
    untung: (
        <>
            <Ledger title="Nasi Goreng, per porsi">
                <LedgerRow label="Harga jual">
                    <Money value={15_000} size="sm" />
                </LedgerRow>
                <LedgerRow label="HPP dari resep">
                    <Money value={7_200} direction="out" size="sm" />
                </LedgerRow>
                <LedgerRow label="Untung">
                    <span className="flex items-center gap-2">
                        <MarginLabel percent={52} />
                        <Money value={7_800} size="sm" />
                    </span>
                </LedgerRow>
            </Ledger>
            <Note>Laporan mengacu pada SAK EMKM</Note>
        </>
    ),
    stok: (
        <div className="flex flex-col gap-2 rounded-xl border bg-card p-4 shadow-card">
            <div className="flex items-center justify-between gap-2">
                <p className="font-semibold">Perlu tindakan hari ini</p>
            </div>
            <MiniRow icon={TriangleAlert}>Stok telur menipis</MiniRow>
            <MiniRow icon={Clock}>
                Bu Sari belum bayar <Money value={45_000} size="sm" />, sudah 21
                hari
            </MiniRow>
        </div>
    ),
};

/**
 * The same four problems, answered one by one. On wide screens the list
 * stays beside the answers: the one being read lights up, the ones already
 * answered are struck through (landing.css).
 */
export function Answers() {
    const section = useRef<HTMLElement>(null);
    const active = useActiveStep(section);
    const last = problems.length - 1;

    return (
        <section
            ref={section}
            id="cara-kerja"
            aria-labelledby="jawaban"
            className={cn(shell, 'scroll-mt-6 py-12 md:py-20')}
        >
            <div className="grid gap-10 lg:grid-cols-12 lg:gap-14">
                <div className="lg:col-span-5">
                    <div className="lg:sticky lg:top-24">
                        <h2
                            id="jawaban"
                            data-reveal="words"
                            className={heading}
                        >
                            <WordReveal text="Begini Lais menjawabnya" />
                        </h2>
                        <p className="mt-3 text-muted-foreground">
                            Empat masalah tadi, satu per satu.
                        </p>
                        <ol
                            aria-hidden
                            style={
                                {
                                    '--progress':
                                        Math.min(Math.max(active, 0), last) /
                                        last,
                                } as CSSProperties
                            }
                            className="landing-steps relative mt-8 hidden flex-col gap-7 lg:flex"
                        >
                            {problems.map((problem, index) => (
                                <li
                                    key={problem.id}
                                    data-state={
                                        index < active
                                            ? 'done'
                                            : index === active
                                              ? 'active'
                                              : 'next'
                                    }
                                    className="landing-step group relative flex items-start gap-4"
                                >
                                    <span className="num relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border-2 border-border bg-card font-semibold text-muted-foreground transition-colors group-data-[state=active]:border-primary group-data-[state=active]:bg-primary group-data-[state=active]:text-primary-foreground group-data-[state=done]:border-brand-strong group-data-[state=done]:text-primary-text">
                                        {index < active ? (
                                            <Check className="size-4" />
                                        ) : (
                                            index + 1
                                        )}
                                    </span>
                                    <span className="pt-1.5 font-semibold text-muted-foreground transition-colors group-data-[state=active]:text-foreground">
                                        <span className="landing-struck">
                                            {problem.title}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </div>
                </div>
                <ol className="flex flex-col gap-16 lg:col-span-7 lg:gap-28">
                    {problems.map((problem, index) => (
                        <li key={problem.id} data-step={index}>
                            <p
                                data-reveal="strike"
                                className="text-sm font-medium text-muted-foreground lg:sr-only"
                            >
                                <span className="sr-only">
                                    Masalah {index + 1}:{' '}
                                </span>
                                <span className="landing-struck">
                                    {problem.title}
                                </span>
                            </p>
                            <div
                                data-reveal="up"
                                className="mt-3 rounded-3xl bg-landing-glow p-4 md:p-8 lg:mt-0"
                            >
                                {answerVisuals[problem.id]}
                            </div>
                            <h3 className="mt-6 text-2xl font-semibold tracking-tight text-balance md:text-3xl">
                                {problem.answer}
                            </h3>
                            <p className="mt-3 text-muted-foreground">
                                {problem.detail}
                            </p>
                        </li>
                    ))}
                </ol>
            </div>
            <span
                aria-hidden
                data-step={problems.length}
                className="block h-px"
            />
        </section>
    );
}

/**
 * The kinds of shops as photo cards drifting past (landing.css). Two copies
 * make the loop and the second is inert. Only a moving cursor holds it: one
 * resting on the strip while the page scrolls would freeze it for good.
 */
export function Audience() {
    const [held, setHeld] = useState(false);
    // A tap (not a scroll) holds it on touch screens; another tap lets it go.
    const [tapped, setTapped] = useState(false);

    return (
        <section aria-labelledby="untuk-siapa" className="py-12 md:py-16">
            <h2
                id="untuk-siapa"
                data-reveal="words"
                className={cn(shell, heading, 'text-center')}
            >
                <WordReveal text="Dari gerobak soto sampai penjahit batik" />
            </h2>
            <p
                data-reveal="up"
                style={delay(200)}
                className={cn(
                    shell,
                    'mt-3 max-w-2xl text-center text-balance text-muted-foreground',
                )}
            >
                Apa pun yang kamu jual, caranya sama: cukup chat, lalu
                penjualan, stok bahan, dan untungnya tercatat sendiri.
            </p>
            <div
                role="region"
                aria-label="Foto jenis usaha. Gerakkan kursor, sentuh, atau fokus untuk berhenti."
                tabIndex={0}
                data-reveal="up"
                data-held={held || tapped || undefined}
                onPointerMove={(event) => {
                    if (event.pointerType === 'mouse') {
                        setHeld(true);
                    }
                }}
                onPointerLeave={() => setHeld(false)}
                onClick={(event) => {
                    if (!isMouse(event.nativeEvent)) {
                        setTapped((now) => !now);
                    }
                }}
                className="landing-strip mt-8 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <div className="landing-strip-track">
                    <PhotoList />
                    <PhotoList copy />
                </div>
            </div>
        </section>
    );
}

function PhotoList({ copy = false }: { copy?: boolean }) {
    return (
        <ul inert={copy} className="landing-strip-list">
            {businessPhotos.map((photo) => (
                <li key={photo.category} className="w-64 shrink-0 lg:w-80">
                    <div className="aspect-[4/5] overflow-hidden rounded-2xl bg-secondary">
                        <img
                            src={`/images/landing/${photo.file}-800.webp`}
                            srcSet={`/images/landing/${photo.file}-480.webp 480w, /images/landing/${photo.file}-800.webp 800w`}
                            sizes="(min-width: 1024px) 320px, 256px"
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
            <div className="landing-texture mx-auto max-w-[1400px] rounded-3xl bg-landing-glow py-10 md:py-14">
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
                    Coba jadi pelanggan di Demo Toko dan lihat alurnya dari
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
            <div className="landing-texture mx-auto flex max-w-[1400px] flex-col items-center gap-5 rounded-3xl bg-landing-glow px-4 py-14 text-center md:py-20">
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
