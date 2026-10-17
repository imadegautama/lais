import type { FormDataValues } from '@inertiajs/core';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Archive, History, Plus, Wheat, X } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import {
    itemKinds,
    MarginLabel,
    THIN_MARGIN_PERCENT,
} from '@/components/app/badges';
import { MetricInline } from '@/components/app/metric';
import { Money } from '@/components/app/money';
import { MoneyInput } from '@/components/app/money-input';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import { StockAdjustDialog } from '@/components/app/stock-adjust-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { formatDate, formatQty } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create, destroy, index, store, update } from '@/routes/items';
import type {
    ItemFormProps,
    ItemKind,
    RecipeMaterial,
    StockMovementReason,
} from '@/types/domain';

const UNITS = [
    'pcs',
    'porsi',
    'gelas',
    'botol',
    'bungkus',
    'butir',
    'kg',
    'gram',
    'liter',
    'ml',
];

const movementReasons: Record<StockMovementReason, string> = {
    stok_awal: 'Stok awal',
    koreksi: 'Koreksi stok',
};

type RecipeRow = { material_id: string; qty: string };

type ItemFormData = {
    kind: ItemKind;
    name: string;
    aliases: string;
    unit: string;
    price: number | null;
    min_stock: string;
    show_in_store: boolean;
    available: boolean;
    opening_stock: string;
    opening_cost: number | null;
    recipe: RecipeRow[];
};

/** "0,15" and "0.15" are the same amount. NaN for anything else. */
const toNumber = (text: string) => Number(text.trim().replace(',', '.'));

const emptyLine = (): RecipeRow => ({ material_id: '', qty: '' });

