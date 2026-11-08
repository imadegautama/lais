import { Plus } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { PageGuide } from '@/components/app/page-guide';
import { QuickRecordSheet } from '@/components/app/quick-record-sheet';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b bg-card px-4 md:px-6 print:hidden">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger
                    className="-ml-1 hidden lg:inline-flex"
                    aria-label="Buka atau tutup sidebar"
                />
                <span className="lg:hidden">
                    <AppLogo />
                </span>
                <span className="hidden min-w-0 lg:block">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </span>
            </div>
            <PageGuide />
            {/* Phones record from the bottom nav instead. */}
            <div className="hidden lg:block">
                <QuickRecordSheet
                    trigger={
                        <Button data-tour="catat">
                            <Plus aria-hidden />
                            Catat
                        </Button>
                    }
                />
            </div>
        </header>
    );
}
