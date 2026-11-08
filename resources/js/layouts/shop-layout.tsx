import { Link, usePage } from '@inertiajs/react';
import { Store } from 'lucide-react';
import type { ReactNode } from 'react';
import { LogoWordmark } from '@/components/logo-wordmark';
import { home } from '@/routes';
import type { ShopInfo } from '@/types/domain';

/**
 * The customer's side (UI-PAGES "Layout publik"): made for a phone, up to
 * 1152 px so the shop list can sit beside its cart; the checkout and the
 * nota keep their own 640 px column. The header names the shop, not the app.
 */
export default function ShopLayout({ children }: { children: ReactNode }) {
    const { shop, name } = usePage<{ shop: ShopInfo; name: string }>().props;

    return (
        <div className="flex min-h-svh flex-col bg-page-wash">
            <header className="border-b bg-card print:border-none">
                <div className="mx-auto flex h-14 w-full max-w-6xl items-center gap-3 px-4">
                    <span
                        aria-hidden
                        className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand-strong"
                    >
                        <Store className="size-4" />
                    </span>
                    <p className="min-w-0 truncate text-base font-semibold">
                        {shop.name}
                    </p>
                </div>
            </header>
            <main
                id="konten"
                className="mx-auto flex w-full max-w-6xl flex-1 flex-col px-4 py-5"
            >
                {children}
            </main>
            <footer className="pb-[calc(1rem+env(safe-area-inset-bottom))] text-center text-xs text-muted-foreground print:hidden">
                <Link
                    href={home()}
                    className="inline-flex items-center gap-1.5 rounded-sm py-2 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    Dibuat dengan
                    <LogoWordmark title={name} className="h-3.5" />
                </Link>
            </footer>
        </div>
    );
}
