import { Head, Link, useForm } from '@inertiajs/react';
import { History, ScrollText } from 'lucide-react';
import type { FormEvent } from 'react';
import { ActivityList } from '@/components/app/activity-list';
import { AssistantFilledNote } from '@/components/app/assistant/assistant-panel';
import { useAssistantForm } from '@/components/app/assistant/assistant-provider';
import { itemKinds } from '@/components/app/badges';
import { Field } from '@/components/app/field';
import { FormActionBar } from '@/components/app/form-action-bar';
import { MoneyInput } from '@/components/app/money-input';
import { PageHeader } from '@/components/app/page-header';
import { SearchSelect } from '@/components/app/search-select';
import { SectionCard } from '@/components/app/section-card';
import { StockMovementList } from '@/components/app/stock-movement-list';
import { SwitchRow } from '@/components/app/switch-row';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ArchiveButton } from '@/features/items/archive-button';
import { CostPanel } from '@/features/items/cost-panel';
import { recipeCost } from '@/features/items/cost';
import {
    initialItemForm,
    isSoldKind,
    isStockedKind,
    toItemPayload,
    withKind,
    type ItemFormData,
} from '@/features/items/item-form';
import { KindPicker } from '@/features/items/kind-picker';
import { OpeningStockPanel } from '@/features/items/opening-stock-panel';
import { RecipeBuilder } from '@/features/items/recipe-builder';
import { PhotoField } from '@/features/items/photo-field';
import { StockAdjustDialog } from '@/features/items/stock-adjust-dialog';
import { itemKindCopy, unitOptions } from '@/features/items/vocabulary';
import { formatQty } from '@/lib/format';
import { fieldSetter, focusFirstError, formErrors } from '@/lib/form';
import { parseDecimal } from '@/lib/number';
import { index, store, update } from '@/routes/items';
import type { ItemFormProps } from '@/types/domain';
import { ItemKind } from '@/types/enums';

