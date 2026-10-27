import { Head, Link, useForm } from '@inertiajs/react';
import AlertError from '@/components/alert-error';
import { AssistantFilledNote } from '@/components/app/assistant/assistant-panel';
import { useAssistantForm } from '@/components/app/assistant/assistant-provider';
import { CategoryBadge } from '@/components/app/badges';
import { ChoiceGroup } from '@/components/app/choice-group';
import { Field } from '@/components/app/field';
import { FormActionBar } from '@/components/app/form-action-bar';
import { MoneyInput } from '@/components/app/money-input';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ImpactPanel } from '@/features/transactions/impact-panel';
import { ItemLines } from '@/features/transactions/item-lines';
import { PaymentFields } from '@/features/transactions/payment-fields';
import {
    DEFAULT_KIND,
    emptyLine,
    hasLines,
    namesEquipment,
    recordTotal,
    toPayload,
    type RecordFormData,
} from '@/features/transactions/record';
import { TypeTabs } from '@/features/transactions/type-tabs';
import { recordCopy } from '@/features/transactions/vocabulary';
import { fieldSetter, focusFirstError, formErrors } from '@/lib/form';
import { index, show, store, update } from '@/routes/transactions';
import type { TransactionCreateProps } from '@/types/domain';
import {
    PaymentMethod,
    TransactionKind,
    TransactionType,
    transactionKindLabels,
    transactionTypeKinds,
    transactionTypeLabels,
} from '@/types/enums';

