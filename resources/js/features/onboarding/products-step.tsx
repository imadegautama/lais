import { Info, PackagePlus, Plus, X } from 'lucide-react';
import { LabelBadge } from '@/components/app/badges';
import { ChoiceGroup } from '@/components/app/choice-group';
import { Field } from '@/components/app/field';
import { MoneyInput } from '@/components/app/money-input';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatQty, formatRupiah } from '@/lib/format';
import { listEditor } from '@/lib/form';
import { parseDecimal } from '@/lib/number';
import {
    ItemKind,
    businessCategoryLabels,
    type BusinessCategory,
} from '@/types/enums';
import {
    emptyProductRow,
    withExamples,
    type ProductRow,
    type StarterRow,
} from './onboarding';

const quickKinds = [
    { value: ItemKind.Produk, label: 'Dijual' },
    { value: ItemKind.Bahan, label: 'Bahan' },
];

/** "0,15 kg Beras, 1 butir Telur", with units from the rows above. */
function recipeText(row: ProductRow, rows: ProductRow[]): string {
    return row.recipe
        .map(
            ({ name, qty }) =>
                formatQty(
                    qty,
                    rows.find((other) => other.name === name)?.unit ?? '',
                ) + ` ${name}`,
        )
        .join(', ');
}

/**
 * Langkah 4: what is sold and what is stocked, with the stock on the day the
 * books open. "Pakai contoh" fills the list for the kind of business.
 */
