import { Head } from '@inertiajs/react';
import { useRef } from 'react';
import { AppShowcase } from '@/features/landing/app-showcase';
import { ChatRouter } from '@/features/landing/chat-router';
import { LandingFooter } from '@/features/landing/landing-footer';
import { LandingHeader } from '@/features/landing/landing-header';
import {
    Answers,
    Audience,
    Closing,
    ExampleShop,
    Hero,
    Problem,
    Trust,
} from '@/features/landing/landing-sections';
import { useReveal } from '@/features/landing/use-reveal';
import { useSmoothScroll } from '@/features/landing/use-smooth-scroll';
import type { WelcomeProps } from '@/types/domain';

/** The landing page (UI-PAGES A1). */
export default function Welcome({ demoShop }: WelcomeProps) {
    const page = useRef<HTMLDivElement>(null);
    useReveal(page);
    useSmoothScroll();

    return (
        <>
            <Head title="Catat usaha cukup lewat chat">
                <meta
                    name="description"
                    content="Lais mencatat penjualan dari chat, mengurangi stok sesuai resep, memisahkan uang pribadi, dan menyusun laporan yang mengacu pada SAK EMKM."
                />
            </Head>
            <div
                ref={page}
                className="landing relative min-h-svh overflow-x-clip bg-background text-foreground"
            >
                <a
                    href="#konten"
                    className="sr-only z-50 rounded-lg bg-card px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
                >
                    Lewati ke konten utama
                </a>
                <LandingHeader hasShop={demoShop !== null} />
                <main id="konten" tabIndex={-1}>
                    <Hero demoShop={demoShop} />
                    <ChatRouter />
                    <Problem />
                    <Answers />
                    <AppShowcase />
                    <Audience />
                    <Trust />
                    {demoShop && <ExampleShop shop={demoShop} />}
                    <Closing />
                </main>
                <LandingFooter hasShop={demoShop !== null} />
            </div>
        </>
    );
}
