import { Link } from '@inertiajs/react';
import { Plus, Wheat, X } from 'lucide-react';
import { Money } from '@/components/app/money';
import { SearchSelect } from '@/components/app/search-select';
import { SectionCard } from '@/components/app/section-card';
import { EmptyState } from '@/components/app/states';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRupiah } from '@/lib/format';
import { listEditor, rowError } from '@/lib/form';
import { cn } from '@/lib/utils';
import { create } from '@/routes/items';
import type { RecipeMaterial } from '@/types/domain';
import { ItemKind, itemKindLabels } from '@/types/enums';
import { emptyRecipeRow, recipeLineCost, type RecipeRow } from './cost';

/** The ingredients of one portion, each with its cost at the average price. */
export function RecipeBuilder({
    lines,
    materials,
    errors,
    onChange,
}: {
    lines: RecipeRow[];
    materials: RecipeMaterial[];
    errors: Record<string, string | undefined>;
    onChange: (lines: RecipeRow[]) => void;
}) {
    const materialById = new Map(materials.map((m) => [String(m.id), m]));
    const list = listEditor(lines, onChange, emptyRecipeRow);

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
                            <Link
                                href={create({
                                    query: { jenis: ItemKind.Bahan },
                                })}
                            >
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
                            const cost = Math.round(
                                recipeLineCost(line, materialById),
                            );

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
                                            <SearchSelect
                                                id={`recipe-${index}-material`}
                                                value={line.material_id}
                                                onChange={(value) =>
                                                    list.update(index, {
                                                        material_id: value,
                                                    })
                                                }
                                                options={materials.map((m) => ({
                                                    value: String(m.id),
                                                    label: m.name,
                                                    group: itemKindLabels[
                                                        m.kind
                                                    ],
                                                    description:
                                                        m.averageCost > 0
                                                            ? `${formatRupiah(m.averageCost)}/${m.unit}`
                                                            : `belum ada biaya/${m.unit}`,
                                                    disabled:
                                                        list.takenElsewhere(
                                                            index,
                                                            (row) =>
                                                                row.material_id,
                                                            String(m.id),
                                                        ),
                                                }))}
                                                placeholder="Pilih bahan"
                                                searchPlaceholder="Cari bahan"
                                                emptyText="Bahan tidak ditemukan. Tambahkan dulu di Produk & stok."
                                                invalid={Boolean(
                                                    errors[
                                                        `recipe.${index}.material_id`
                                                    ],
                                                )}
                                            />
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
                                                        list.update(index, {
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
                                                value={cost}
                                                size="sm"
                                                className="h-9 items-center"
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => list.remove(index)}
                                            disabled={!list.canRemove}
                                            aria-label={`Hapus ${material?.name ?? `baris ${index + 1}`} dari resep`}
                                        >
                                            <X aria-hidden />
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground md:hidden">
                                        Biaya{' '}
                                        <Money
                                            value={cost}
                                            size="sm"
                                            className="text-xs"
                                        />
                                    </p>
                                    <InputError
                                        message={rowError(
                                            errors,
                                            'recipe',
                                            index,
                                            ['material_id', 'qty'],
                                        )}
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