export default function ItemForm({
    item,
    initialKind,
    materials,
    units,
    movements,
    activities = [],
}: ItemFormProps) {
    const editing = item !== null;
    const form = useForm<ItemFormData>(initialItemForm(item, initialKind));
    const { data, processing } = form;
    const errors = formErrors(form);
    const setField = fieldSetter(form);
    // The assistant fills new products only; an edit opens a new form instead.
    const filledByAssistant = useAssistantForm(
        'produk',
        data,
        (values) => {
            form.setData(values);
            form.clearErrors();
        },
        !editing,
    );
    const { kind } = data;
    const sold = isSoldKind(kind);
    const stocked = isStockedKind(kind);
    const unitCost =
        kind === ItemKind.Komposisi
            ? recipeCost(
                  data.recipe,
                  new Map(materials.map((m) => [String(m.id), m])),
              )
            : editing
              ? item.unitCost
              : data.opening_cost;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // A photo goes up as multipart, which PUT cannot carry: it is sent as POST with _method.
        const upload = data.photo !== null;
        form.transform((values) => ({
            ...toItemPayload(values, editing),
            ...(upload && editing ? { _method: 'put' } : {}),
        }));
        form.submit(
            upload && editing
                ? { url: update.url(item.id), method: 'post' }
                : editing
                  ? update(item.id)
                  : store(),
            {
                preserveScroll: true,
                forceFormData: upload,
                onError: focusFirstError,
            },
        );
    };

    const title = editing ? `Ubah ${item.name}` : 'Tambah produk';

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title={title}
                    description={
                        editing
                            ? itemKinds[kind].label
                            : 'Pilih jenisnya dulu, lalu isi harga dan stok.'
                    }
                />

                <form
                    key={filledByAssistant}
                    onSubmit={submit}
                    noValidate
                    className="flex flex-1 flex-col gap-4 md:gap-6"
                >
                    {filledByAssistant > 0 && <AssistantFilledNote />}
                    <div className="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-12">
                        <div className="flex min-w-0 flex-col gap-4 md:gap-6 lg:col-span-7">
                            {!editing && (
                                <KindPicker
                                    value={kind}
                                    onChange={(next) =>
                                        form.setData((values) =>
                                            withKind(values, next),
                                        )
                                    }
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
                                                setField('name', e.target.value)
                                            }
                                            placeholder={
                                                itemKindCopy[kind].nameExample
                                            }
                                            aria-invalid={
                                                Boolean(errors.name) ||
                                                undefined
                                            }
                                            autoComplete="off"
                                        />
                                    </Field>

                                    <Field
                                        label="Nama lain"
                                        htmlFor="item-aliases"
                                        error={
                                            errors.aliases ??
                                            errors['aliases.0']
                                        }
                                        hint="Pisahkan dengan koma. Bot Lais jadi paham kalau ditulis begini."
                                        className="md:col-span-2"
                                    >
                                        <Input
                                            id="item-aliases"
                                            value={data.aliases}
                                            onChange={(e) =>
                                                setField(
                                                    'aliases',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={
                                                itemKindCopy[kind].aliasExample
                                            }
                                            aria-invalid={
                                                Boolean(errors.aliases) ||
                                                undefined
                                            }
                                            autoComplete="off"
                                        />
                                    </Field>

                                    <Field
                                        label="Satuan"
                                        htmlFor="item-unit"
                                        error={errors.unit}
                                        hint="Tidak ada di daftar? Ketik saja, misalnya ikat."
                                    >
                                        <SearchSelect
                                            id="item-unit"
                                            value={data.unit}
                                            onChange={(unit) =>
                                                setField(
                                                    'unit',
                                                    unit.toLowerCase(),
                                                )
                                            }
                                            options={unitOptions(units)}
                                            placeholder="Pilih satuan"
                                            searchPlaceholder="Cari atau ketik satuan"
                                            emptyText="Ketik satuan yang kamu pakai."
                                            creatable={(unit) =>
                                                `Pakai satuan "${unit.toLowerCase()}"`
                                            }
                                            invalid={Boolean(errors.unit)}
                                        />
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
                                                    setField(
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
                                                    setField('price', value)
                                                }
                                            />
                                            <InputError
                                                message={errors.price}
                                            />
                                        </div>
                                    )}
                                </div>

                                {sold && (
                                    <div className="mt-4 flex flex-col divide-y border-t">
                                        <SwitchRow
                                            id="item-show-in-store"
                                            label="Tampil di halaman toko"
                                            hint="Pelanggan bisa memesan produk ini dari link toko."
                                            checked={data.show_in_store}
                                            onChange={(value) =>
                                                setField('show_in_store', value)
                                            }
                                        />
                                        {/* Produk biasa is sold out by its stock (PRD US-08), so only recipes get this switch. */}
                                        {kind === ItemKind.Komposisi && (
                                            <SwitchRow
                                                id="item-available"
                                                label="Tersedia untuk dipesan"
                                                hint="Matikan kalau bahannya habis atau warung tutup."
                                                checked={data.available}
                                                onChange={(value) =>
                                                    setField('available', value)
                                                }
                                            />
                                        )}
                                    </div>
                                )}
                            </SectionCard>

                            {sold && (
                                <PhotoField
                                    current={
                                        data.remove_photo
                                            ? null
                                            : (item?.photoUrl ?? null)
                                    }
                                    file={data.photo}
                                    error={errors.photo}
                                    onFile={(file) => {
                                        setField('photo', file);
                                        form.setData('remove_photo', false);
                                    }}
                                    onRemove={() => {
                                        setField('photo', null);
                                        form.setData('remove_photo', true);
                                    }}
                                />
                            )}

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
                                                    setField(
                                                        'opening_stock',
                                                        e.target.value,
                                                    )
                                                }
                                                aria-invalid={
                                                    Boolean(
                                                        errors.opening_stock,
                                                    ) || undefined
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
                                                    setField(
                                                        'opening_cost',
                                                        value,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={errors.opening_cost}
                                            />
                                        </div>
                                    </div>
                                </SectionCard>
                            )}

                            {kind === ItemKind.Komposisi && (
                                <RecipeBuilder
                                    lines={data.recipe}
                                    materials={materials}
                                    errors={errors}
                                    onChange={(recipe) =>
                                        setField('recipe', recipe)
                                    }
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

                            {!editing && kind === ItemKind.Bahan && (
                                <OpeningStockPanel
                                    qty={parseDecimal(data.opening_stock)}
                                    unitCost={data.opening_cost}
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
                                            Belum ada perubahan stok. Stok
                                            bertambah saat belanja dicatat.
                                        </p>
                                    ) : (
                                        <StockMovementList
                                            movements={movements}
                                            by="reason"
                                            unit={item.unit}
                                        />
                                    )}
                                </SectionCard>
                            )}

                            {editing && (
                                <SectionCard
                                    title="Riwayat perubahan"
                                    icon={ScrollText}
                                    description="Siapa mengubah harga, resep, atau data lain, dan kapan."
                                >
                                    <ActivityList entries={activities} />
                                </SectionCard>
                            )}
                        </div>
                    </div>

                    <FormActionBar>
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
                    </FormActionBar>
                </form>
            </div>
        </>
    );
}

ItemForm.layout = {
    breadcrumbs: [{ title: 'Produk & stok', href: index() }],
};
