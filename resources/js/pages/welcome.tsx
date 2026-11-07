import { Head } from '@inertiajs/react';
import { LandingFooter } from '@/features/landing/landing-footer';
import { LandingHeader } from '@/features/landing/landing-header';
import {
    Audience,
    Closing,
    ExampleShop,
    Hero,
    HowItWorks,
    Problem,
    Reports,
    SeparateMoney,
    Trust,
} from '@/features/landing/landing-sections';
import type { WelcomeProps } from '@/types/domain';

/** The landing page (UI-PAGES A1). */
export default function Welcome({ demoShop }: WelcomeProps) {
    return (
        <>
            <Head title="Catat usaha cukup lewat chat">
                <meta
                    name="description"
                    content="Lais mencatat penjualan dari chat, mengurangi stok sesuai resep, memisahkan uang pribadi, dan menyusun laporan yang mengacu pada SAK EMKM."
                />
            </Head>
            <div className="relative min-h-svh bg-background text-foreground">
                <a
                    href="#konten"
                    className="sr-only z-50 rounded-lg bg-card px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
                >
                    Lewati ke konten utama
                </a>
                <LandingHeader hasShop={demoShop !== null} />
                <main id="konten" tabIndex={-1}>
                    <Hero demoShop={demoShop} />
                    <Problem />
                    <HowItWorks />
                    <Audience />
                    <SeparateMoney />
                    <Reports />
                    <Trust />
                    {demoShop && <ExampleShop shop={demoShop} />}
                    <Closing />
                </main>
                <LandingFooter hasShop={demoShop !== null} />
            </div>
        </>
    );
}