export default function ItemForm({
    item,
    initialKind,
    materials,
    movements,
}: ItemFormProps) {
    const editing = item !== null;
    const form = useForm<ItemFormData>({
        kind: initialKind,
        name: item?.name ?? '',
        aliases: item?.aliases.join(', ') ?? '',
        unit: item?.unit ?? (initialKind === 'komposisi' ? 'porsi' : 'pcs'),
        price: item?.price ?? null,
        min_stock: item?.minStock?.toString().replace('.', ',') ?? '',
        show_in_store: item?.showInStore ?? true,
        available: item?.available ?? true,
        opening_stock: '',
        opening_cost: null,
        recipe: item?.recipe.map((line) => ({
            material_id: String(line.material_id),
            qty: String(line.qty).replace('.', ','),
        })) ?? [emptyLine()],
    });
    const { data, processing } = form;
    const setData = <K extends keyof ItemFormData>(
        key: K,
        value: FormDataValues<ItemFormData, K>,
    ) => {
        form.setData(key, value);
        // An error goes away as soon as its field is edited.
        const stale = Object.keys(form.errors).filter(
            (name) => name === key || name.startsWith(`${key}.`),
        );

        if (stale.length > 0) {
            form.clearErrors(...(stale as Array<keyof ItemFormData>));
        }
    };
    const errors = form.errors as Record<string, string | undefined>;
    const kind = data.kind;
    const sold = kind !== 'bahan';
    const stocked = kind !== 'komposisi';

    const materialById = new Map(materials.map((m) => [String(m.id), m]));
    const lineCost = (line: RecipeRow) => {
        const qty = toNumber(line.qty);
        const material = materialById.get(line.material_id);

        return material && qty > 0 ? qty * material.averageCost : 0;
    };
    const unitCost =
        kind === 'komposisi'
            ? data.recipe.some((line) => lineCost(line) > 0)
                ? Math.round(
                      data.recipe.reduce((sum, l) => sum + lineCost(l), 0),
                  )
                : null
            : editing
              ? item.unitCost
              : data.opening_cost;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((values) => ({
            ...(editing ? {} : { kind: values.kind }),
            name: values.name,
            aliases: values.aliases,
            unit: values.unit,
            price: sold ? values.price : null,
            min_stock:
                stocked && values.min_stock.trim() !== ''
                    ? toNumber(values.min_stock)
                    : null,
            show_in_store: sold && values.show_in_store,
            available: values.available,
            ...(!editing && stocked
                ? {
                      opening_stock:
                          values.opening_stock.trim() === ''
                              ? null
                              : toNumber(values.opening_stock),
                      opening_cost: values.opening_cost,
                  }
                : {}),
            ...(kind === 'komposisi'
                ? {
                      recipe: values.recipe.map((line) => ({
                          material_id:
                              line.material_id === ''
                                  ? null
                                  : Number(line.material_id),
                          qty:
                              line.qty.trim() === ''
                                  ? null
                                  : toNumber(line.qty),
                      })),
                  }
                : {}),
        }));
        form.submit(editing ? update(item.id) : store(), {
            preserveScroll: true,
            onError: () =>
                requestAnimationFrame(() =>
                    document
                        .querySelector<HTMLElement>('[aria-invalid="true"]')
                        ?.focus(),
                ),
        });
    };

    const title = editing ? `Ubah ${item.name}` : 'Tambah produk';

    return (
        <>
            <Head title={title} />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title={title}
                    description={
                        editing
                            ? itemKinds[kind].label
                            : 'Pilih jenisnya dulu, lalu isi harga dan stok.'
                    }
                />

                <form
                    onSubmit={submit}
                    noValidate
                    className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12"
                >
                    <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-7">
                        {!editing && (
                            <KindPicker
                                value={kind}
                                onChange={(next) => {
                                    form.setData((values) => ({
                                        ...values,
                                        kind: next,
                                        unit:
                                            values.unit === 'pcs' ||
                                            values.unit === 'porsi'
                                                ? next === 'komposisi'
                                                    ? 'porsi'
                                                    : 'pcs'
                                                : values.unit,
                                    }));
                                }}
                                error={errors.kind}
                            />
                        )}

                        <SectionCard title="Data dasar">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <Field
                                    label="Nama"
                                    htmlFor="item-name"
                                    error={errors.name}
                                    className="md:col-span-2"
                                >
                                    <Input
                                        id="item-name"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder={
                                            kind === 'bahan'
                                                ? 'Beras'
                                                : kind === 'komposisi'
                                                  ? 'Nasi Goreng'
                                                  : 'Air Mineral 600 ml'
                                        }
                                        aria-invalid={
                                            Boolean(errors.name) || undefined
                                        }
                                        autoComplete="off"
                                    />
                                </Field>

                                <Field
                                    label="Nama lain"
                                    htmlFor="item-aliases"
                                    error={
                                        errors.aliases ?? errors['aliases.0']
                                    }
                                    hint="Pisahkan dengan koma. Bot WhatsApp jadi paham kalau ditulis begini."
                                    className="md:col-span-2"
                                >
                                    <Input
                                        id="item-aliases"
                                        value={data.aliases}
                                        onChange={(e) =>
                                            setData('aliases', e.target.value)
                                        }
                                        placeholder={
                                            {
                                                produk: 'aqua, air',
                                                bahan: 'minyak, migor',
                                                komposisi:
                                                    'nasgor, nasi goreng biasa',
                                            }[kind]
                                        }
                                        aria-invalid={
                                            Boolean(errors.aliases) || undefined
                                        }
                                        autoComplete="off"
                                    />
                                </Field>

                                <Field
                                    label="Satuan"
                                    htmlFor="item-unit"
                                    error={errors.unit}
                                    hint="Pilih atau ketik sendiri."
                                >
                                    <Input
                                        id="item-unit"
                                        list="item-unit-options"
                                        value={data.unit}
                                        onChange={(e) =>
                                            setData('unit', e.target.value)
                                        }
                                        aria-invalid={
                                            Boolean(errors.unit) || undefined
                                        }
                                        autoComplete="off"
                                    />
                                    <datalist id="item-unit-options">
                                        {UNITS.map((unit) => (
                                            <option key={unit} value={unit} />
                                        ))}
                                    </datalist>
                                </Field>

                                {stocked && (
                                    <Field
                                        label={`Stok minimum (${data.unit || 'satuan'})`}
                                        htmlFor="item-min-stock"
                                        error={errors.min_stock}
                                        hint="Diberi tanda menipis di bawah angka ini."
                                    >
                                        <Input
                                            id="item-min-stock"
                                            inputMode="decimal"
                                            value={data.min_stock}
                                            onChange={(e) =>
                                                setData(
                                                    'min_stock',
                                                    e.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(errors.min_stock) ||
                                                undefined
                                            }
                                            className="num"
                                            autoComplete="off"
                                        />
                                    </Field>
                                )}

                                {sold && (
                                    <div className="flex flex-col gap-1.5">
                                        <MoneyInput
                                            key={kind}
                                            label="Harga jual"
                                            value={data.price}
                                            onChange={(value) =>
                                                setData('price', value)
                                            }
                                        />
                                        <InputError message={errors.price} />
                                    </div>
                                )}
                            </div>

                            {sold && (
                                <div className="mt-4 flex flex-col divide-y border-t">
                                    <SwitchRow
                                        id="item-show-in-store"
                                        label="Tampil di halaman toko"
                                        hint="Pembeli bisa memesan dari tautan toko."
                                        checked={data.show_in_store}
                                        onChange={(value) =>
                                            setData('show_in_store', value)
                                        }
                                    />
                                    <SwitchRow
                                        id="item-available"
                                        label="Tersedia untuk dijual"
                                        hint="Matikan kalau sedang habis atau tutup."
                                        checked={data.available}
                                        onChange={(value) =>
                                            setData('available', value)
                                        }
                                    />
                                </div>
                            )}
                        </SectionCard>

                        {!editing && stocked && (
                            <SectionCard
                                title="Stok awal"
                                description="Yang ada sekarang di rak atau gudang. Boleh dikosongkan dan dicatat saat belanja."
                            >
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <Field
                                        label={`Jumlah (${data.unit || 'satuan'})`}
                                        htmlFor="item-opening-stock"
                                        error={errors.opening_stock}
                                    >
                                        <Input
                                            id="item-opening-stock"
                                            inputMode="decimal"
                                            value={data.opening_stock}
                                            onChange={(e) =>
                                                setData(
                                                    'opening_stock',
                                                    e.target.value,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(errors.opening_stock) ||
                                                undefined
                                            }
                                            className="num"
                                            autoComplete="off"
                                        />
                                    </Field>
                                    <div className="flex flex-col gap-1.5">
                                        <MoneyInput
                                            label={`Harga beli per ${data.unit || 'satuan'}`}
                                            value={data.opening_cost}
                                            onChange={(value) =>
                                                setData('opening_cost', value)
                                            }
                                        />
                                        <InputError
                                            message={errors.opening_cost}
                                        />
                                    </div>
                                </div>
                            </SectionCard>
                        )}

                        {kind === 'komposisi' && (
                            <RecipeBuilder
                                lines={data.recipe}
                                materials={materials}
                                errors={errors}
                                lineCost={lineCost}
                                onChange={(recipe) => setData('recipe', recipe)}
                            />
                        )}
                    </div>

                    <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-5">
                        {sold && (
                            <CostPanel
                                kind={kind}
                                unitCost={unitCost}
                                price={data.price}
                                unit={data.unit}
                            />
                        )}

                        {editing && stocked && (
                            <SectionCard
                                title="Riwayat stok"
                                icon={History}
                                description={`Sekarang ${formatQty(item.stock, item.unit)}.`}
                                action={
                                    <StockAdjustDialog
                                        item={item}
                                        trigger={
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                            >
                                                Koreksi stok
                                            </Button>
                                        }
                                    />
                                }
                            >
                                {movements.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada perubahan stok. Stok bertambah
                                        saat belanja dicatat.
                                    </p>
                                ) : (
                                    <ul className="flex flex-col divide-y">
                                        {movements.map((movement) => (
                                            <li
                                                key={movement.id}
                                                className="flex items-baseline justify-between gap-3 py-2 first:pt-0 last:pb-0"
                                            >
                                                <div className="flex min-w-0 flex-col">
                                                    <span className="text-sm font-medium">
                                                        {
                                                            movementReasons[
                                                                movement.reason
                                                            ]
                                                        }
                                                    </span>
                                                    <span className="num text-xs text-muted-foreground">
                                                        {formatDate(
                                                            movement.occurredAt,
                                                            'datetime',
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="flex flex-col items-end">
                                                    <span className="num text-sm font-semibold">
                                                        {movement.qty > 0
                                                            ? '+'
                                                            : '−'}
                                                        {formatQty(
                                                            Math.abs(
                                                                movement.qty,
                                                            ),
                                                            item.unit,
                                                        )}
                                                    </span>
                                                    <Money
                                                        value={movement.value}
                                                        size="sm"
                                                        className="text-xs font-normal text-muted-foreground"
                                                    />
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </SectionCard>
                        )}

                        <div className="flex flex-wrap items-center gap-2 lg:sticky lg:top-4">
                            <Button type="submit" disabled={processing}>
                                {processing
                                    ? 'Menyimpan…'
                                    : editing
                                      ? 'Simpan perubahan'
                                      : 'Simpan produk'}
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href={index()}>Batal</Link>
                            </Button>
                            {editing && (
                                <ArchiveButton id={item.id} name={item.name} />
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}

ItemForm.layout = {
    breadcrumbs: [{ title: 'Produk & stok', href: index() }],
};

function Field({
    label,
    htmlFor,
    error,
    hint,
    className,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    hint?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {error ? (
                <InputError message={error} />
            ) : (
                hint && <p className="text-xs text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}

function SwitchRow({
    id,
    label,
    hint,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    hint: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-4 py-3 last:pb-0">
            <Label htmlFor={id} className="flex flex-col items-start gap-0.5">
                {label}
                <span className="text-xs font-normal text-muted-foreground">
                    {hint}
                </span>
            </Label>
            <Switch id={id} checked={checked} onCheckedChange={onChange} />
        </div>
    );
}

function KindPicker({
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
                {(['produk', 'komposisi', 'bahan'] as ItemKind[]).map(
                    (kind) => {
                        const { label, hint, icon: Icon } = itemKinds[kind];

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
                                        {hint}
                                    </span>
                                </span>
                            </label>
                        );
                    },
                )}
            </div>
            <InputError message={error} />
        </fieldset>
    );
}

function RecipeBuilder({
    lines,
    materials,
    errors,
    lineCost,
    onChange,
}: {
    lines: RecipeRow[];
    materials: RecipeMaterial[];
    errors: Record<string, string | undefined>;
    lineCost: (line: RecipeRow) => number;
    onChange: (lines: RecipeRow[]) => void;
}) {
    const materialById = new Map(materials.map((m) => [String(m.id), m]));
    const setLine = (index: number, patch: Partial<RecipeRow>) =>
        onChange(
            lines.map((line, i) =>
                i === index ? { ...line, ...patch } : line,
            ),
        );

    return (
        <SectionCard
            title="Resep per porsi"
            icon={Wheat}
            description="Takaran bahan untuk satu kali jual. HPP dihitung dari biaya rata-rata tiap bahan."
        >
            {materials.length === 0 ? (
                <EmptyState
                    title="Belum ada bahan baku"
                    description="Resep disusun dari bahan baku atau produk biasa. Tambahkan bahannya dulu, lalu kembali ke sini."
                    action={
                        <Button variant="outline" size="sm" asChild>
                            <Link href={create({ query: { jenis: 'bahan' } })}>
                                Tambah bahan baku
                            </Link>
                        </Button>
                    }
                />
            ) : (
                <div className="flex flex-col gap-3">
                    <InputError message={errors.recipe} />
                    <ol className="flex flex-col gap-3">
                        {lines.map((line, index) => {
                            const material = materialById.get(line.material_id);
                            const lineError =
                                errors[`recipe.${index}.material_id`] ??
                                errors[`recipe.${index}.qty`];

                            return (
                                <li
                                    key={index}
                                    className="flex flex-col gap-1.5 rounded-lg border p-3 md:border-0 md:p-0"
                                >
                                    <div className="grid grid-cols-[1fr_auto] items-end gap-2 md:grid-cols-[minmax(0,1fr)_8rem_6.5rem_auto]">
                                        <div className="col-span-2 flex flex-col gap-1.5 md:col-span-1">
                                            <Label
                                                htmlFor={`recipe-${index}-material`}
                                                className={cn(
                                                    index > 0 && 'md:sr-only',
                                                )}
                                            >
                                                Bahan
                                            </Label>
                                            <Select
                                                value={line.material_id}
                                                onValueChange={(value) =>
                                                    setLine(index, {
                                                        material_id: value,
                                                    })
                                                }
                                            >
                                                <SelectTrigger
                                                    id={`recipe-${index}-material`}
                                                    className="w-full"
                                                    aria-invalid={
                                                        Boolean(
                                                            errors[
                                                                `recipe.${index}.material_id`
                                                            ],
                                                        ) || undefined
                                                    }
                                                >
                                                    <SelectValue placeholder="Pilih bahan" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {materials.map((m) => (
                                                        <SelectItem
                                                            key={m.id}
                                                            value={String(m.id)}
                                                            disabled={lines.some(
                                                                (other, i) =>
                                                                    i !==
                                                                        index &&
                                                                    other.material_id ===
                                                                        String(
                                                                            m.id,
                                                                        ),
                                                            )}
                                                        >
                                                            {m.name} ({m.unit})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="flex flex-col gap-1.5">
                                            <Label
                                                htmlFor={`recipe-${index}-qty`}
                                                className={cn(
                                                    index > 0 && 'md:sr-only',
                                                )}
                                            >
                                                Takaran
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id={`recipe-${index}-qty`}
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
                                                                `recipe.${index}.qty`
                                                            ],
                                                        ) || undefined
                                                    }
                                                    className="num pr-14"
                                                    autoComplete="off"
                                                />
                                                <span
                                                    aria-hidden
                                                    className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground"
                                                >
                                                    {material?.unit ?? ''}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="hidden flex-col items-end gap-1.5 md:flex">
                                            <span
                                                className={cn(
                                                    'text-sm font-medium',
                                                    index > 0 && 'sr-only',
                                                )}
                                            >
                                                Biaya
                                            </span>
                                            <Money
                                                value={Math.round(
                                                    lineCost(line),
                                                )}
                                                size="sm"
                                                className="h-9 items-center"
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() =>
                                                onChange(
                                                    lines.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                            disabled={lines.length === 1}
                                            aria-label={`Hapus ${material?.name ?? `baris ${index + 1}`} dari resep`}
                                        >
                                            <X aria-hidden />
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground md:hidden">
                                        Biaya{' '}
                                        <Money
                                            value={Math.round(lineCost(line))}
                                            size="sm"
                                            className="text-xs"
                                        />
                                    </p>
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
                        disabled={lines.length >= materials.length}
                    >
                        <Plus aria-hidden />
                        Tambah bahan
                    </Button>
                </div>
            )}
        </SectionCard>
    );
}

function CostPanel({
    kind,
    unitCost,
    price,
    unit,
}: {
    kind: ItemKind;
    unitCost: number | null;
    price: number | null;
    unit: string;
}) {
    const marginRupiah =
        price !== null && unitCost !== null ? price - unitCost : null;
    const marginPercent =
        marginRupiah !== null && price
            ? Math.round((marginRupiah / price) * 1000) / 10
            : null;

    return (
        <SectionCard
            title="Untung per jual"
            tone="focus"
            description={
                kind === 'komposisi'
                    ? `Dihitung langsung dari resep, per ${unit || 'porsi'}.`
                    : `Dari harga beli per ${unit || 'satuan'}.`
            }
            className="lg:sticky lg:top-4"
        >
            <div className="flex flex-col divide-y" aria-live="polite">
                <MetricInline
                    label={kind === 'komposisi' ? 'HPP' : 'Biaya per unit'}
                    value={
                        unitCost === null ? (
                            <span className="text-sm text-muted-foreground">
                                Belum diisi
                            </span>
                        ) : (
                            <Money value={unitCost} />
                        )
                    }
                />
                <MetricInline
                    label="Harga jual"
                    value={
                        price === null ? (
                            <span className="text-sm text-muted-foreground">
                                Belum diisi
                            </span>
                        ) : (
                            <Money value={price} />
                        )
                    }
                />
                <MetricInline
                    label="Margin"
                    value={
                        marginRupiah === null ? (
                            <span className="text-sm text-muted-foreground">
                                Isi harga dan biaya
                            </span>
                        ) : (
                            <Money value={marginRupiah} size="lg" />
                        )
                    }
                    detail={
                        marginPercent !== null && (
                            <MarginLabel percent={marginPercent} />
                        )
                    }
                />
            </div>
            {marginPercent !== null && marginPercent < THIN_MARGIN_PERCENT && (
                <p
                    role="status"
                    className="mt-3 rounded-lg bg-destructive-soft px-3 py-2 text-sm text-foreground"
                >
                    Margin di bawah {THIN_MARGIN_PERCENT}%. Naikkan harga jual
                    atau cek lagi takaran resepnya.
                </p>
            )}
        </SectionCard>
    );
}

function ArchiveButton({ id, name }: { id: number; name: string }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="text-destructive hover:text-destructive lg:ml-auto"
                >
                    <Archive aria-hidden />
                    Arsipkan
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Arsipkan {name}?</DialogTitle>
                    <DialogDescription>
                        {name} hilang dari daftar produk dan halaman toko.
                        Catatan penjualan dan stok lamanya tetap tersimpan.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={busy}
                        onClick={() =>
                            router.delete(destroy.url(id), {
                                onStart: () => setBusy(true),
                                onFinish: () => {
                                    setBusy(false);
                                    setOpen(false);
                                },
                            })
                        }
                    >
                        {busy ? 'Mengarsipkan…' : 'Arsipkan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