export function ProductsStep({
    products,
    onChange,
    errors,
    category,
    templates,
    inventory,
}: {
    products: ProductRow[];
    onChange: (products: ProductRow[]) => void;
    errors: Record<string, string | undefined>;
    category: BusinessCategory | '';
    templates: Record<BusinessCategory, StarterRow[]>;
    inventory: { count: number; value: number };
}) {
    const editor = listEditor(products, onChange, emptyProductRow);
    const starters = category ? templates[category] : [];
    // Once every example is in, the button would do nothing, so it goes.
    const examplesLeft =
        withExamples(products, starters).length > products.length;
    const error = (index: number, field: string) =>
        errors[`products.${index}.${field}`];

    return (
        <div className="flex flex-col gap-4">
            {inventory.count > 0 && (
                <p className="flex gap-2 rounded-lg bg-secondary px-3 py-2.5 text-sm">
                    <Info
                        className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        aria-hidden
                    />
                    <span>
                        Sudah ada {inventory.count} barang dengan persediaan{' '}
                        {formatRupiah(inventory.value)}. Yang ditambahkan di
                        sini barang baru.
                    </span>
                </p>
            )}

            {examplesLeft && (
                <Button
                    type="button"
                    variant="outline"
                    className="self-start"
                    onClick={() => onChange(withExamples(products, starters))}
                >
                    <PackagePlus aria-hidden />
                    Pakai contoh{' '}
                    {businessCategoryLabels[
                        category as BusinessCategory
                    ].toLowerCase()}
                </Button>
            )}

            {products.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Belum ada produk. Tambahkan sendiri
                    {starters.length > 0 && ' atau pakai contoh'}, atau lewati
                    dan isi nanti di halaman Produk & stok.
                </p>
            )}

            {products.map((row, index) => {
                const isRecipe = row.kind === ItemKind.Komposisi;
                const id = `ob-product-${index}`;

                return (
                    <div
                        key={index}
                        className="flex flex-col gap-3 rounded-lg border p-3"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <div className="flex min-w-0 flex-wrap items-center gap-2">
                                <span className="truncate text-sm font-semibold">
                                    {row.name || `Produk ${index + 1}`}
                                </span>
                                {row.example && (
                                    <LabelBadge
                                        icon={Info}
                                        label="Contoh, ubah sesuai usahamu"
                                        tone="warning"
                                    />
                                )}
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => editor.remove(index)}
                                aria-label={`Hapus ${row.name || `produk ${index + 1}`}`}
                            >
                                <X aria-hidden />
                            </Button>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <Field
                                label="Nama"
                                htmlFor={`${id}-name`}
                                error={error(index, 'name')}
                            >
                                <Input
                                    id={`${id}-name`}
                                    value={row.name}
                                    onChange={(event) =>
                                        editor.update(index, {
                                            name: event.target.value,
                                        })
                                    }
                                    placeholder="Kopi susu"
                                    autoComplete="off"
                                    aria-invalid={
                                        Boolean(error(index, 'name')) ||
                                        undefined
                                    }
                                />
                            </Field>
                            {isRecipe ? (
                                <div className="flex flex-col gap-1.5">
                                    <span className="text-sm font-medium">
                                        Jenis
                                    </span>
                                    <p className="text-sm text-muted-foreground">
                                        Dengan resep:{' '}
                                        {recipeText(row, products)}. Stoknya ada
                                        di bahan; resep bisa diubah di halaman
                                        Produk.
                                    </p>
                                    <InputError
                                        message={error(index, 'recipe')}
                                    />
                                </div>
                            ) : (
                                <div className="flex flex-col gap-1.5">
                                    <span
                                        id={`${id}-kind`}
                                        className="text-sm font-medium"
                                    >
                                        Jenis
                                    </span>
                                    <ChoiceGroup
                                        labelledBy={`${id}-kind`}
                                        value={row.kind}
                                        options={quickKinds}
                                        onChange={(kind) =>
                                            editor.update(index, { kind })
                                        }
                                    />
                                </div>
                            )}
                            <Field
                                label="Satuan"
                                htmlFor={`${id}-unit`}
                                error={error(index, 'unit')}
                            >
                                <Input
                                    id={`${id}-unit`}
                                    value={row.unit}
                                    onChange={(event) =>
                                        editor.update(index, {
                                            unit: event.target.value,
                                        })
                                    }
                                    placeholder="pcs, kg, porsi"
                                    maxLength={20}
                                    autoComplete="off"
                                    aria-invalid={
                                        Boolean(error(index, 'unit')) ||
                                        undefined
                                    }
                                />
                            </Field>
                            {row.kind !== ItemKind.Bahan && (
                                <div className="flex flex-col gap-1.5">
                                    <MoneyInput
                                        label="Harga jual"
                                        value={row.price}
                                        onChange={(price) =>
                                            editor.update(index, { price })
                                        }
                                    />
                                    <InputError
                                        message={error(index, 'price')}
                                    />
                                </div>
                            )}
                            {!isRecipe && (
                                <>
                                    <Field
                                        label={`Stok awal${row.unit ? ` (${row.unit})` : ''}`}
                                        htmlFor={`${id}-stock`}
                                        error={error(index, 'opening_stock')}
                                        hint={
                                            parseDecimal(row.opening_stock) >
                                                0 && row.opening_cost
                                                ? `Senilai ${formatRupiah(Math.round(parseDecimal(row.opening_stock) * row.opening_cost))}`
                                                : 'Boleh pakai koma, misalnya 2,5.'
                                        }
                                    >
                                        <Input
                                            id={`${id}-stock`}
                                            inputMode="decimal"
                                            value={row.opening_stock}
                                            onChange={(event) =>
                                                editor.update(index, {
                                                    opening_stock:
                                                        event.target.value,
                                                })
                                            }
                                            placeholder="0"
                                            autoComplete="off"
                                            className="num"
                                            aria-invalid={
                                                Boolean(
                                                    error(
                                                        index,
                                                        'opening_stock',
                                                    ),
                                                ) || undefined
                                            }
                                        />
                                    </Field>
                                    <div className="flex flex-col gap-1.5">
                                        <MoneyInput
                                            label="Biaya per unit"
                                            value={row.opening_cost}
                                            onChange={(opening_cost) =>
                                                editor.update(index, {
                                                    opening_cost,
                                                })
                                            }
                                        />
                                        <InputError
                                            message={error(
                                                index,
                                                'opening_cost',
                                            )}
                                        />
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                );
            })}

            <Button
                type="button"
                variant="outline"
                className="self-start"
                onClick={editor.add}
            >
                <Plus aria-hidden />
                Tambah produk
            </Button>
        </div>
    );
}
