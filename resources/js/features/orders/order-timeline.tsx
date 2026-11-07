import { Ban, Check } from 'lucide-react';
import { formatDate, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PublicOrder } from '@/types/domain';
import { orderSteps, stepIndex } from './vocabulary';

/**
 * Where the order stands, with the time of each step (UI-PAGES A4, C10).
 * A refused or cancelled order shows one red line instead.
 */
export function OrderTimeline({ order }: { order: PublicOrder }) {
    if (order.status === 'ditolak') {
        return (
            <div
                role="status"
                className="flex gap-3 rounded-lg bg-destructive-soft px-4 py-3 text-sm"
            >
                <Ban
                    className="mt-0.5 size-4 shrink-0 text-destructive"
                    aria-hidden
                />
                <p>
                    <span className="font-semibold text-destructive">
                        Pesanan tidak diproses
                        {order.rejectedAt &&
                            ` · ${formatDate(order.rejectedAt, 'datetime')}`}
                        .
                    </span>{' '}
                    {order.rejectReason
                        ? `Alasan: ${order.rejectReason}.`
                        : 'Hubungi penjual untuk keterangannya.'}
                </p>
            </div>
        );
    }

    const current = stepIndex(order.status);

    return (
        <ol className="flex flex-col">
            {orderSteps.map((step, index) => {
                const at = step.at(order);
                const done =
                    index < current ||
                    (index === current && order.status === 'selesai');
                // Siap is skipped when the owner goes straight to Selesai.
                const skipped = !at && index < current;
                const active = index === current && !done;

                return (
                    <li
                        key={step.status}
                        aria-current={active ? 'step' : undefined}
                        className="relative flex gap-3 pb-4 last:pb-0"
                    >
                        {index < orderSteps.length - 1 && (
                            <span
                                aria-hidden
                                className={cn(
                                    'absolute top-6 bottom-0 left-[11px] w-0.5',
                                    index < current
                                        ? 'bg-primary'
                                        : 'bg-border',
                                )}
                            />
                        )}
                        <span
                            aria-hidden
                            className={cn(
                                'relative flex size-6 shrink-0 items-center justify-center rounded-full border-2 text-xs font-semibold',
                                done &&
                                    'border-primary bg-primary text-primary-foreground',
                                active &&
                                    'border-primary bg-card text-primary-text',
                                !done &&
                                    !active &&
                                    'border-border bg-card text-muted-foreground',
                            )}
                        >
                            {done ? <Check className="size-3.5" /> : index + 1}
                        </span>
                        <div className="min-w-0 pt-0.5">
                            <p
                                className={cn(
                                    'text-sm',
                                    active || done
                                        ? 'font-semibold'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {step.label}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {at
                                    ? `${formatDate(at, 'datetime')} WIB`
                                    : skipped
                                      ? 'Dilewati'
                                      : active
                                        ? 'Sedang berjalan'
                                        : 'Belum'}
                            </p>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}

/** Items, total, paid, and what is left (A4 "Rincian", C10 "Item"). */
export function OrderLines({ order }: { order: PublicOrder }) {
    return (
        <ul className="flex flex-col divide-y">
            {order.lines.map((line, index) => (
                <li
                    key={index}
                    className="flex items-start justify-between gap-3 py-2.5 text-sm first:pt-0"
                >
                    <div className="min-w-0">
                        <p className="font-medium">{line.name}</p>
                        <p className="num text-xs text-muted-foreground">
                            {line.qty} {line.unit} × {formatRupiah(line.price)}
                        </p>
                    </div>
                    <p className="num shrink-0 font-medium">
                        {formatRupiah(line.subtotal)}
                    </p>
                </li>
            ))}
        </ul>
    );
}
