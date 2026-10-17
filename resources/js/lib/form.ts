import type { FormDataKeys, FormDataValues } from '@inertiajs/core';
import type { InertiaFormProps } from '@inertiajs/react';

// Helpers shared by the long forms (product, transaction). Plain functions:
// none of them hold state of their own, the Inertia form does.

type AnyForm = Record<string, unknown>;

/** Field errors keyed by path, "lines.0.qty" included. */
export function formErrors<T extends AnyForm>(
    form: InertiaFormProps<T>,
): Record<string, string | undefined> {
    return form.errors as Record<string, string | undefined>;
}

/** setData that also clears the field's error (and its rows' errors) as soon as it is edited. */
export function fieldSetter<T extends AnyForm>(form: InertiaFormProps<T>) {
    return <K extends FormDataKeys<T>>(key: K, value: FormDataValues<T, K>) => {
        form.setData(key, value);
        const stale = Object.keys(form.errors).filter(
            (name) => name === key || name.startsWith(`${String(key)}.`),
        );

        if (stale.length > 0) {
            form.clearErrors(...(stale as Array<FormDataKeys<T>>));
        }
    };
}

/** After a failed submit, move focus to the first field that has an error. */
export function focusFirstError(): void {
    requestAnimationFrame(() =>
        document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus(),
    );
}

/** Edits for a list of rows in a form (recipe lines, transaction lines). */
export function listEditor<Row>(
    rows: Row[],
    onChange: (rows: Row[]) => void,
    empty: () => Row,
) {
    return {
        update: (index: number, patch: Partial<Row>) =>
            onChange(
                rows.map((row, i) =>
                    i === index ? { ...row, ...patch } : row,
                ),
            ),
        remove: (index: number) => onChange(rows.filter((_, i) => i !== index)),
        add: () => onChange([...rows, empty()]),
        /** One row always stays, so the form never shows an empty list. */
        canRemove: rows.length > 1,
        /** A value picked in another row, so it cannot be picked twice. */
        takenElsewhere: (
            index: number,
            pick: (row: Row) => string,
            value: string,
        ) => rows.some((row, i) => i !== index && pick(row) === value),
    };
}

/** The first error of a row, e.g. rowError(errors, 'lines', 0, ['item_id', 'qty']). */
export function rowError(
    errors: Record<string, string | undefined>,
    list: string,
    index: number,
    fields: string[],
): string | undefined {
    return fields
        .map((field) => errors[`${list}.${index}.${field}`])
        .find(Boolean);
}
