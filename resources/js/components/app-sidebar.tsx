import { Link, usePage } from '@inertiajs/react';
import {
    BookUser,
    ChartColumn,
    Landmark,
    LayoutDashboard,
    MessageCircleQuestion,
    NotebookPen,
    NotebookText,
    Package,
    ScrollText,
    Settings,
    ShoppingBag,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { useAssistant } from '@/components/app/assistant/assistant-provider';
import { NavMain, type AppNavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard, settings } from '@/routes';
import contacts from '@/routes/contacts';
import debts from '@/routes/debts';
import activity from '@/routes/activity';
import items from '@/routes/items';
import moneyAccounts from '@/routes/money-accounts';
import orders from '@/routes/orders';
import reports from '@/routes/reports';
import transactions from '@/routes/transactions';

// Order follows how often an owner needs each page (UI-PAGES.md).
const navGroups = (
    openAssistant: () => void,
    pendingOrders: number,
): AppNavGroup[] => [
    {
        label: 'Utama',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
            {
                title: 'Transaksi',
                href: transactions.index(),
                icon: NotebookText,
            },
            {
                title: 'Pesanan',
                href: orders.index(),
                icon: ShoppingBag,
                count: pendingOrders,
            },
        ],
    },
    {
        label: 'Operasional',
        items: [
            {
                title: 'Kas & rekening',
                href: moneyAccounts.index(),
                icon: Landmark,
            },
            { title: 'Produk & stok', href: items.index(), icon: Package },
            { title: 'Utang-piutang', href: debts.index(), icon: NotebookPen },
            { title: 'Kontak', href: contacts.index(), icon: BookUser },
        ],
    },
    {
        label: 'Analisis',
        items: [
            { title: 'Laporan', href: reports.index(), icon: ChartColumn },
            {
                title: 'Tanya AI',
                icon: MessageCircleQuestion,
                onSelect: openAssistant,
            },
        ],
    },
    {
        label: 'Akun',
        items: [
            {
                title: 'Log aktivitas',
                href: activity.index(),
                icon: ScrollText,
            },
            { title: 'Pengaturan', href: settings(), icon: Settings },
        ],
    },
];

export function AppSidebar() {
    const { setOpen } = useAssistant();
    const { setOpenMobile } = useSidebar();
    const { pendingOrders } = usePage().props;
    const groups = navGroups(() => {
        // On phones the sidebar is a sheet; it makes way for the panel.
        setOpenMobile(false);
        setOpen(true);
    }, pendingOrders);

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="border-b border-sidebar-border px-3 py-3 group-data-[collapsible=icon]:px-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="py-2">
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
