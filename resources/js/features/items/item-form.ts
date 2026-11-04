import { decimalInput, parseDecimal } from '@/lib/number';
import type { ItemFormProps } from '@/types/domain';
import { ItemKind, soldItemKinds, stockedItemKinds } from '@/types/enums';
import { emptyRecipeRow, type RecipeRow } from './cost';
import { defaultUnit } from './vocabulary';

// The product form's state and what it sends; mirrors ItemRequest.

export type ItemFormData = {
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
    /** A new shop photo, already shrunk (lib/image.ts). */
    photo: File | null;
    remove_photo: boolean;
};

export const isSoldKind = (kind: ItemKind) => soldItemKinds.includes(kind);

export const isStockedKind = (kind: ItemKind) =>
    stockedItemKinds.includes(kind);

/** The form filled from the item being edited, or empty for a new one. */
export function initialItemForm(
    item: ItemFormProps['item'],
    kind: ItemKind,
): ItemFormData {
    return {
        kind,
        name: item?.name ?? '',
        aliases: item?.aliases.join(', ') ?? '',
        unit: item?.unit ?? defaultUnit(kind),
        price: item?.price ?? null,
        min_stock: decimalInput(item?.minStock),
        show_in_store: item?.showInStore ?? true,
        available: item?.available ?? true,
        opening_stock: '',
        opening_cost: null,
        recipe: item?.recipe.map((line) => ({
            material_id: String(line.material_id),
            qty: decimalInput(line.qty),
        })) ?? [emptyRecipeRow()],
        photo: null,
        remove_photo: false,
    };
}

/** Switching kind keeps a typed unit and swaps only the untouched default (pcs or porsi). */
export function withKind(values: ItemFormData, kind: ItemKind): ItemFormData {
    const untouched =
        values.unit === defaultUnit(ItemKind.Produk) ||
        values.unit === defaultUnit(ItemKind.Komposisi);

    return {
        ...values,
        kind,
        unit: untouched ? defaultUnit(kind) : values.unit,
    };
}

const decimalOrNull = (text: string) =>
    text.trim() === '' ? null : parseDecimal(text);

/** Only the fields the kind uses: no price for bahan, no stock for recipes, opening stock only when adding. */
export function toItemPayload(values: ItemFormData, editing: boolean) {
    const sold = isSoldKind(values.kind);
    const stocked = isStockedKind(values.kind);

    return {
        ...(editing ? {} : { kind: values.kind }),
        name: values.name,
        aliases: values.aliases,
        unit: values.unit,
        price: sold ? values.price : null,
        min_stock: stocked ? decimalOrNull(values.min_stock) : null,
        show_in_store: sold && values.show_in_store,
        available: values.available,
        ...(!editing && stocked
            ? {
                  opening_stock: decimalOrNull(values.opening_stock),
                  opening_cost: values.opening_cost,
              }
            : {}),
        // A file switches the request to multipart (forceFormData).
        ...(sold && values.photo ? { photo: values.photo } : {}),
        ...(values.remove_photo ? { remove_photo: true } : {}),
        ...(values.kind === ItemKind.Komposisi
            ? {
                  recipe: values.recipe.map((line) => ({
                      material_id:
                          line.material_id === ''
                              ? null
                              : Number(line.material_id),
                      qty: decimalOrNull(line.qty),
                  })),
              }
            : {}),
    };
}
