import { usePage } from '@inertiajs/react';

/**
 * Text wordmark only. The product name is a placeholder and no logo has
 * been designed yet (antislop R-23), so nothing pretends to be one.
 */
export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <span className="flex min-w-0 items-baseline gap-2">
            <span className="text-lg leading-none font-extrabold tracking-tight text-foreground group-data-[collapsible=icon]:hidden">
                {name}
            </span>
            <span
                aria-hidden
                className="hidden text-lg leading-none font-extrabold text-foreground group-data-[collapsible=icon]:inline"
            >
                {String(name).charAt(0)}
            </span>
        </span>
    );
}
