import { Money } from '@/components/app/money';
import { formatDate, formatQty } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { StockMovementRow } from '@/types/domain';
import { stockReasonLabels } from '@/types/enums';

type StockMovementListProps = {
    movements: StockMovementRow[];
    /**
     * "reason" leads with why stock moved, for one item's history.
     * "item" leads with the item, for a transaction that moves several.
     */
    by: 'reason' | 'item';
    /** Unit for movements that do not carry their own (one item's history). */
    unit?: string;
};

/** Stock changes with what is left after each, on an item and on a transaction. */
export function StockMovementList({
    movements,
    by,
    unit = '',
}: StockMovementListProps) {
    return (
        <ul className="flex flex-col divide-y">
            {movements.map((movement) => {
                const movementUnit = movement.unit ?? unit;
                const reason = stockReasonLabels[movement.reason];
                const left =
                    movement.stockAfter !== null &&
                    ` · sisa ${formatQty(movement.stockAfter, movementUnit)}`;

                return (
                    <li
                        key={movement.id}
                        className="flex items-baseline justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                    >
                        <div className="flex min-w-0 flex-col">
                            <span className="text-sm font-medium">
                                {by === 'item' ? movement.name : reason}
                            </span>
                            <span className="num text-xs text-muted-foreground">
                                {by === 'item'
                                    ? reason
                                    : formatDate(
                                          movement.occurredAt,
                                          'datetime',
                                      )}
                                {left}
                            </span>
                        </div>
                        <div className="flex flex-col items-end">
                            <span
                                className={cn(
                                    'num text-sm font-semibold',
                                    movement.stockAfter !== null &&
                                        movement.stockAfter < 0 &&
                                        'text-destructive',
                                )}
                            >
                                {movement.qty > 0 ? '+' : '−'}
                                {formatQty(
                                    Math.abs(movement.qty),
                                    movementUnit,
                                )}
                            </span>
                            <Money
                                value={movement.value}
                                size="sm"
                                className="text-xs font-normal text-muted-foreground"
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
