import { Info, Plus, X } from 'lucide-react';
import { Field } from '@/components/app/field';
import { MoneyInput } from '@/components/app/money-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { listEditor } from '@/lib/form';
import { MoneyAccountKind } from '@/types/enums';
import type { AccountRow } from './onboarding';

const newAccount = (): AccountRow => ({
    id: null,
    kind: MoneyAccountKind.NonTunai,
    name: '',
    balance: null,
});

/** Langkah 2: how much business money sits where, on the day the books open. */
export function MoneyStep({
    accounts,
    onChange,
    errors,
}: {
    accounts: AccountRow[];
    onChange: (accounts: AccountRow[]) => void;
    errors: Record<string, string | undefined>;
}) {
    const editor = listEditor(accounts, onChange, newAccount);

    return (
        <div className="flex flex-col gap-4">
            <p className="flex gap-2 rounded-lg bg-secondary px-3 py-2.5 text-sm">
                <Info
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-hidden
                />
                <span>
                    Hanya uang usaha. Jangan dicampur uang rumah tangga, supaya
                    laba dan modal terbaca benar.
                </span>
            </p>

            {accounts.map((account, index) => (
                <div
                    key={account.id ?? `baru-${index}`}
                    className="grid grid-cols-1 items-start gap-3 rounded-lg border p-3 sm:grid-cols-[1fr_12rem_auto]"
                >
                    <Field
                        label={
                            account.kind === MoneyAccountKind.Tunai
                                ? 'Uang tunai di'
                                : 'Rekening atau dompet digital'
                        }
                        htmlFor={`ob-account-${index}`}
                        error={errors[`accounts.${index}.name`]}
                    >
                        <Input
                            id={`ob-account-${index}`}
                            value={account.name}
                            onChange={(event) =>
                                editor.update(index, {
                                    name: event.target.value,
                                })
                            }
                            placeholder="BCA, GoPay, ShopeePay"
                            maxLength={60}
                            autoComplete="off"
                            aria-invalid={
                                Boolean(errors[`accounts.${index}.name`]) ||
                                undefined
                            }
                        />
                    </Field>
                    <div className="flex flex-col gap-1.5">
                        <MoneyInput
                            label={`Saldo ${account.name || 'rekening ini'}`}
                            value={account.balance}
                            onChange={(balance) =>
                                editor.update(index, { balance })
                            }
                            placeholder="0"
                        />
                        <InputError
                            message={errors[`accounts.${index}.balance`]}
                        />
                    </div>
                    {/* Rekening that already exist stay; only new ones can be taken out again. */}
                    {account.id === null ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="sm:mt-6"
                            onClick={() => editor.remove(index)}
                            aria-label={`Hapus ${account.name || 'rekening baru'}`}
                        >
                            <X aria-hidden />
                        </Button>
                    ) : (
                        <span className="hidden sm:block sm:w-9" aria-hidden />
                    )}
                </div>
            ))}
            <InputError message={errors.accounts} />
            <Button
                type="button"
                variant="outline"
                className="self-start"
                onClick={editor.add}
            >
                <Plus aria-hidden />
                Tambah rekening
            </Button>
        </div>
    );
}
