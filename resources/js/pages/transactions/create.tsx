import { Head, Link, useForm } from '@inertiajs/react';
import type { FormDataValues } from '@inertiajs/core';
import { Info, Plus, X } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import {
    methodLabel,
    transactionKinds,
    transactionTypes,
} from '@/components/app/badges';
import { Field } from '@/components/app/field';
import { FormActionBar } from '@/components/app/form-action-bar';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { MoneyInput } from '@/components/app/money-input';
import { PageHeader } from '@/components/app/page-header';
import { QtyStepper } from '@/components/app/qty-stepper';
import {
    SearchSelect,
    type SearchSelectOption,
} from '@/components/app/search-select';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatQty, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create as createItem } from '@/routes/items';
import { create, index, store } from '@/routes/transactions';
import type {
    ContactOption,
    PaymentMethod,
    RecordItem,
    TransactionCreateProps,
    TransactionKind,
    TransactionType,
} from '@/types/domain';

type Line = { item_id: string; qty: string; price: number | null };

type RecordFormData = {
    type: TransactionType;
    kind: TransactionKind | '';
    method: PaymentMethod;
    occurred_at: string;
    lines: Line[];
    amount: number | null;
    paid_amount: number | null;
    /** A contact id, or a new name typed into the field. */
    contact: string;
    note: string;
};

// Mirrors Transaction::KINDS and Transaction::METHODS on the server.
const KINDS: Partial<Record<TransactionType, TransactionKind[]>> = {
    pembelian: ['barang', 'peralatan'],
    biaya: [
        'listrik_air',
        'gas_bbm',
        'sewa',
        'upah',
        'transport',
        'pemasaran',
        'lain',
    ],
    utang_piutang: ['terima_piutang', 'bayar_utang'],
    modal: ['setor', 'prive'],
};

const METHODS: Record<TransactionType, PaymentMethod[]> = {
    penjualan: ['tunai', 'non_tunai', 'kredit'],
    pembelian: ['tunai', 'non_tunai', 'kredit', 'pribadi'],
    biaya: ['tunai', 'non_tunai', 'kredit', 'pribadi'],
    utang_piutang: ['tunai', 'non_tunai'],
    modal: ['tunai', 'non_tunai'],
};

// Expense kind is left empty on purpose: the owner picks it, nothing is guessed.
const DEFAULT_KIND: Record<TransactionType, TransactionKind | ''> = {
    penjualan: '',
    pembelian: 'barang',
    biaya: '',
    utang_piutang: 'terima_piutang',
    modal: 'setor',
};

const SAVE_LABEL: Record<TransactionType, string> = {
    penjualan: 'Simpan penjualan',
    pembelian: 'Simpan belanja',
    biaya: 'Simpan biaya',
    utang_piutang: 'Simpan pembayaran',
    modal: 'Simpan',
};

/** "0,15" and "0.15" are the same amount. NaN for anything else. */
const toNumber = (text: string) => Number(text.trim().replace(',', '.'));

const emptyLine = (): Line => ({ item_id: '', qty: '1', price: null });

const hasLines = (type: TransactionType, kind: string) =>
    type === 'penjualan' || (type === 'pembelian' && kind === 'barang');

