import type { SearchSelectOption } from '@/components/app/search-select';
import { ItemKind } from '@/types/enums';

/** The order the kind picker offers them in: what most owners start with first. */
export const ITEM_KIND_ORDER: readonly ItemKind[] = [
    ItemKind.Produk,
    ItemKind.Komposisi,
    ItemKind.Bahan,
];

/** What each kind means, with examples, and example inputs for the form. */
export const itemKindCopy: Record<
    ItemKind,
    { hint: string; nameExample: string; aliasExample: string }
> = {
    produk: {
        hint: 'Dibeli atau dibuat lalu dijual apa adanya: air mineral, sabun jadi.',
        nameExample: 'Air Mineral 600 ml',
        aliasExample: 'aqua, air',
    },
    bahan: {
        hint: 'Tidak dijual langsung, dipakai di resep: beras, telur, minyak.',
        nameExample: 'Beras',
        aliasExample: 'minyak, migor',
    },
    komposisi: {
        hint: 'Dibuat dari bahan saat dijual: nasi goreng, es teh, kue.',
        nameExample: 'Nasi Goreng',
        aliasExample: 'nasgor, nasi goreng biasa',
    },
};

// Common units, grouped the way owners think about them. Units are free
// text in the database; one item has one unit until conversions land (v1.1).
const UNIT_GROUPS: Array<[string, string[]]> = [
    [
        'Jumlah',
        ['pcs', 'porsi', 'gelas', 'botol', 'bungkus', 'butir', 'kantong'],
    ],
    ['Berat', ['kg', 'gram']],
    ['Volume', ['liter', 'ml']],
];

/** The business's own units first, then the common ones. */
export function unitOptions(used: string[]): SearchSelectOption[] {
    const common = UNIT_GROUPS.flatMap(([group, units]) =>
        units.map((unit) => ({ value: unit, label: unit, group })),
    );
    const own = used
        .filter((unit) => !common.some((option) => option.value === unit))
        .map((unit) => ({
            value: unit,
            label: unit,
            group: 'Dipakai di usahamu',
        }));

    return [...own, ...common];
}

/** A recipe is sold by the portion; everything else starts as pieces. */
export function defaultUnit(kind: ItemKind): string {
    return kind === ItemKind.Komposisi ? 'porsi' : 'pcs';
}
