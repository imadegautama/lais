import { Plus, X } from 'lucide-react';
import { Field } from '@/components/app/field';
import { MoneyInput } from '@/components/app/money-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { listEditor } from '@/lib/form';
import { emptyAmountRow, type AmountRow } from './onboarding';

/**
 * Rows of a name and an amount (equipment, opening debts and receivables).
 * Optional: an empty list is fine, and empty rows are not sent.
 */
export function AmountRows({
    list,
    rows,
    onChange,
    errors,
    nameLabel,
    namePlaceholder,
    amountLabel,
    addLabel,
}: {
    /** The field the rows are sent as, for their errors: "equipment", "debts". */
    list: string;
    rows: AmountRow[];
    onChange: (rows: AmountRow[]) => void;
    errors: Record<string, string | undefined>;
    nameLabel: string;
    namePlaceholder: string;
    amountLabel: string;
    addLabel: string;
}) {
    const editor = listEditor(rows, onChange, emptyAmountRow);

    return (
        <div className="flex flex-col gap-3">
            {rows.map((row, index) => (
                <div
                    key={index}
                    className="grid grid-cols-[1fr_auto] items-start gap-x-2 gap-y-3 rounded-lg border p-3 sm:grid-cols-[1fr_12rem_auto]"
                >
                    <Field
                        label={nameLabel}
                        htmlFor={`${list}-${index}-name`}
                        error={errors[`${list}.${index}.name`]}
                    >
                        <Input
                            id={`${list}-${index}-name`}
                            value={row.name}
                            onChange={(event) =>
                                editor.update(index, {
                                    name: event.target.value,
                                })
                            }
                            placeholder={namePlaceholder}
                            autoComplete="off"
                            aria-invalid={
                                Boolean(errors[`${list}.${index}.name`]) ||
                                undefined
                            }
                        />
                    </Field>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="mt-6 sm:order-last"
                        onClick={() => editor.remove(index)}
                        aria-label={`Hapus ${row.name || 'baris ini'}`}
                    >
                        <X aria-hidden />
                    </Button>
                    <div className="col-span-1 flex flex-col gap-1.5 sm:col-span-1">
                        <MoneyInput
                            label={amountLabel}
                            value={row.amount}
                            onChange={(amount) =>
                                editor.update(index, { amount })
                            }
                        />
                        <InputError
                            message={errors[`${list}.${index}.amount`]}
                        />
                    </div>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                className="self-start"
                onClick={editor.add}
            >
                <Plus aria-hidden />
                {addLabel}
            </Button>
        </div>
    );
}