export default function TransactionCreate({
    initialType,
    now,
    items,
    contacts,
}: TransactionCreateProps) {
    const form = useForm<RecordFormData>({
        type: initialType,
        kind: DEFAULT_KIND[initialType],
        method: 'tunai',
        occurred_at: now,
        lines: [emptyLine()],
        amount: null,
        paid_amount: null,
        contact: '',
        note: '',
    });
    const { data, processing } = form;
    const errors = form.errors as Record<string, string | undefined>;
    const type = data.type;
    const withLines = hasLines(type, data.kind);
    const itemById = new Map(items.map((item) => [String(item.id), item]));
    const contactById = new Map(contacts.map((c) => [String(c.id), c]));

    const setData = <K extends keyof RecordFormData>(
        key: K,
        value: FormDataValues<RecordFormData, K>,
    ) => {
        form.setData(key, value);
        // An error goes away as soon as its field is edited.
        const stale = Object.keys(form.errors).filter(
            (name) => name === key || name.startsWith(`${key}.`),
        );

        if (stale.length > 0) {
            form.clearErrors(...(stale as Array<keyof RecordFormData>));
        }
    };

    const total = withLines
        ? data.lines.reduce(
              (sum, line) =>
                  sum +
                  Math.round((toNumber(line.qty) || 0) * (line.price ?? 0)),
              0,
          )
        : (data.amount ?? 0);

    const submit = (again: boolean) => (event?: FormEvent) => {
        event?.preventDefault();
        form.transform((values) => {
            const known = contactById.has(values.contact);
            const typed = values.contact.trim();

            return {
                type: values.type,
                ...(values.type === 'penjualan' ? {} : { kind: values.kind }),
                method: values.method,
                occurred_at: values.occurred_at || null,
                note: values.note.trim() || null,
                again,
                ...(hasLines(values.type, values.kind)
                    ? {
                          lines: values.lines.map((line) => ({
                              item_id:
                                  line.item_id === ''
                                      ? null
                                      : Number(line.item_id),
                              qty:
                                  line.qty.trim() === ''
                                      ? null
                                      : toNumber(line.qty),
                              price: line.price,
                          })),
                      }
                    : { amount: values.amount }),
                ...(values.type === 'penjualan' && values.method === 'kredit'
                    ? { paid_amount: values.paid_amount ?? 0 }
                    : {}),
                ...(known
                    ? { contact_id: Number(values.contact) }
                    : typed !== '' &&
                        !['modal', 'utang_piutang'].includes(values.type)
                      ? { contact_name: typed }
                      : {}),
            };
        });
        form.submit(store(), {
            preserveScroll: true,
            // Keep what was typed on errors; start fresh after "Simpan & catat lagi".
            preserveState: 'errors',
            onError: () =>
                requestAnimationFrame(() =>
                    document
                        .querySelector<HTMLElement>('[aria-invalid="true"]')
                        ?.focus(),
                ),
        });
    };

    const kinds = KINDS[type] ?? [];
    const contactField = contactFieldFor(type, data.kind, data.method);

    return (
        <>
            <Head
                title={`Catat ${transactionTypes[type].label.toLowerCase()}`}
            />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Catat transaksi"
                    description="Stok, kas, dan laba langsung dihitung saat disimpan."
                />

                <TypeTabs current={type} />

                <form
                    onSubmit={submit(false)}
                    noValidate
                    className="flex flex-1 flex-col gap-4 md:gap-6"
                >
                    <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                        <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-7">
                            {kinds.length > 0 && (
                                <SectionCard
                                    title={kindTitle(type)}
                                    contentClassName="pt-3"
                                >
                                    <ChoiceGroup
                                        label={kindTitle(type)}
                                        value={data.kind}
                                        options={kinds.map((kind) => ({
                                            value: kind,
                                            label: transactionKinds[kind],
                                        }))}
                                        onChange={(kind) => {
                                            setData(
                                                'kind',
                                                kind as TransactionKind,
                                            );
                                            setData('contact', '');
                                        }}
                                        wrap={type === 'biaya'}
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
                                        ? type === 'penjualan'
                                            ? 'Yang terjual'
                                            : 'Yang dibeli'
                                        : 'Rincian'
                                }
                            >
                                <div className="flex flex-col gap-4">
                                    {withLines ? (
                                        <ItemLines
                                            mode={
                                                type === 'penjualan'
                                                    ? 'sale'
                                                    : 'purchase'
                                            }
                                            lines={data.lines}
                                            items={items}
                                            errors={errors}
                                            onChange={(lines) =>
                                                setData('lines', lines)
                                            }
                                        />
                                    ) : (
                                        <div className="flex flex-col gap-1.5">
                                            <MoneyInput
                                                key={`${type}-${data.kind}-${data.contact}`}
                                                label={
                                                    type === 'pembelian'
                                                        ? 'Harga peralatan'
                                                        : 'Nominal'
                                                }
                                                value={data.amount}
                                                onChange={(value) =>
                                                    setData('amount', value)
                                                }
                                            />
                                            <InputError
                                                message={errors.amount}
                                            />
                                        </div>
                                    )}

                                    {type === 'pembelian' &&
                                        data.kind === 'peralatan' && (
                                            <Field
                                                label="Nama peralatan"
                                                htmlFor="tx-note"
                                                error={errors.note}
                                            >
                                                <Input
                                                    id="tx-note"
                                                    value={data.note}
                                                    onChange={(e) =>
                                                        setData(
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

                            <SectionCard title="Pembayaran">
                                <div className="flex flex-col gap-4">
                                    <div className="flex flex-col gap-1.5">
                                        <span
                                            id="tx-method-label"
                                            className="text-sm font-medium"
                                        >
                                            {type === 'penjualan' ||
                                            data.kind === 'terima_piutang' ||
                                            data.kind === 'setor'
                                                ? 'Diterima lewat'
                                                : 'Dibayar dari'}
                                        </span>
                                        <ChoiceGroup
                                            labelledBy="tx-method-label"
                                            value={data.method}
                                            options={METHODS[type].map(
                                                (method) => ({
                                                    value: method,
                                                    label: methodLabel(
                                                        type,
                                                        method,
                                                    ),
                                                }),
                                            )}
                                            onChange={(method) =>
                                                setData(
                                                    'method',
                                                    method as PaymentMethod,
                                                )
                                            }
                                        />
                                        <InputError message={errors.method} />
                                    </div>

                                    {contactField && (
                                        <ContactField
                                            {...contactField}
                                            value={data.contact}
                                            contacts={contacts}
                                            error={
                                                errors.contact_id ??
                                                errors.contact_name
                                            }
                                            onChange={(contact) => {
                                                setData('contact', contact);
                                                const chosen =
                                                    contactById.get(contact);

                                                // Paying off a balance: default to everything still owed.
                                                if (
                                                    type === 'utang_piutang' &&
                                                    chosen
                                                ) {
                                                    setData(
                                                        'amount',
                                                        chosen.balance,
                                                    );
                                                }
                                            }}
                                        />
                                    )}

                                    {type === 'penjualan' &&
                                        data.method === 'kredit' && (
                                            <div className="flex flex-col gap-1.5">
                                                <MoneyInput
                                                    label="Sudah dibayar (boleh kosong)"
                                                    value={data.paid_amount}
                                                    onChange={(value) =>
                                                        setData(
                                                            'paid_amount',
                                                            value,
                                                        )
                                                    }
                                                    placeholder="0"
                                                />
                                                <InputError
                                                    message={errors.paid_amount}
                                                />
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
                                                    setData(
                                                        'occurred_at',
                                                        e.target.value,
                                                    )
                                                }
                                                aria-invalid={
                                                    Boolean(
                                                        errors.occurred_at,
                                                    ) || undefined
                                                }
                                            />
                                        </Field>
                                        {!(
                                            type === 'pembelian' &&
                                            data.kind === 'peralatan'
                                        ) && (
                                            <Field
                                                label="Catatan (boleh kosong)"
                                                htmlFor="tx-note"
                                                error={errors.note}
                                            >
                                                <Input
                                                    id="tx-note"
                                                    value={data.note}
                                                    onChange={(e) =>
                                                        setData(
                                                            'note',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder={notePlaceholder(
                                                        type,
                                                    )}
                                                    autoComplete="off"
                                                />
                                            </Field>
                                        )}
                                    </div>
                                </div>
                            </SectionCard>
                        </div>

                        <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-5">
                            <ImpactPanel
                                data={data}
                                total={total}
                                items={itemById}
                                contact={contactById.get(data.contact) ?? null}
                            />
                        </div>
                    </div>

                    <FormActionBar>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Menyimpan…' : SAVE_LABEL[type]}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={submit(true)}
                        >
                            Simpan & catat lagi
                        </Button>
                        {/* Phones leave with the back gesture or the bottom nav; the bar stays one row. */}
                        <Button
                            type="button"
                            variant="ghost"
                            asChild
                            className="hidden sm:inline-flex lg:ml-auto"
                        >
                            <Link href={index()}>Batal</Link>
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

function kindTitle(type: TransactionType): string {
    return {
        penjualan: '',
        pembelian: 'Yang dibeli',
        biaya: 'Jenis biaya',
        utang_piutang: 'Jenis pembayaran',
        modal: 'Jenis catatan modal',
    }[type];
}

function notePlaceholder(type: TransactionType): string {
    return {
        penjualan: 'Pesanan meja 3',
        pembelian: 'Belanja di pasar',
        biaya: 'Token bulan Oktober',
        utang_piutang: 'Cicilan pertama',
        modal: 'Buat bayar sekolah',
    }[type];
}

/** Which contact field a type shows, if any. */
function contactFieldFor(
    type: TransactionType,
    kind: string,
    method: PaymentMethod,
): Pick<
    ContactFieldProps,
    'label' | 'contactType' | 'required' | 'creatable' | 'onlyOwing'
> | null {
    if (type === 'utang_piutang') {
        const customer = kind === 'terima_piutang';

        return {
            label: customer
                ? 'Pelanggan yang membayar'
                : 'Supplier yang dibayar',
            contactType: customer ? 'pelanggan' : 'supplier',
            required: true,
            creatable: false,
            onlyOwing: true,
        };
    }

    if (type === 'penjualan') {
        return method === 'kredit'
            ? {
                  label: 'Pelanggan yang belum bayar',
                  contactType: 'pelanggan',
                  required: true,
                  creatable: true,
                  onlyOwing: false,
              }
            : null;
    }

    if (type === 'pembelian' || (type === 'biaya' && method === 'kredit')) {
        return {
            label:
                method === 'kredit'
                    ? 'Supplier yang belum dibayar'
                    : 'Supplier (boleh kosong)',
            contactType: 'supplier',
            required: method === 'kredit',
            creatable: true,
            onlyOwing: false,
        };
    }

    return null;
}

function TypeTabs({ current }: { current: TransactionType }) {
    return (
        <nav aria-label="Jenis catatan" className="flex flex-wrap gap-1.5">
            {(Object.keys(transactionTypes) as TransactionType[]).map(
                (type) => {
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
                },
            )}
        </nav>
    );
}

/** One choice from a short, fixed list (payment method, kind). */
function ChoiceGroup({
    label,
    labelledBy,
    value,
    options,
    onChange,
    wrap = false,
}: {
    label?: string;
    labelledBy?: string;
    value: string;
    options: Array<{ value: string; label: string }>;
    onChange: (value: string) => void;
    wrap?: boolean;
}) {
    return (
        <ToggleGroup
            type="single"
            variant="outline"
            value={value}
            onValueChange={(next) => next && onChange(next)}
            aria-label={label}
            aria-labelledby={labelledBy}
            className={cn(
                'w-full gap-1.5',
                wrap
                    ? 'flex-wrap justify-start'
                    : 'grid grid-cols-2 sm:auto-cols-fr sm:grid-flow-col',
            )}
        >
            {options.map((option) => (
                <ToggleGroupItem
                    key={option.value}
                    value={option.value}
                    className="h-auto min-h-9 rounded-md! border-l! bg-card px-3 py-1.5 text-sm whitespace-normal data-[state=on]:border-primary data-[state=on]:bg-accent data-[state=on]:font-semibold data-[state=on]:text-accent-foreground pointer-coarse:min-h-11"
                >
                    {option.label}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}

function ItemLines({
    mode,
    lines,
    items,
    errors,
    onChange,
}: {
    mode: 'sale' | 'purchase';
    lines: Line[];
    items: RecordItem[];
    errors: Record<string, string | undefined>;
    onChange: (lines: Line[]) => void;
}) {
    const choices = items.filter((item) =>
        mode === 'sale' ? item.kind !== 'bahan' : item.kind !== 'komposisi',
    );
    const byId = new Map(choices.map((item) => [String(item.id), item]));
    const setLine = (index: number, patch: Partial<Line>) =>
        onChange(
            lines.map((line, i) =>
                i === index ? { ...line, ...patch } : line,
            ),
        );

    if (choices.length === 0) {
        return (
            <EmptyState
                title={
                    mode === 'sale'
                        ? 'Belum ada produk yang dijual'
                        : 'Belum ada bahan atau produk'
                }
                description="Tambahkan dulu di Produk & stok, lalu kembali ke sini."
                action={
                    <Button variant="outline" size="sm" asChild>
                        <Link
                            href={createItem({
                                query: {
                                    jenis: mode === 'sale' ? 'produk' : 'bahan',
                                },
                            })}
                        >
                            Tambah produk
                        </Link>
                    </Button>
                }
            />
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <InputError message={errors.lines} />
            <ol className="flex flex-col gap-3">
                {lines.map((line, index) => {
                    const item = byId.get(line.item_id);
                    const qty = toNumber(line.qty) || 0;
                    const lineError =
                        errors[`lines.${index}.item_id`] ??
                        errors[`lines.${index}.qty`] ??
                        errors[`lines.${index}.price`];

                    return (
                        <li
                            key={index}
                            className="flex flex-col gap-3 rounded-lg border p-3"
                        >
                            <div className="flex items-end gap-2">
                                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                    <Label htmlFor={`line-${index}-item`}>
                                        Barang
                                    </Label>
                                    <SearchSelect
                                        id={`line-${index}-item`}
                                        value={line.item_id}
                                        onChange={(value) => {
                                            const chosen = byId.get(value);
                                            setLine(index, {
                                                item_id: value,
                                                price:
                                                    mode === 'sale'
                                                        ? (chosen?.price ??
                                                          null)
                                                        : chosen &&
                                                            chosen.averageCost >
                                                                0
                                                          ? Math.round(
                                                                chosen.averageCost,
                                                            )
                                                          : null,
                                            });
                                        }}
                                        options={choices.map((choice) =>
                                            itemOption(
                                                choice,
                                                mode,
                                                lines,
                                                index,
                                            ),
                                        )}
                                        placeholder="Pilih barang"
                                        searchPlaceholder="Cari nama atau alias"
                                        emptyText="Tidak ditemukan. Tambahkan dulu di Produk & stok."
                                        invalid={Boolean(
                                            errors[`lines.${index}.item_id`],
                                        )}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() =>
                                        onChange(
                                            lines.filter((_, i) => i !== index),
                                        )
                                    }
                                    disabled={lines.length === 1}
                                    aria-label={`Hapus ${item?.name ?? `baris ${index + 1}`}`}
                                >
                                    <X aria-hidden />
                                </Button>
                            </div>
                            <div className="grid grid-cols-1 items-start gap-3 sm:grid-cols-[auto_minmax(0,1fr)]">
                                <div className="flex flex-col gap-1.5">
                                    <span className="text-sm font-medium">
                                        Jumlah
                                    </span>
                                    {mode === 'sale' ? (
                                        <QtyStepper
                                            label={item?.name ?? 'barang'}
                                            value={qty}
                                            min={0}
                                            step={
                                                item &&
                                                ['kg', 'liter'].includes(
                                                    item.unit,
                                                )
                                                    ? 0.1
                                                    : 1
                                            }
                                            unit={item?.unit}
                                            onChange={(value) =>
                                                setLine(index, {
                                                    qty: String(value),
                                                })
                                            }
                                        />
                                    ) : (
                                        <div className="relative">
                                            <Input
                                                aria-label={`Jumlah ${item?.name ?? 'barang'}`}
                                                inputMode="decimal"
                                                value={line.qty}
                                                onChange={(e) =>
                                                    setLine(index, {
                                                        qty: e.target.value,
                                                    })
                                                }
                                                aria-invalid={
                                                    Boolean(
                                                        errors[
                                                            `lines.${index}.qty`
                                                        ],
                                                    ) || undefined
                                                }
                                                className="num w-full pr-16 sm:w-40"
                                                autoComplete="off"
                                            />
                                            <span
                                                aria-hidden
                                                className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground sm:right-auto sm:left-28"
                                            >
                                                {item?.unit ?? ''}
                                            </span>
                                        </div>
                                    )}
                                </div>
                                <MoneyInput
                                    key={`${index}-${line.item_id}`}
                                    label={
                                        mode === 'sale'
                                            ? `Harga per ${item?.unit ?? 'satuan'}`
                                            : `Harga beli per ${item?.unit ?? 'satuan'}`
                                    }
                                    value={line.price}
                                    onChange={(value) =>
                                        setLine(index, { price: value })
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between gap-3 border-t pt-2 text-sm">
                                <span className="text-muted-foreground">
                                    Subtotal
                                </span>
                                <Money
                                    value={Math.round(qty * (line.price ?? 0))}
                                    size="sm"
                                />
                            </div>
                            <InputError message={lineError} />
                        </li>
                    );
                })}
            </ol>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="w-fit"
                onClick={() => onChange([...lines, emptyLine()])}
                disabled={lines.length >= choices.length}
            >
                <Plus aria-hidden />
                Tambah barang
            </Button>
        </div>
    );
}

function itemOption(
    item: RecordItem,
    mode: 'sale' | 'purchase',
    lines: Line[],
    index: number,
): SearchSelectOption {
    const description =
        mode === 'sale'
            ? `${formatRupiah(item.price ?? 0)} · ${item.kind === 'komposisi' ? 'dari resep' : `stok ${formatQty(item.stock, item.unit)}`}`
            : item.averageCost > 0
              ? `${formatRupiah(Math.round(item.averageCost))}/${item.unit}`
              : `stok ${formatQty(item.stock, item.unit)}`;

    return {
        value: String(item.id),
        label: item.name,
        description,
        keywords: item.aliases,
        group:
            item.kind === 'bahan'
                ? 'Bahan baku'
                : item.kind === 'komposisi'
                  ? 'Produk resep'
                  : 'Produk biasa',
        disabled: lines.some(
            (other, i) => i !== index && other.item_id === String(item.id),
        ),
    };
}

type ContactFieldProps = {
    label: string;
    contactType: ContactOption['type'];
    required: boolean;
    creatable: boolean;
    /** Paying off: only contacts with something still open. */
    onlyOwing: boolean;
    value: string;
    contacts: ContactOption[];
    error?: string;
    onChange: (value: string) => void;
};

function ContactField({
    label,
    contactType,
    creatable,
    onlyOwing,
    value,
    contacts,
    error,
    onChange,
}: ContactFieldProps) {
    const options = contacts
        .filter(
            (contact) =>
                contact.type === contactType &&
                (!onlyOwing || contact.balance > 0),
        )
        .map((contact) => ({
            value: String(contact.id),
            label: contact.name,
            description:
                contact.balance > 0
                    ? `sisa ${formatRupiah(contact.balance)}`
                    : undefined,
        }));

    return (
        <Field
            label={label}
            htmlFor="tx-contact"
            error={error}
            hint={
                creatable
                    ? 'Belum ada di daftar? Ketik namanya untuk menambahkan.'
                    : options.length === 0
                      ? contactType === 'pelanggan'
                          ? 'Belum ada pelanggan yang punya tagihan.'
                          : 'Belum ada utang ke supplier.'
                      : undefined
            }
        >
            <SearchSelect
                id="tx-contact"
                value={value}
                onChange={onChange}
                options={options}
                placeholder={
                    contactType === 'pelanggan'
                        ? 'Pilih pelanggan'
                        : 'Pilih supplier'
                }
                searchPlaceholder="Cari nama"
                emptyText={
                    creatable
                        ? 'Ketik nama untuk menambahkan.'
                        : 'Tidak ada yang punya tagihan.'
                }
                creatable={creatable ? (name) => `Tambah “${name}”` : undefined}
                invalid={Boolean(error)}
            />
        </Field>
    );
}

/**
 * What saving will change, computed the same way as the server
 * (App\Services\Inventory value rules), so the owner sees it before saving.
 */
function ImpactPanel({
    data,
    total,
    items,
    contact,
}: {
    data: RecordFormData;
    total: number;
    items: Map<string, RecordItem>;
    contact: ContactOption | null;
}) {
    const { type, kind, method } = data;
    const cash = cashEffect(type, kind, method, total, data.paid_amount ?? 0);

    return (
        <SectionCard
            title="Dampak catatan ini"
            description="Dihitung langsung dari isian di kiri."
            tone="focus"
            className="lg:sticky lg:top-18"
        >
            <div className="flex flex-col gap-4" aria-live="polite">
                <div className="flex flex-col divide-y">
                    <MetricInline
                        label="Total"
                        value={<Money value={total} size="lg" />}
                    />
                    {type === 'penjualan' && (
                        <SaleImpact data={data} total={total} items={items} />
                    )}
                    {type === 'pembelian' && kind === 'barang' && (
                        <PurchaseImpact data={data} items={items} />
                    )}
                    {type === 'utang_piutang' && contact && (
                        <MetricInline
                            label={`Sisa tagihan ${contact.name}`}
                            value={
                                <span className="num text-sm font-semibold">
                                    {formatRupiah(contact.balance)} →{' '}
                                    {formatRupiah(
                                        Math.max(0, contact.balance - total),
                                    )}
                                </span>
                            }
                            detail={
                                total > contact.balance ? (
                                    <span className="font-semibold text-destructive">
                                        Lebih dari sisa tagihan. Turunkan
                                        nominalnya.
                                    </span>
                                ) : contact.balance - total === 0 ? (
                                    'Lunas'
                                ) : undefined
                            }
                        />
                    )}
                    <MetricInline
                        label="Kas usaha"
                        value={
                            <Money
                                value={cash}
                                direction={
                                    cash > 0 ? 'in' : cash < 0 ? 'out' : 'none'
                                }
                            />
                        }
                        detail={cashNote(type, method)}
                    />
                </div>
                <ImpactNote
                    type={type}
                    kind={kind}
                    method={method}
                    total={total}
                />
            </div>
        </SectionCard>
    );
}

function SaleImpact({
    data,
    total,
    items,
}: {
    data: RecordFormData;
    total: number;
    items: Map<string, RecordItem>;
}) {
    // Explode recipes into ingredients, then follow the server's value rules per item.
    const out = new Map<string, number>();

    for (const line of data.lines) {
        const item = items.get(line.item_id);
        const qty = toNumber(line.qty) || 0;

        if (!item || qty <= 0) {
            continue;
        }

        const parts =
            item.kind === 'komposisi'
                ? item.recipe.map(
                      (r) =>
                          [
                              String(r.materialId),
                              Math.round(r.qty * qty * 1000) / 1000,
                          ] as const,
                  )
                : [[String(item.id), qty] as const];

        for (const [id, partQty] of parts) {
            out.set(id, (out.get(id) ?? 0) + partQty);
        }
    }

    let hpp = 0;
    const changes = [...out].flatMap(([id, qty]) => {
        const item = items.get(id);

        if (!item) {
            return [];
        }

        const value = item.stock * item.averageCost;
        hpp +=
            item.stock <= 0
                ? 0
                : item.stock - qty <= 0
                  ? value
                  : qty * item.averageCost;

        return [
            { item, qty, after: Math.round((item.stock - qty) * 1000) / 1000 },
        ];
    });
    hpp = Math.round(hpp);

    return (
        <>
            <MetricInline
                label="Estimasi HPP"
                value={<Money value={hpp} />}
                detail="Biaya rata-rata bahan dan barang yang keluar."
            />
            <MetricInline
                label="Estimasi laba kotor"
                value={<Money value={total - hpp} />}
            />
            {changes.length > 0 && (
                <div className="flex flex-col gap-1.5 py-3 last:pb-0">
                    <span className="text-sm text-muted-foreground">
                        Stok yang berkurang
                    </span>
                    <ul className="flex flex-col gap-1 text-sm">
                        {changes.map(({ item, qty, after }) => (
                            <li
                                key={item.id}
                                className="flex items-baseline justify-between gap-3"
                            >
                                <span className="min-w-0 truncate">
                                    {item.name}{' '}
                                    <span className="num text-muted-foreground">
                                        −{formatQty(qty, item.unit)}
                                    </span>
                                </span>
                                <span
                                    className={cn(
                                        'num shrink-0 text-xs',
                                        after < 0
                                            ? 'font-semibold text-destructive'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {after < 0
                                        ? `jadi minus ${formatQty(after, item.unit)}`
                                        : `sisa ${formatQty(after, item.unit)}`}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}

function PurchaseImpact({
    data,
    items,
}: {
    data: RecordFormData;
    items: Map<string, RecordItem>;
}) {
    const changes = data.lines.flatMap((line) => {
        const item = items.get(line.item_id);
        const qty = toNumber(line.qty) || 0;

        if (!item || qty <= 0 || line.price === null) {
            return [];
        }

        // Same rule as the server: stock at or below zero restarts the average at the purchase price.
        const after = item.stock + qty;
        const average =
            item.stock <= 0
                ? line.price
                : (item.stock * item.averageCost + qty * line.price) / after;

        return [{ item, qty, before: item.averageCost, average }];
    });

    if (changes.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1.5 py-3 last:pb-0">
            <span className="text-sm text-muted-foreground">
                Stok bertambah dan biaya rata-rata
            </span>
            <ul className="flex flex-col gap-1 text-sm">
                {changes.map(({ item, qty, before, average }) => (
                    <li key={item.id} className="flex flex-col">
                        <span>
                            {item.name}{' '}
                            <span className="num text-muted-foreground">
                                +{formatQty(qty, item.unit)}
                            </span>
                        </span>
                        <span className="num text-xs text-muted-foreground">
                            {before > 0
                                ? `${formatRupiah(Math.round(before))} → ${formatRupiah(Math.round(average))}/${item.unit}`
                                : `${formatRupiah(Math.round(average))}/${item.unit}`}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Money into (+) or out of (−) the business till, same as transactions.cash_flow. */
function cashEffect(
    type: TransactionType,
    kind: string,
    method: PaymentMethod,
    total: number,
    paid: number,
): number {
    if (method === 'pribadi') {
        return 0;
    }

    if (method === 'kredit') {
        return type === 'penjualan' ? paid : 0;
    }

    const incoming =
        type === 'penjualan' || kind === 'terima_piutang' || kind === 'setor';

    return incoming ? total : -total;
}

function cashNote(
    type: TransactionType,
    method: PaymentMethod,
): string | undefined {
    if (method === 'pribadi') {
        return 'Dibayar pakai uang pribadi, kas usaha tidak berubah.';
    }

    if (method === 'kredit') {
        return type === 'penjualan'
            ? 'Hanya yang sudah dibayar yang masuk kas.'
            : 'Kas baru berkurang saat utangnya dibayar.';
    }

    return method === 'non_tunai' ? 'Lewat QRIS atau transfer.' : undefined;
}

function ImpactNote({
    type,
    kind,
    method,
    total,
}: {
    type: TransactionType;
    kind: string;
    method: PaymentMethod;
    total: number;
}) {
    const note: ReactNode = (() => {
        if (type === 'modal' && kind === 'prive') {
            return 'Mengambil uang untuk pribadi tidak mengurangi laba, tetapi mengurangi modal usaha.';
        }

        if (type === 'modal') {
            return 'Setoran modal menambah kas usaha, bukan pendapatan.';
        }

        if (type === 'biaya') {
            return (
                <>
                    Mengurangi laba <Money value={total} size="sm" />.
                    {method === 'pribadi' &&
                        ' Uang pribadinya dicatat sebagai tambahan modal.'}
                </>
            );
        }

        if (type === 'pembelian' && kind === 'peralatan') {
            return 'Peralatan dicatat sebagai aset usaha, tidak mengurangi laba.';
        }

        if (type === 'pembelian' && method === 'pribadi') {
            return 'Uang pribadi yang dipakai belanja dicatat sebagai tambahan modal.';
        }

        if (type === 'penjualan' && method === 'kredit') {
            return 'Sisa yang belum dibayar masuk daftar piutang pelanggan.';
        }

        return null;
    })();

    return note ? (
        <p className="flex gap-2 rounded-lg bg-secondary px-3 py-2.5 text-sm">
            <Info
                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden
            />
            <span>{note}</span>
        </p>
    ) : null;
}
