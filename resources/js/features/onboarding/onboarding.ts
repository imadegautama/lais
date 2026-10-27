import type { FormDataKeys, FormDataValues } from '@inertiajs/core';
import { decimalInput, parseDecimal, roundHalfAway } from '@/lib/number';
import type { Rupiah } from '@/types/domain';
import {
    ItemKind,
    type BusinessCategory,
    type MoneyAccountKind,
} from '@/types/enums';

// The onboarding wizard's rules, kept out of the page so they can be tested.
// They mirror OnboardingRequest and App\Services\Onboarding on the server.

export const STEPS = [
    { key: 'usaha', label: 'Usaha' },
    { key: 'uang', label: 'Uang' },
    { key: 'peralatan', label: 'Peralatan' },
    { key: 'produk', label: 'Produk' },
    { key: 'utang', label: 'Utang & piutang' },
    { key: 'ringkasan', label: 'Ringkasan' },
] as const;

export type StepKey = (typeof STEPS)[number]['key'];

export type AccountRow = {
    /** null for a rekening added in the wizard. */
    id: number | null;
    kind: MoneyAccountKind;
    name: string;
    balance: Rupiah | null;
};

/** Equipment, an opening debt, or an opening receivable. */
export type AmountRow = { name: string; amount: Rupiah | null };

export type ProductRow = {
    name: string;
    kind: ItemKind;
    unit: string;
    price: Rupiah | null;
    min_stock: number | null;
    /** As typed, comma decimals allowed. */
    opening_stock: string;
    opening_cost: Rupiah | null;
    aliases: string[];
    recipe: Array<{ name: string; qty: number }>;
    /** Filled by "Pakai contoh"; the owner checks the numbers. */
    example: boolean;
};

export type OnboardingDraft = {
    /** The last step saved, so the wizard resumes after it. */
    step: StepKey | '';
    name: string;
    category: BusinessCategory | '';
    started_on: string;
    accounts: AccountRow[];
    equipment: AmountRow[];
    products: ProductRow[];
    debts: AmountRow[];
    receivables: AmountRow[];
};

export type OnboardingSetter = <K extends FormDataKeys<OnboardingDraft>>(
    key: K,
    value: FormDataValues<OnboardingDraft, K>,
) => void;

/** A row of App\Support\StarterCatalog. */
export type StarterRow = Omit<ProductRow, 'opening_stock' | 'example'> & {
    opening_stock: number;
};

export const emptyAmountRow = (): AmountRow => ({ name: '', amount: null });

export const emptyProductRow = (): ProductRow => ({
    name: '',
    kind: ItemKind.Produk,
    unit: '',
    price: null,
    min_stock: null,
    opening_stock: '',
    opening_cost: null,
    aliases: [],
    recipe: [],
    example: false,
});

/** The saved draft, or a fresh one from what the business already has. */
export function initialDraft({
    draft,
    name,
    category,
    accounts,
    latestStart,
}: {
    draft: Partial<OnboardingDraft> | null;
    name: string;
    category: BusinessCategory | null;
    accounts: Array<{ id: number; kind: MoneyAccountKind; name: string }>;
    latestStart: string;
}): OnboardingDraft {
    return {
        step: '',
        name,
        category: category ?? '',
        started_on: latestStart,
        accounts: accounts.map((account) => ({ ...account, balance: null })),
        equipment: [],
        products: [],
        debts: [],
        receivables: [],
        ...draft,
    };
}

/** Where to pick up: the step after the last one saved. */
export function resumeIndex(draft: OnboardingDraft): number {
    const saved = STEPS.findIndex((step) => step.key === draft.step);

    return saved === -1 ? 0 : Math.min(saved + 1, STEPS.length - 1);
}

/** "Pakai contoh": the starter rows not already in the list. */
export function withExamples(
    products: ProductRow[],
    starters: StarterRow[],
): ProductRow[] {
    const taken = new Set(products.map((row) => row.name.trim().toLowerCase()));

    return [
        ...products,
        ...starters
            .filter((row) => !taken.has(row.name.toLowerCase()))
            .map((row) => ({
                ...row,
                opening_stock: decimalInput(row.opening_stock || null),
                example: true,
            })),
    ];
}

const stockValue = (row: ProductRow) =>
    row.kind === ItemKind.Komposisi
        ? 0
        : roundHalfAway(
              (parseDecimal(row.opening_stock) || 0) * (row.opening_cost ?? 0),
          );

const sum = (rows: AmountRow[]) =>
    rows.reduce((total, row) => total + (row.amount ?? 0), 0);

/**
 * The first balance sheet: what the business has minus what it owes is the
 * owner's opening capital (Modal awal), so it balances by construction.
 * existingStock is the opening stock of products added before the wizard.
 */
export function openingBalance(draft: OnboardingDraft, existingStock: Rupiah) {
    const cash = draft.accounts.reduce(
        (total, account) => total + (account.balance ?? 0),
        0,
    );
    const equipment = sum(draft.equipment);
    const inventory =
        existingStock +
        draft.products.reduce((total, row) => total + stockValue(row), 0);
    const receivables = sum(draft.receivables);
    const assets = cash + equipment + inventory + receivables;
    const debts = sum(draft.debts);

    return {
        cash,
        equipment,
        inventory,
        receivables,
        assets,
        debts,
        capital: assets - debts,
    };
}

const blankAmount = (row: AmountRow) =>
    row.name.trim() === '' && row.amount === null;

const blankProduct = (row: ProductRow) =>
    row.name.trim() === '' &&
    row.unit.trim() === '' &&
    row.price === null &&
    row.opening_stock.trim() === '' &&
    row.opening_cost === null;

/**
 * What the wizard sends: rows left completely empty are dropped, numbers are
 * read the way the owner typed them, and the "contoh" marker stays here.
 */
export function toPayload(draft: OnboardingDraft) {
    return {
        name: draft.name.trim(),
        category: draft.category || null,
        started_on: draft.started_on,
        accounts: draft.accounts.map((account) => ({
            id: account.id,
            name: account.name.trim(),
            balance: account.balance,
        })),
        equipment: draft.equipment.filter((row) => !blankAmount(row)),
        products: draft.products
            .filter((row) => !blankProduct(row))
            .map(({ example: _example, ...row }) => ({
                ...row,
                name: row.name.trim(),
                price: row.kind === ItemKind.Bahan ? null : row.price,
                opening_stock:
                    row.opening_stock.trim() === ''
                        ? null
                        : parseDecimal(row.opening_stock),
            })),
        debts: draft.debts.filter((row) => !blankAmount(row)),
        receivables: draft.receivables.filter((row) => !blankAmount(row)),
    };
}

const STEP_OF_FIELD: Record<string, StepKey> = {
    name: 'usaha',
    category: 'usaha',
    started_on: 'usaha',
    accounts: 'uang',
    equipment: 'peralatan',
    products: 'produk',
    debts: 'utang',
    receivables: 'utang',
};

/** After a failed finish, the earliest step that has an error. */
export function firstStepWithError(
    errors: Record<string, string | undefined>,
): StepKey | null {
    const steps = Object.keys(errors)
        .filter((key) => errors[key])
        .map((key) => STEP_OF_FIELD[key.split('.')[0]])
        .filter(Boolean);

    return STEPS.find((step) => steps.includes(step.key))?.key ?? null;
}
