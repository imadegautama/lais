import { Link } from '@inertiajs/react';
import {
    House,
    Menu,
    NotebookText,
    Plus,
    ShoppingBag,
    type LucideIcon,
} from 'lucide-react';
import { QuickRecordSheet } from '@/components/app/quick-record-sheet';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

const itemClass =
    'flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 rounded-lg text-[11px] font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function SoonItem({ icon: Icon, label }: { icon: LucideIcon; label: string }) {
    // TODO(C2/C9): becomes a Link when the page exists.
    return (
        <span
            aria-disabled="true"
            className={cn(itemClass, 'text-muted-foreground/70')}
        >
            <Icon className="size-5" aria-hidden />
            {label}
            <span className="text-[10px] leading-none">Segera</span>
        </span>
    );
}

/**
 * Phone and tablet navigation (< lg): the four destinations an owner reaches daily
 * plus the record button in the thumb zone. The sidebar takes over at lg.
 */
export function MobileBottomNav() {
    const { isCurrentUrl } = useCurrentUrl();
    const { setOpenMobile } = useSidebar();
    const onDashboard = isCurrentUrl(dashboard());

    return (
        <nav
            aria-label="Navigasi utama"
            className="fixed inset-x-0 bottom-0 z-40 border-t bg-card pb-[env(safe-area-inset-bottom)] lg:hidden"
        >
            <div className="mx-auto flex max-w-lg items-stretch gap-1 px-2 py-1">
                <Link
                    href={dashboard()}
                    aria-current={onDashboard ? 'page' : undefined}
                    className={cn(
                        itemClass,
                        onDashboard
                            ? 'text-accent-foreground'
                            : 'text-muted-foreground',
                    )}
                >
                    <House
                        className={cn(
                            'size-5',
                            onDashboard && 'text-brand-strong',
                        )}
                        aria-hidden
                    />
                    Beranda
                </Link>
                <SoonItem icon={NotebookText} label="Transaksi" />
                <QuickRecordSheet
                    trigger={
                        <button
                            type="button"
                            className={cn(itemClass, 'text-foreground')}
                        >
                            <span className="flex size-10 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-button">
                                <Plus className="size-5" aria-hidden />
                            </span>
                            Catat
                        </button>
                    }
                />
                <SoonItem icon={ShoppingBag} label="Pesanan" />
                <button
                    type="button"
                    onClick={() => setOpenMobile(true)}
                    className={cn(itemClass, 'text-muted-foreground')}
                >
                    <Menu className="size-5" aria-hidden />
                    Lainnya
                </button>
            </div>
        </nav>
    );
}
