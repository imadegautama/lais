import { Link } from '@inertiajs/react';
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
import { dashboard } from '@/routes';
import activity from '@/routes/activity';
import items from '@/routes/items';
import moneyAccounts from '@/routes/money-accounts';
import reports from '@/routes/reports';
import transactions from '@/routes/transactions';
import { edit as editProfile } from '@/routes/profile';

// Order follows how often an owner needs each page (UI-PAGES.md).
const navGroups = (openAssistant: () => void): AppNavGroup[] => [
    {
        label: 'Utama',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
            {
                title: 'Transaksi',
                href: transactions.index(),
                icon: NotebookText,
            },
            { title: 'Pesanan', icon: ShoppingBag, comingSoon: true },
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
            { title: 'Utang-piutang', icon: NotebookPen, comingSoon: true },
            { title: 'Kontak', icon: BookUser, comingSoon: true },
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
            { title: 'Pengaturan', href: editProfile(), icon: Settings },
        ],
    },
];

export function AppSidebar() {
    const { setOpen } = useAssistant();
    const { setOpenMobile } = useSidebar();
    const groups = navGroups(() => {
        // On phones the sidebar is a sheet; it makes way for the panel.
        setOpenMobile(false);
        setOpen(true);
    });

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
