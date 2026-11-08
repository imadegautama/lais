import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Check, Palette, Shirt, Store, UtensilsCrossed } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/** The kinds of shop the landing page shows; no user counts or partner logos (DESIGN.md §6.16). */
const businesses: Array<{ label: string; icon: LucideIcon }> = [
    { label: 'Kuliner', icon: UtensilsCrossed },
    { label: 'Kelontong', icon: Store },
    { label: 'Fashion', icon: Shirt },
    { label: 'Kerajinan', icon: Palette },
];

/** A few things owners type and what Lais answers; no money amounts. */
const chats = [
    {
        message: 'jual 3 nasgor 2 es teh qris',
        reply: 'Penjualan tercatat. Stok nasi dan telur ikut berkurang sesuai resep.',
    },
    {
        message: 'beli beras 5 kg di toko makmur',
        reply: 'Belanja tercatat. Stok beras bertambah 5 kg.',
    },
    {
        message: 'bu sari bayar utangnya',
        reply: 'Pembayaran Bu Sari tercatat. Sisa utangnya ikut berkurang.',
    },
];

/** How long one exchange stays before the next (the CSS fades it at 4.7 s). */
const CHAT_MS = 5200;

/**
 * The panel's chat plays one exchange after another (landing.css). It holds
 * under the cursor, and with reduced motion it stays on the first, still.
 */
function PanelChat() {
    const [index, setIndex] = useState(0);
    const [held, setHeld] = useState(false);
    // Bumped when the cursor leaves, so the exchange replays in step with the timer.
    const [run, setRun] = useState(0);
    const chat = chats[index];

    useEffect(() => {
        if (
            held ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        const timer = window.setTimeout(
            () => setIndex((now) => (now + 1) % chats.length),
            CHAT_MS,
        );

        return () => window.clearTimeout(timer);
    }, [held, index, run]);

    return (
        <div
            aria-hidden
            key={`${index}-${run}`}
            data-held={held || undefined}
            onMouseEnter={() => setHeld(true)}
            onMouseLeave={() => {
                setHeld(false);
                setRun((now) => now + 1);
            }}
            className="auth-chat flex max-w-md flex-col gap-3"
        >
            <p className="auth-chat-message max-w-[80%] self-end rounded-2xl rounded-br-md bg-brand-soft px-4 py-2.5 text-sm text-foreground">
                {chat.message}
            </p>
            <div className="relative max-w-[85%]">
                <p className="auth-chat-typing absolute top-0 left-0 flex items-center gap-1.5 rounded-2xl rounded-bl-md bg-card px-4 py-3 text-muted-foreground shadow-card">
                    <span className="landing-reading flex gap-1">
                        <span />
                        <span />
                        <span />
                    </span>
                </p>
                <p className="auth-chat-reply flex items-start gap-2 rounded-2xl rounded-bl-md bg-card px-4 py-2.5 text-sm text-foreground shadow-card">
                    <Check className="mt-0.5 size-4 shrink-0 text-success" />
                    {chat.reply}
                </p>
            </div>
        </div>
    );
}

/**
 * Every auth page: the form on white, its logo lined up with the fields,
 * and from 1024 px a brand-orange panel that says what Lais does. The logo
 * never sits on the panel (DESIGN.md §2); the panel holds no numbers.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-2">
            <div className="flex flex-col items-center px-6 py-8 md:px-10">
                <div className="flex w-full max-w-md flex-1 flex-col">
                    <Link href={home()} className="w-fit rounded-md">
                        <AppLogo className="h-10" />
                        <span className="sr-only">Lais, ke beranda</span>
                    </Link>

                    <main className="flex flex-1 flex-col justify-center py-12">
                        <h1 className="text-3xl font-semibold tracking-tight text-balance">
                            {title}
                        </h1>
                        <p className="mt-3 text-muted-foreground">
                            {description}
                        </p>
                        <div className="mt-8">{children}</div>
                    </main>
                </div>
            </div>

            <aside
                aria-labelledby="tentang-lais"
                className="auth-texture hidden flex-col bg-auth-panel px-14 py-14 text-primary-foreground lg:flex xl:px-20"
            >
                <div className="my-auto flex max-w-xl flex-col gap-12">
                    <h2
                        id="tentang-lais"
                        className="text-4xl leading-tight font-semibold tracking-tight text-balance xl:text-5xl"
                    >
                        Cukup chat, pembukuan usahamu tersusun sendiri.
                    </h2>

                    <PanelChat />
                </div>

                <div className="mt-12">
                    <div className="flex items-center gap-4">
                        <p className="text-sm font-medium">Cocok untuk</p>
                        <span
                            aria-hidden
                            className="h-px flex-1 bg-primary-foreground/30"
                        />
                    </div>
                    <ul className="mt-6 flex flex-wrap gap-x-10 gap-y-4">
                        {businesses.map(({ label, icon: Icon }) => (
                            <li
                                key={label}
                                className="flex items-center gap-2 text-lg font-semibold"
                            >
                                <Icon aria-hidden className="size-5" />
                                {label}
                            </li>
                        ))}
                    </ul>
                </div>
            </aside>
        </div>
    );
}
