import { Link } from '@inertiajs/react';
import {
    BookUser,
    ChartColumn,
    LayoutDashboard,
    MessageCircleQuestion,
    NotebookPen,
    NotebookText,
    Package,
    Settings,
    ShoppingBag,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

// Order follows how often an owner needs each page (UI-PAGES.md).
const groups: AppNavGroup[] = [
    {
        label: 'Utama',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
            { title: 'Transaksi', icon: NotebookText, comingSoon: true },
            { title: 'Pesanan', icon: ShoppingBag, comingSoon: true },
        ],
    },
    {
        label: 'Operasional',
        items: [
            { title: 'Produk & stok', icon: Package, comingSoon: true },
            { title: 'Utang-piutang', icon: NotebookPen, comingSoon: true },
            { title: 'Kontak', icon: BookUser, comingSoon: true },
        ],
    },
    {
        label: 'Analisis',
        items: [
            { title: 'Laporan', icon: ChartColumn, comingSoon: true },
            {
                title: 'Tanya AI',
                icon: MessageCircleQuestion,
                comingSoon: true,
            },
        ],
    },
    {
        label: 'Akun',
        items: [{ title: 'Pengaturan', href: editProfile(), icon: Settings }],
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="border-b border-sidebar-border px-3 py-3">
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
