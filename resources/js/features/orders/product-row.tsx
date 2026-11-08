import { CircleX, Plus } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { QtyStepper } from '@/components/app/qty-stepper';
import { Button } from '@/components/ui/button';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ShopItem } from '@/types/domain';
import { LOW_LEFT, maxQty } from './cart';

/**
 * One product in the shop list (UI-PAGES A2): a small photo, or a tinted tile
 * with its first letter, so a shop without photos still looks finished.
 */
export function ProductRow({
    item,
    qty,
    open,
    full,
    onQty,
}: {
    item: ShopItem;
    qty: number;
    open: boolean;
    /** The order already has 20 different products. */
    full: boolean;
    onQty: (qty: number) => void;
}) {
    const soldOut = !item.orderable;
    const low = !soldOut && item.left !== null && item.left <= LOW_LEFT;

    return (
        <li className="flex gap-3 rounded-xl border bg-card p-3 shadow-card">
            <div
                className={cn(
                    'size-16 shrink-0 overflow-hidden rounded-lg sm:size-20',
                    soldOut ? 'bg-secondary' : 'bg-brand-soft',
                )}
            >
                {item.photoUrl ? (
                    <img
                        src={item.photoUrl}
                        alt=""
                        width={160}
                        height={160}
                        loading="lazy"
                        decoding="async"
                        className={cn(
                            'size-full object-cover',
                            soldOut && 'opacity-50 grayscale',
                        )}
                    />
                ) : (
                    <span
                        aria-hidden
                        className={cn(
                            'flex size-full items-center justify-center text-2xl font-semibold',
                            soldOut
                                ? 'text-muted-foreground'
                                : 'text-primary-text',
                        )}
                    >
                        {item.name.slice(0, 1).toUpperCase()}
                    </span>
                )}
            </div>
            <div className="flex min-w-0 flex-1 flex-col gap-1">
                <h3
                    className={cn(
                        'line-clamp-2 text-base font-medium',
                        soldOut && 'text-muted-foreground',
                    )}
                >
                    {item.name}
                </h3>
                <p className="num text-base font-semibold">
                    {formatRupiah(item.price)}
                    <span className="text-sm font-normal text-muted-foreground">
                        {' '}
                        / {item.unit}
                    </span>
                </p>
                <div className="mt-auto flex min-h-9 flex-wrap items-center justify-between gap-2 pointer-coarse:min-h-11">
                    {soldOut ? (
                        <LabelBadge icon={CircleX} label="Habis" />
                    ) : low ? (
                        <p className="text-sm font-medium text-warning">
                            Sisa {item.left} {item.unit}
                        </p>
                    ) : (
                        <span aria-hidden />
                    )}
                    {qty > 0 ? (
                        <QtyStepper
                            label={item.name}
                            value={qty}
                            min={0}
                            max={maxQty(item)}
                            onChange={onQty}
                            className="ml-auto"
                        />
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="ml-auto"
                            disabled={soldOut || !open || full}
                            onClick={() => onQty(1)}
                            aria-label={`${soldOut ? 'Habis:' : 'Tambah'} ${item.name}`}
                        >
                            <Plus aria-hidden />
                        </Button>
                    )}
                </div>
            </div>
        </li>
    );
}
