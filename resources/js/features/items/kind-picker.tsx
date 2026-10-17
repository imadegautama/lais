import { itemKinds } from '@/components/app/badges';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';
import type { ItemKind } from '@/types/enums';
import { ITEM_KIND_ORDER, itemKindCopy } from './vocabulary';

/** The three item kinds as cards with a hint, chosen once when adding. */
export function KindPicker({
    value,
    onChange,
    error,
}: {
    value: ItemKind;
    onChange: (kind: ItemKind) => void;
    error?: string;
}) {
    return (
        <fieldset className="flex flex-col gap-2">
            <legend className="mb-2 text-sm font-medium">Jenis</legend>
            <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
                {ITEM_KIND_ORDER.map((kind) => {
                    const { label, icon: Icon } = itemKinds[kind];

                    return (
                        <label
                            key={kind}
                            className="flex cursor-pointer items-start gap-3 rounded-xl border bg-card p-3 shadow-card has-checked:border-primary has-checked:bg-accent has-focus-visible:ring-2 has-focus-visible:ring-ring"
                        >
                            <input
                                type="radio"
                                name="kind"
                                value={kind}
                                checked={value === kind}
                                onChange={() => onChange(kind)}
                                className="sr-only"
                            />
                            <Icon
                                className={cn(
                                    'mt-0.5 size-5 shrink-0',
                                    value === kind
                                        ? 'text-brand-strong'
                                        : 'text-muted-foreground',
                                )}
                                aria-hidden
                            />
                            <span className="flex flex-col gap-0.5">
                                <span className="text-sm font-semibold">
                                    {label}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {itemKindCopy[kind].hint}
                                </span>
                            </span>
                        </label>
                    );
                })}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}
