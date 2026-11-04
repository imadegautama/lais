import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { SoonTag } from '@/components/app/action-button';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';

export type AppNavItem = {
    title: string;
    icon: LucideIcon;
    /** Something waiting there, e.g. new orders. */
    count?: number;
} & (
    | {
          href: NonNullable<InertiaLinkProps['href']>;
          comingSoon?: never;
          onSelect?: never;
      }
    // Opens something in place, like the "Tanya AI" panel.
    | { href?: never; comingSoon?: never; onSelect: () => void }
    // TODO: switch to { href } as each page is built (R-24: no dead links).
    | { href?: never; comingSoon: true; onSelect?: never }
);

export type AppNavGroup = { label: string; items: AppNavItem[] };

export function NavMain({ groups }: { groups: AppNavGroup[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

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
                                        isActive={isCurrentOrParentUrl(
                                            item.href,
                                        )}
                                        tooltip={{ children: item.title }}
                                        className="[&>svg]:text-muted-foreground data-[active=true]:[&>svg]:text-brand-strong"
                                    >
                                        <Link href={item.href} prefetch>
                                            <item.icon />
                                            <span>{item.title}</span>
                                            {Boolean(item.count) && (
                                                <span className="sr-only">
                                                    , {item.count} menunggu
                                                </span>
                                            )}
                                        </Link>
                                    </SidebarMenuButton>
                                ) : item.onSelect ? (
                                    <SidebarMenuButton
                                        onClick={item.onSelect}
                                        tooltip={{ children: item.title }}
                                        className="[&>svg]:text-muted-foreground"
                                    >
                                        <item.icon />
                                        <span>{item.title}</span>
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
                                {Boolean(item.count) && (
                                    <SidebarMenuBadge
                                        aria-hidden
                                        className="num rounded-md bg-destructive text-white peer-hover/menu-button:text-white peer-data-[active=true]/menu-button:text-white"
                                    >
                                        {item.count}
                                    </SidebarMenuBadge>
                                )}
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
