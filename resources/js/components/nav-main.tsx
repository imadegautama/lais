import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { SoonTag } from '@/components/app/action-button';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';

export type AppNavItem = {
    title: string;
    icon: LucideIcon;
} & (
    | { href: NonNullable<InertiaLinkProps['href']>; comingSoon?: never }
    // TODO: switch to { href } as each page is built (R-24: no dead links).
    | { href?: never; comingSoon: true }
);

export type AppNavGroup = { label: string; items: AppNavItem[] };

export function NavMain({ groups }: { groups: AppNavGroup[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <>
            {groups.map((group) => (
                <SidebarGroup key={group.label} className="px-2 py-1">
                    {/* Sentence case on purpose: no wide-tracked uppercase (DESIGN.md). */}
                    <SidebarGroupLabel className="text-xs font-semibold text-muted-foreground">
                        {group.label}
                    </SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                {item.href ? (
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentUrl(item.href)}
                                        tooltip={{ children: item.title }}
                                        className="[&>svg]:text-muted-foreground data-[active=true]:[&>svg]:text-brand-strong"
                                    >
                                        <Link href={item.href} prefetch>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                ) : (
                                    <SidebarMenuButton
                                        aria-disabled="true"
                                        tooltip={{
                                            children: `${item.title} (segera)`,
                                        }}
                                        className="cursor-default text-muted-foreground hover:bg-transparent hover:text-muted-foreground aria-disabled:opacity-75"
                                    >
                                        <item.icon />
                                        <span className="flex-1">
                                            {item.title}
                                        </span>
                                        <SoonTag className="group-data-[collapsible=icon]:hidden" />
                                    </SidebarMenuButton>
                                )}
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
