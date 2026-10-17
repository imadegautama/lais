import { Link } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { Money } from '@/components/app/money';
import { MoneyInput } from '@/components/app/money-input';
import { QtyStepper } from '@/components/app/qty-stepper';
import {
    SearchSelect,
    type SearchSelectOption,
} from '@/components/app/search-select';
import { EmptyState } from '@/components/app/states';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatQty, formatRupiah } from '@/lib/format';
import { listEditor, rowError } from '@/lib/form';
import { parseDecimal } from '@/lib/number';
import { create as createItem } from '@/routes/items';
import type { RecordItem } from '@/types/domain';
import {
    ItemKind,
    itemKindLabels,
    soldItemKinds,
    stockedItemKinds,
} from '@/types/enums';
import {
    defaultPrice,
    emptyLine,
    lineSubtotal,
    type LineMode,
    type RecordLine,
} from './record';

/** Weighed goods sell in tenths. */
const qtyStep = (unit?: string) =>
    unit === 'kg' || unit === 'liter' ? 0.1 : 1;

/** The items of a sale or a goods purchase, one row per item. */
export function ItemLines({
    mode,
    lines,
    items,
    errors,
    onChange,
}: {
    mode: LineMode;
    lines: RecordLine[];
    items: RecordItem[];
    errors: Record<string, string | undefined>;
    onChange: (lines: RecordLine[]) => void;
}) {
    const kinds = mode === 'sale' ? soldItemKinds : stockedItemKinds;
    const choices = items.filter((item) => kinds.includes(item.kind));
    const byId = new Map(choices.map((item) => [String(item.id), item]));
    const list = listEditor(lines, onChange, emptyLine);

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
                                    jenis:
                                        mode === 'sale'
                                            ? ItemKind.Produk
                                            : ItemKind.Bahan,
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
                    const qty = parseDecimal(line.qty) || 0;

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
                                        onChange={(value) =>
                                            list.update(index, {
                                                item_id: value,
                                                price: defaultPrice(
                                                    byId.get(value),
                                                    mode,
                                                ),
                                            })
                                        }
                                        options={choices.map((choice) => ({
                                            ...itemOption(choice, mode),
                                            disabled: list.takenElsewhere(
                                                index,
                                                (row) => row.item_id,
                                                String(choice.id),
                                            ),
                                        }))}
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
                                    onClick={() => list.remove(index)}
                                    disabled={!list.canRemove}
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
                                            step={qtyStep(item?.unit)}
                                            unit={item?.unit}
                                            onChange={(value) =>
                                                list.update(index, {
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
                                                    list.update(index, {
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
                                        list.update(index, { price: value })
                                    }
                                />
                            </div>
                            <div className="flex items-center justify-between gap-3 border-t pt-2 text-sm">
                                <span className="text-muted-foreground">
                                    Subtotal
                                </span>
                                <Money value={lineSubtotal(line)} size="sm" />
                            </div>
                            <InputError
                                message={rowError(errors, 'lines', index, [
                                    'item_id',
                                    'qty',
                                    'price',
                                ])}
                            />
                        </li>
                    );
                })}
            </ol>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="w-fit"
                onClick={list.add}
                disabled={lines.length >= choices.length}
            >
                <Plus aria-hidden />
                Tambah barang
            </Button>
        </div>
    );
}

function itemOption(item: RecordItem, mode: LineMode): SearchSelectOption {
    const description =
        mode === 'sale'
            ? `${formatRupiah(item.price ?? 0)} · ${item.kind === ItemKind.Komposisi ? 'dari resep' : `stok ${formatQty(item.stock, item.unit)}`}`
            : item.averageCost > 0
              ? `${formatRupiah(Math.round(item.averageCost))}/${item.unit}`
              : `stok ${formatQty(item.stock, item.unit)}`;

    return {
        value: String(item.id),
        label: item.name,
        description,
        keywords: item.aliases,
        group: itemKindLabels[item.kind],
    };
}
