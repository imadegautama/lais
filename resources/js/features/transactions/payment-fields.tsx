import { methodLabel } from '@/components/app/badges';
import { ChoiceGroup } from '@/components/app/choice-group';
import { Field } from '@/components/app/field';
import { MoneyInput } from '@/components/app/money-input';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import type { ContactOption, MoneyAccountOption } from '@/types/domain';
import {
    MoneyAccountKind,
    PaymentMethod,
    TransactionKind,
    TransactionType,
    transactionTypeMethods,
} from '@/types/enums';
import { ContactField } from './contact-field';
import {
    contactFieldFor,
    isIncoming,
    namesEquipment,
    type RecordFieldSetter,
    type RecordFormData,
} from './record';
import { TransferFields } from './transfer-fields';
import { recordCopy } from './vocabulary';

/** How it was paid (or, for a transfer, between which rekening), by or to whom, when, and a note. */
export function PaymentFields({
    data,
    errors,
    setField,
    contacts,
    moneyAccounts,
    now,
}: {
    data: RecordFormData;
    errors: Record<string, string | undefined>;
    setField: RecordFieldSetter;
    contacts: ContactOption[];
    moneyAccounts: MoneyAccountOption[];
    /** Latest allowed time, from the server (WIB). */
    now: string;
}) {
    const { type, kind, method } = data;
    const contactField = contactFieldFor(type, kind, method);
    const isEquipment = namesEquipment(type, kind);
    const nonCash = moneyAccounts.filter(
        (account) => account.kind === MoneyAccountKind.NonTunai,
    );

    return (
        <div className="flex flex-col gap-4">
            {type === TransactionType.PindahDana ? (
                <TransferFields
                    data={data}
                    errors={errors}
                    setField={setField}
                    moneyAccounts={moneyAccounts}
                />
            ) : type === TransactionType.SaldoAwal ? (
                kind === TransactionKind.SaldoKas && (
                    <div className="flex flex-col gap-1.5">
                        <span
                            id="tx-account-label"
                            className="text-sm font-medium"
                        >
                            Rekening
                        </span>
                        <ChoiceGroup
                            labelledBy="tx-account-label"
                            value={data.money_account_id}
                            options={moneyAccounts.map((account) => ({
                                value: String(account.id),
                                label: account.name,
                            }))}
                            onChange={(id) => setField('money_account_id', id)}
                            wrap
                        />
                        <InputError message={errors.money_account_id} />
                    </div>
                )
            ) : (
                <MethodFields
                    data={data}
                    errors={errors}
                    setField={setField}
                    nonCash={nonCash}
                />
            )}

            {contactField && (
                <ContactField
                    {...contactField}
                    value={data.contact}
                    contacts={contacts}
                    error={errors.contact_id ?? errors.contact_name}
                    onChange={(contact) => {
                        setField('contact', contact);
                        const chosen = contacts.find(
                            (option) => String(option.id) === contact,
                        );

                        // Paying off a balance: default to everything still owed.
                        if (type === TransactionType.UtangPiutang && chosen) {
                            setField('amount', chosen.balance);
                        }
                    }}
                />
            )}

            {type === TransactionType.Penjualan &&
                method === PaymentMethod.Kredit && (
                    <div className="flex flex-col gap-1.5">
                        <MoneyInput
                            label="Sudah dibayar (boleh kosong)"
                            value={data.paid_amount}
                            onChange={(value) => setField('paid_amount', value)}
                            placeholder="0"
                        />
                        <InputError message={errors.paid_amount} />
                    </div>
                )}

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Field
                    label="Waktu"
                    htmlFor="tx-time"
                    error={errors.occurred_at}
                    hint="Ganti kalau mencatat transaksi yang sudah lewat."
                >
                    <Input
                        id="tx-time"
                        type="datetime-local"
                        max={now}
                        value={data.occurred_at}
                        onChange={(e) =>
                            setField('occurred_at', e.target.value)
                        }
                        aria-invalid={Boolean(errors.occurred_at) || undefined}
                    />
                </Field>
                {/* Equipment asks for its name in the details card instead. */}
                {!isEquipment && (
                    <Field
                        label="Catatan (boleh kosong)"
                        htmlFor="tx-note"
                        error={errors.note}
                    >
                        <Input
                            id="tx-note"
                            value={data.note}
                            onChange={(e) => setField('note', e.target.value)}
                            placeholder={recordCopy[type].notePlaceholder}
                            autoComplete="off"
                        />
                    </Field>
                )}
            </div>
        </div>
    );
}

/**
 * Tunai, non-tunai, credit, or personal money. Non-tunai asks which rekening
 * only when there is more than one; the server takes the only one otherwise.
 */
function MethodFields({
    data,
    errors,
    setField,
    nonCash,
}: {
    data: RecordFormData;
    errors: Record<string, string | undefined>;
    setField: RecordFieldSetter;
    nonCash: MoneyAccountOption[];
}) {
    const { type, kind, method } = data;

    return (
        <>
            <div className="flex flex-col gap-1.5">
                <span id="tx-method-label" className="text-sm font-medium">
                    {isIncoming(type, kind) ? 'Diterima lewat' : 'Dibayar dari'}
                </span>
                <ChoiceGroup
                    labelledBy="tx-method-label"
                    value={method}
                    options={transactionTypeMethods[type].map((option) => ({
                        value: option,
                        label: methodLabel(type, option),
                    }))}
                    onChange={(option) => setField('method', option)}
                />
                <InputError message={errors.method} />
            </div>

            {method === PaymentMethod.NonTunai && nonCash.length > 1 && (
                <div className="flex flex-col gap-1.5">
                    <span id="tx-account-label" className="text-sm font-medium">
                        Rekening
                    </span>
                    <ChoiceGroup
                        labelledBy="tx-account-label"
                        value={data.money_account_id}
                        options={nonCash.map((account) => ({
                            value: String(account.id),
                            label: account.name,
                        }))}
                        onChange={(id) => setField('money_account_id', id)}
                        wrap
                    />
                    <InputError message={errors.money_account_id} />
                </div>
            )}
        </>
    );
}
