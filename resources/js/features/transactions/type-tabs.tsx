import { Link } from '@inertiajs/react';
import { everydayTypes, transactionTypes } from '@/components/app/badges';
import { cn } from '@/lib/utils';
import { create } from '@/routes/transactions';
import type { TransactionType } from '@/types/enums';

/** The everyday record types as links, so switching opens a clean form. */
export function TypeTabs({ current }: { current: TransactionType }) {
    return (
        <nav aria-label="Jenis catatan" className="flex flex-wrap gap-1.5">
            {everydayTypes.map((type) => {
                const { label, icon: Icon } = transactionTypes[type];
                const active = type === current;

                return (
                    <Link
                        key={type}
                        href={create({ query: { jenis: type } })}
                        preserveScroll
                        prefetch
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'inline-flex min-h-9 items-center gap-1.5 rounded-md border bg-card px-3 text-sm font-medium shadow-button-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none pointer-coarse:min-h-11',
                            active
                                ? 'border-primary bg-accent text-accent-foreground'
                                : 'hover:bg-accent',
                        )}
                    >
                        <Icon
                            className={cn(
                                'size-4',
                                active
                                    ? 'text-brand-strong'
                                    : 'text-muted-foreground',
                            )}
                            aria-hidden
                        />
                        {label}
                    </Link>
                );
            })}
        </nav>
    );
}
