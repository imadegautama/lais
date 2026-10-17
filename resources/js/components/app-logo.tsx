import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LogoWordmark } from '@/components/logo-wordmark';
import { cn } from '@/lib/utils';

/**
 * The logo in the app chrome: the wordmark, or the small mark when the
 * sidebar is collapsed to icons. Height comes from className (h-6 in the
 * header and sidebar, larger on the sign-in pages).
 */
export default function AppLogo({ className }: { className?: string }) {
    const name = String(usePage().props.name);

    return (
        <span className="flex min-w-0 items-center">
            <LogoWordmark
                title={name}
                className={cn(
                    'group-data-[collapsible=icon]:hidden',
                    className,
                )}
            />
            <AppLogoIcon
                role="img"
                aria-label={name}
                className="hidden h-6 w-auto text-foreground group-data-[collapsible=icon]:block"
            />
        </span>
    );
}
