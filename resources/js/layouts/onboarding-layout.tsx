import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { dashboard } from '@/routes';

/**
 * The onboarding wizard on its own, without the sidebar: one job on the
 * screen. Skipping is always one tap away; the dashboard reminds the owner.
 */
export default function OnboardingLayout({
    children,
}: {
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-svh flex-col bg-page-wash">
            <header className="border-b bg-card">
                <div className="mx-auto flex h-14 w-full max-w-3xl items-center justify-between gap-4 px-4 md:px-6">
                    <AppLogo />
                    <Link
                        href={dashboard()}
                        className="rounded-sm text-sm font-medium text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:py-3"
                    >
                        Lewati, nanti saja
                    </Link>
                </div>
            </header>
            {/* Full width: the page keeps its own column, so its action bar can span the screen. */}
            <main id="konten" className="flex w-full flex-1 flex-col">
                {children}
            </main>
        </div>
    );
}
