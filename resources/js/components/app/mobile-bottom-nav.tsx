import { Link, usePage, type InertiaLinkProps } from '@inertiajs/react';
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
import orders from '@/routes/orders';
import transactions from '@/routes/transactions';

const itemClass =
    'flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 rounded-lg text-[11px] font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function NavLink({
    href,
    icon: Icon,
    label,
    active,
    count = 0,
}: {
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    label: string;
    active: boolean;
    /** Waiting items, shown as a small red number on the icon. */
    count?: number;
}) {
    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={cn(
                itemClass,
                active ? 'text-accent-foreground' : 'text-muted-foreground',
            )}
        >
            <span className="relative">
                <Icon
                    className={cn('size-5', active && 'text-brand-strong')}
                    aria-hidden
                />
                {count > 0 && (
                    <span className="num absolute -top-1.5 -right-2.5 min-w-4 rounded-full bg-destructive px-1 text-center text-[10px] leading-4 font-semibold text-white">
                        {count}
                    </span>
                )}
            </span>
            {label}
            {count > 0 && <span className="sr-only">, {count} menunggu</span>}
        </Link>
    );
}

/**
 * Phone and tablet navigation (< lg): the four destinations an owner reaches daily
 * plus the record button in the thumb zone. The sidebar takes over at lg.
 */
export function MobileBottomNav() {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { setOpenMobile } = useSidebar();
    const { pendingOrders } = usePage().props;

    return (
        <nav
            aria-label="Navigasi utama"
            className="fixed inset-x-0 bottom-0 z-40 border-t bg-card pb-[env(safe-area-inset-bottom)] lg:hidden print:hidden"
        >
            <div className="mx-auto flex max-w-lg items-stretch gap-1 px-2 py-1">
                <NavLink
                    href={dashboard()}
                    icon={House}
                    label="Beranda"
                    active={isCurrentUrl(dashboard())}
                />
                <NavLink
                    href={transactions.index()}
                    icon={NotebookText}
                    label="Transaksi"
                    active={isCurrentOrParentUrl(transactions.index())}
                />
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
                <NavLink
                    href={orders.index()}
                    icon={ShoppingBag}
                    label="Pesanan"
                    count={pendingOrders}
                    active={isCurrentOrParentUrl(orders.index())}
                />
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
