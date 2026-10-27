import { Link } from '@inertiajs/react';
import { ArrowRight, ScrollText } from 'lucide-react';
import { SourceLabel } from '@/components/app/badges';
import { EmptyState } from '@/components/app/states';
import { formatDate } from '@/lib/format';
import type { ActivityEntry } from '@/types/domain';

type ActivityItemProps = {
    entry: ActivityEntry;
    /** The sentence links to the record (Log aktivitas); a record's own Riwayat leaves it plain. */
    linked?: boolean;
    /** Who, when, and the channel under the sentence; the desktop log shows them as columns instead. */
    meta?: 'datetime' | 'time' | false;
};

/**
 * One audit-log entry: what happened, each field that changed (old → new),
 * and who did it, when, and through which channel.
 */
export function ActivityItem({
    entry,
    linked = false,
    meta = 'datetime',
}: ActivityItemProps) {
    return (
        <div className="flex min-w-0 flex-col gap-1">
            <p className="text-sm font-medium">
                {linked && entry.href ? (
                    <Link
                        href={entry.href}
                        className="rounded-sm hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        {entry.description}
                    </Link>
                ) : (
                    entry.description
                )}
            </p>
            {entry.changes.length > 0 && (
                <dl className="flex flex-col gap-0.5 text-sm">
                    {entry.changes.map((change) => (
                        <div
                            key={change.label}
                            className="flex flex-wrap items-baseline gap-x-1.5"
                        >
                            <dt className="text-muted-foreground">
                                {change.label}:
                            </dt>
                            <dd className="flex flex-wrap items-baseline gap-x-1.5">
                                <span className="text-muted-foreground">
                                    {change.old}
                                </span>
                                <ArrowRight
                                    className="size-3.5 shrink-0 self-center text-muted-foreground"
                                    aria-hidden
                                />
                                <span className="sr-only">menjadi</span>
                                <span className="font-medium">
                                    {change.new}
                                </span>
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
            {entry.detail && (
                <p className="text-xs text-muted-foreground">{entry.detail}</p>
            )}
            {meta && (
                <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                    <span>{entry.actor}</span>
                    <span aria-hidden>·</span>
                    <time dateTime={entry.at} className="num">
                        {formatDate(entry.at, meta)}
                    </time>
                    {entry.source && (
                        <>
                            <span aria-hidden>·</span>
                            <SourceLabel source={entry.source} />
                        </>
                    )}
                </p>
            )}
        </div>
    );
}

/** A record's Riwayat, newest first. Records from before the log began have none. */
export function ActivityList({ entries }: { entries: ActivityEntry[] }) {
    if (entries.length === 0) {
        return (
            <EmptyState
                icon={ScrollText}
                title="Belum ada riwayat"
                description="Perubahan berikutnya, siapa yang membuatnya, dan kapan, akan tercatat di sini."
            />
        );
    }

    return (
        <ul className="flex flex-col divide-y">
            {entries.map((entry) => (
                <li key={entry.id} className="py-3 first:pt-0 last:pb-0">
                    <ActivityItem entry={entry} />
                </li>
            ))}
        </ul>
    );
}
