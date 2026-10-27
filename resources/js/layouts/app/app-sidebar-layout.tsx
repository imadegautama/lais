import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import {
    AssistantButton,
    AssistantPanel,
} from '@/components/app/assistant/assistant-panel';
import { AssistantProvider } from '@/components/app/assistant/assistant-provider';
import { MobileBottomNav } from '@/components/app/mobile-bottom-nav';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AssistantProvider>
                <a
                    href="#konten"
                    className="sr-only z-50 rounded-lg bg-card px-4 py-2 font-semibold focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:ring-2 focus:ring-ring"
                >
                    Lewati ke konten utama
                </a>
                <AppSidebar />
                {/* Bottom padding keeps the phone nav and "Tanya AI" off the content (app.css). */}
                <AppContent
                    id="konten"
                    tabIndex={-1}
                    variant="sidebar"
                    className="min-w-0 overflow-x-clip bg-page-wash pb-(--content-pad-bottom) outline-none"
                >
                    <AppSidebarHeader breadcrumbs={breadcrumbs} />
                    {children}
                </AppContent>
                <MobileBottomNav />
                <AssistantButton />
                <AssistantPanel />
            </AssistantProvider>
        </AppShell>
    );
}