export default function TransactionCreate({
    initialType,
    now,
    items,
    contacts,
    moneyAccounts,
    editing,
    initial,
}: TransactionCreateProps) {
    const form = useForm<RecordFormData>(
        initial ?? {
            type: initialType,
            kind: DEFAULT_KIND[initialType],
            method: PaymentMethod.Tunai,
            occurred_at: now,
            lines: [emptyLine()],
            amount: null,
            paid_amount: null,
            contact: '',
            note: '',
            money_account_id: '',
            to_money_account_id: '',
        },
    );
    const { data, processing } = form;
    const errors = formErrors(form);
    const setField = fieldSetter(form);
    const { type } = data;
    const withLines = hasLines(type, data.kind);
    const isEquipment = namesEquipment(type, data.kind);
    const kinds = transactionTypeKinds[type];
    const total = recordTotal(data);
    const contactIds = new Set(contacts.map((contact) => String(contact.id)));
    // Edits and the saldo awal stay by hand; from there the assistant opens a new form.
    const filledByAssistant = useAssistantForm(
        'transaksi',
        data,
        (values) => {
            form.setData(values);
            form.clearErrors();
        },
        !editing && initialType !== TransactionType.SaldoAwal,
    );

    const submit =
        (again: boolean) => (event?: { preventDefault: () => void }) => {
            event?.preventDefault();
            form.transform((values) => toPayload(values, again, contactIds));
            form.submit(editing ? update(editing.id) : store(), {
                preserveScroll: true,
                // Keep what was typed on errors; start fresh after "Simpan & catat lagi".
                preserveState: 'errors',
                onError: focusFirstError,
            });
        };

    return (
        <>
            <Head
                title={
                    editing
                        ? 'Ubah catatan'
                        : `Catat ${transactionTypeLabels[type].toLowerCase()}`
                }
            />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                {editing ? (
                    // The type stays: a record of the wrong type is cancelled and recorded anew.
                    <PageHeader
                        title="Ubah catatan"
                        meta={<CategoryBadge type={type} />}
                        description="Saat disimpan, catatan lama dibatalkan dan diganti yang baru. Riwayatnya tetap bisa dilihat."
                    />
                ) : type === TransactionType.SaldoAwal ? (
                    // Not an everyday record, so it has no tab of its own.
                    <PageHeader
                        title="Catat saldo awal"
                        meta={<CategoryBadge type={type} />}
                        description="Uang, peralatan, dan utang-piutang yang sudah ada saat kamu mulai mencatat. Modal awal dihitung dari selisihnya."
                    />
                ) : (
                    <>
                        <PageHeader
                            title="Catat transaksi"
                            description="Stok, kas, dan laba langsung dihitung saat disimpan."
                        />
                        <TypeTabs current={type} />
                    </>
                )}

                <form
                    key={filledByAssistant}
                    onSubmit={submit(false)}
                    noValidate
                    className="flex flex-1 flex-col gap-4 md:gap-6"
                >
                    {filledByAssistant > 0 && <AssistantFilledNote />}
                    <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                        <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-7">
                            {kinds.length > 0 && (
                                <SectionCard
                                    title={recordCopy[type].kindTitle}
                                    contentClassName="pt-3"
                                >
                                    <ChoiceGroup
                                        label={recordCopy[type].kindTitle}
                                        value={data.kind}
                                        options={kinds.map((kind) => ({
                                            value: kind,
                                            label: transactionKindLabels[kind],
                                        }))}
                                        onChange={(kind) => {
                                            setField('kind', kind);
                                            setField('contact', '');
                                        }}
                                        wrap={type === TransactionType.Biaya}
                                    />
                                    <InputError
                                        message={errors.kind}
                                        className="mt-2"
                                    />
                                </SectionCard>
                            )}

                            <SectionCard
                                title={
                                    withLines
                                        ? type === TransactionType.Penjualan
                                            ? 'Yang terjual'
                                            : 'Yang dibeli'
                                        : 'Rincian'
                                }
                            >
                                <div className="flex flex-col gap-4">
                                    {withLines ? (
                                        <ItemLines
                                            mode={
                                                type ===
                                                TransactionType.Penjualan
                                                    ? 'sale'
                                                    : 'purchase'
                                            }
                                            lines={data.lines}
                                            items={items}
                                            errors={errors}
                                            onChange={(lines) =>
                                                setField('lines', lines)
                                            }
                                        />
                                    ) : (
                                        <div className="flex flex-col gap-1.5">
                                            <MoneyInput
                                                key={`${type}-${data.kind}-${data.contact}`}
                                                label={
                                                    data.kind ===
                                                    TransactionKind.SaldoPeralatan
                                                        ? 'Perkiraan nilai sekarang'
                                                        : isEquipment
                                                          ? 'Harga peralatan'
                                                          : 'Nominal'
                                                }
                                                value={data.amount}
                                                onChange={(value) =>
                                                    setField('amount', value)
                                                }
                                            />
                                            <InputError
                                                message={errors.amount}
                                            />
                                        </div>
                                    )}

                                    {isEquipment && (
                                        <Field
                                            label="Nama peralatan"
                                            htmlFor="tx-note"
                                            error={errors.note}
                                        >
                                            <Input
                                                id="tx-note"
                                                value={data.note}
                                                onChange={(e) =>
                                                    setField(
                                                        'note',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Kompor dua tungku"
                                                aria-invalid={
                                                    Boolean(errors.note) ||
                                                    undefined
                                                }
                                                autoComplete="off"
                                            />
                                        </Field>
                                    )}
                                </div>
                            </SectionCard>

                            <SectionCard title={recordCopy[type].paymentTitle}>
                                <PaymentFields
                                    data={data}
                                    errors={errors}
                                    setField={setField}
                                    contacts={contacts}
                                    moneyAccounts={moneyAccounts}
                                    now={now}
                                />
                            </SectionCard>
                        </div>

                        <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-5">
                            <ImpactPanel
                                data={data}
                                total={total}
                                items={
                                    new Map(
                                        items.map((item) => [
                                            String(item.id),
                                            item,
                                        ]),
                                    )
                                }
                                contact={
                                    contacts.find(
                                        (contact) =>
                                            String(contact.id) === data.contact,
                                    ) ?? null
                                }
                                moneyAccounts={moneyAccounts}
                            />
                        </div>
                    </div>

                    {/* Refusals that belong to no field, e.g. payments already made against the old record. */}
                    {errors.transaction && (
                        <AlertError
                            title="Perubahan belum disimpan"
                            errors={[errors.transaction]}
                        />
                    )}

                    <FormActionBar>
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Menyimpan…'
                                : editing
                                  ? 'Simpan perubahan'
                                  : recordCopy[type].save}
                        </Button>
                        {!editing && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={submit(true)}
                            >
                                Simpan & catat lagi
                            </Button>
                        )}
                        {/* Phones leave with the back gesture or the bottom nav; the bar stays one row. */}
                        <Button
                            type="button"
                            variant="ghost"
                            asChild
                            className={
                                editing
                                    ? 'lg:ml-auto'
                                    : 'hidden sm:inline-flex lg:ml-auto'
                            }
                        >
                            <Link href={editing ? show(editing.id) : index()}>
                                Batal
                            </Link>
                        </Button>
                    </FormActionBar>
                </form>
            </div>
        </>
    );
}

TransactionCreate.layout = {
    breadcrumbs: [{ title: 'Transaksi', href: index() }],
};
