import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { MessageCircle, ShieldCheck, Store, UserRound } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/app/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { edit as editBusiness } from '@/routes/business';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editBot } from '@/routes/whatsapp';

const tabs: { title: string; href: string; icon: LucideIcon }[] = [
    { title: 'Usaha', href: editBusiness.url(), icon: Store },
    { title: 'Akun', href: editProfile.url(), icon: UserRound },
    { title: 'Keamanan', href: editSecurity.url(), icon: ShieldCheck },
    { title: 'Bot', href: editBot.url(), icon: MessageCircle },
];

/**
 * Pengaturan (UI-PAGES C20): tabs on the left on desktop, a row of tabs
 * above the content on a phone. Each page is a stack of SectionCards.
 */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
            <PageHeader
                title="Pengaturan"
                description="Data usaha, akun, keamanan, dan bot Lais."
            />

            <div className="flex flex-col gap-4 lg:flex-row lg:gap-8">
                <nav
                    aria-label="Pengaturan"
                    className="-mx-4 shrink-0 overflow-x-auto px-4 lg:mx-0 lg:w-48 lg:overflow-visible lg:px-0"
                >
                    <ul className="flex gap-1 lg:flex-col">
                        {tabs.map(({ title, href, icon: Icon }) => {
                            const active = isCurrentOrParentUrl(href);

                            return (
                                <li key={href}>
                                    <Link
                                        href={href}
                                        prefetch
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={cn(
                                            'flex h-9 items-center gap-2 rounded-md px-3 text-sm whitespace-nowrap transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none pointer-coarse:h-11',
                                            active
                                                ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        <Icon className="size-4" aria-hidden />
                                        {title}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </nav>

                <div className="flex max-w-2xl min-w-0 flex-1 flex-col gap-4 md:gap-6">
                    {children}
                </div>
            </div>
        </div>
    );
}
