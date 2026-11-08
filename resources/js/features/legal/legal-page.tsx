import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { LogoWordmark } from '@/components/logo-wordmark';
import { home, privacy, terms } from '@/routes';

/** Where people ask about their data; forwarded by Cloudflare Email Routing. */
export const CONTACT_EMAIL = 'halo@lais.web.id';

/** The two public policy pages: plain reading width, the logo on white. */
export function LegalPage({
    title,
    updated,
    children,
}: {
    title: string;
    updated: string;
    children: ReactNode;
}) {
    return (
        <div className="min-h-svh bg-background">
            <Head title={title} />
            <header className="mx-auto w-full max-w-2xl px-6 pt-8 md:pt-12">
                <Link href={home()} className="inline-block rounded-md">
                    <LogoWordmark className="h-8" />
                    <span className="sr-only">Lais, ke beranda</span>
                </Link>
            </header>

            <main className="mx-auto w-full max-w-2xl px-6 py-10 md:py-14">
                <h1 className="text-3xl font-semibold tracking-tight text-balance md:text-4xl">
                    {title}
                </h1>
                <p className="mt-3 text-sm text-muted-foreground">
                    Berlaku sejak {updated}
                </p>
                <div className="mt-8 leading-relaxed [&_a]:underline [&_a]:underline-offset-2 [&_h2]:mt-10 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_li]:mt-2 [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-5">
                    {children}
                </div>
            </main>

            <footer className="mx-auto flex w-full max-w-2xl flex-wrap gap-x-5 gap-y-2 border-t px-6 py-6 text-sm text-muted-foreground">
                <p className="mr-auto">© 2026 Tim Lais</p>
                <Link href={privacy()} className="hover:text-foreground">
                    Kebijakan Privasi
                </Link>
                <Link href={terms()} className="hover:text-foreground">
                    Syarat dan Ketentuan
                </Link>
            </footer>
        </div>
    );
}
