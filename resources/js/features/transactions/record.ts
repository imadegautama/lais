import type { FormDataKeys, FormDataValues } from '@inertiajs/core';
import { parseDecimal, roundHalfAway } from '@/lib/number';
import {
    afterStockIn,
    afterStockOut,
    averageCost,
    stockedParts,
    stockOutCost,
    type StockLevel,
} from '@/lib/stock';
import type {
    ContactOption,
    MoneyAccountOption,
    RecordFormData,
    RecordItem,
    RecordLine,
} from '@/types/domain';
import {
    ContactType,
    MoneyAccountKind,
    PaymentMethod,
    TransactionKind,
    TransactionType,
} from '@/types/enums';

// The record form's rules, kept out of the page so they can be tested.
// They mirror TransactionRequest and TransactionService on the server.

// The form's data shape lives with the page props, since "Ubah" sends it from the server.
export type { RecordFormData, RecordLine };

export type RecordFieldSetter = <K extends FormDataKeys<RecordFormData>>(
    key: K,
    value: FormDataValues<RecordFormData, K>,
) => void;

/** Lines either sell (selling price, stepper) or buy (purchase price, decimal input). */
export type LineMode = 'sale' | 'purchase';

export const emptyLine = (): RecordLine => ({
    item_id: '',
    qty: '1',
    price: null,
});

// Expense kind is left empty on purpose: the owner picks it, nothing is guessed.
export const DEFAULT_KIND: Record<TransactionType, TransactionKind | ''> = {
    penjualan: '',
    pembelian: TransactionKind.Barang,
    biaya: '',
    utang_piutang: TransactionKind.TerimaPiutang,
    modal: TransactionKind.Setor,
    pindah_dana: '',
};

/** Sales and goods purchases list items; everything else is one amount. */
export function hasLines(type: TransactionType, kind: string): boolean {
    return (
        type === TransactionType.Penjualan ||
        (type === TransactionType.Pembelian && kind === TransactionKind.Barang)
    );
}

export function lineSubtotal(line: RecordLine): number {
    return roundHalfAway((parseDecimal(line.qty) || 0) * (line.price ?? 0));
}

export function recordTotal(data: RecordFormData): number {
    return hasLines(data.type, data.kind)
        ? data.lines.reduce((sum, line) => sum + lineSubtotal(line), 0)
        : (data.amount ?? 0);
}

/** Money comes in: a sale, a customer paying, or capital paid in. */
export function isIncoming(type: TransactionType, kind: string): boolean {
    return (
        type === TransactionType.Penjualan ||
        kind === TransactionKind.TerimaPiutang ||
        kind === TransactionKind.Setor
    );
}

/** Customers buy and pay off their debts; suppliers are on the other side of everything else (ContactType::for). */
export function contactTypeFor(
    type: TransactionType,
    kind: string | null,
): ContactType {
    return type === TransactionType.Penjualan ||
        kind === TransactionKind.TerimaPiutang
        ? ContactType.Pelanggan
        : ContactType.Supplier;
}

/** Money into (+) or out of (−) the business till, same as transactions.cash_flow. */
export function cashEffect(
    type: TransactionType,
    kind: string,
    method: PaymentMethod,
    total: number,
    paid: number,
): number {
    // Personal money is not the business's; a transfer only moves it between rekening.
    if (
        method === PaymentMethod.Pribadi ||
        type === TransactionType.PindahDana
    ) {
        return 0;
    }

    if (method === PaymentMethod.Kredit) {
        return type === TransactionType.Penjualan ? paid : 0;
    }

    return isIncoming(type, kind) ? total : -total;
}

/**
 * The rekening the money goes through, picked the way TransactionService
 * does: Kas tunai for tunai, the only non-tunai rekening or the chosen one.
 */
export function chosenMoneyAccount(
    data: Pick<RecordFormData, 'type' | 'method' | 'money_account_id'>,
    moneyAccounts: MoneyAccountOption[],
): MoneyAccountOption | null {
    const byId = moneyAccounts.find(
        (account) => String(account.id) === data.money_account_id,
    );

    if (data.type === TransactionType.PindahDana) {
        return byId ?? null;
    }

    if (data.method === PaymentMethod.Tunai) {
        return (
            moneyAccounts.find(
                (account) => account.kind === MoneyAccountKind.Tunai,
            ) ?? null
        );
    }

    if (data.method !== PaymentMethod.NonTunai) {
        return null;
    }

    const nonCash = moneyAccounts.filter(
        (account) => account.kind === MoneyAccountKind.NonTunai,
    );

    return nonCash.length === 1
        ? nonCash[0]
        : byId?.kind === MoneyAccountKind.NonTunai
          ? byId
          : null;
}

/** Each rekening's balance before and after saving, for the impact panel. */
export function moneyImpact(
    data: RecordFormData,
    total: number,
    moneyAccounts: MoneyAccountOption[],
): Array<{ account: MoneyAccountOption; before: number; after: number }> {
    const move = (
        account: MoneyAccountOption | undefined | null,
        by: number,
    ) =>
        account
            ? [
                  {
                      account,
                      before: account.balance,
                      after: account.balance + by,
                  },
              ]
            : [];

    if (data.type === TransactionType.PindahDana) {
        const from = chosenMoneyAccount(data, moneyAccounts);
        const to = moneyAccounts.find(
            (account) => String(account.id) === data.to_money_account_id,
        );

        return [...move(from, -total), ...(to === from ? [] : move(to, total))];
    }

    const cash = cashEffect(
        data.type,
        data.kind,
        data.method,
        total,
        data.paid_amount ?? 0,
    );

    if (cash === 0) {
        return [];
    }

    // The part of a credit sale paid on the spot goes to Kas tunai (TransactionService::moneyLines).
    return move(
        data.method === PaymentMethod.Kredit
            ? chosenMoneyAccount(
                  { ...data, method: PaymentMethod.Tunai },
                  moneyAccounts,
              )
            : chosenMoneyAccount(data, moneyAccounts),
        cash,
    );
}

/** What a contact still owes, or is owed, after this payment. */
export function balanceAfter(contact: ContactOption, total: number): number {
    return Math.max(0, contact.balance - total);
}

export type ContactFieldConfig = {
    label: string;
    contactType: ContactType;
    required: boolean;
    creatable: boolean;
    /** Paying off: only contacts with something still open. */
    onlyOwing: boolean;
};

/** Which contact field a type shows, if any. */
export function contactFieldFor(
    type: TransactionType,
    kind: string,
    method: PaymentMethod,
): ContactFieldConfig | null {
    const credit = method === PaymentMethod.Kredit;

    if (type === TransactionType.UtangPiutang) {
        const contactType = contactTypeFor(type, kind);

        return {
            label:
                contactType === ContactType.Pelanggan
                    ? 'Pelanggan yang membayar'
                    : 'Supplier yang dibayar',
            contactType,
            required: true,
            creatable: false,
            onlyOwing: true,
        };
    }

    if (type === TransactionType.Penjualan) {
        return credit
            ? {
                  label: 'Pelanggan yang belum bayar',
                  contactType: ContactType.Pelanggan,
                  required: true,
                  creatable: true,
                  onlyOwing: false,
              }
            : null;
    }

    if (
        type === TransactionType.Pembelian ||
        (type === TransactionType.Biaya && credit)
    ) {
        return {
            label: credit
                ? 'Supplier yang belum dibayar'
                : 'Supplier (boleh kosong)',
            contactType: ContactType.Supplier,
            required: credit,
            creatable: true,
            onlyOwing: false,
        };
    }

    return null;
}

/** A new line starts at the selling price on a sale and at the average cost on a purchase. */
export function defaultPrice(
    item: RecordItem | undefined,
    mode: LineMode,
): number | null {
    if (!item) {
        return null;
    }

    if (mode === 'sale') {
        return item.price;
    }

    return item.averageCost > 0 ? Math.round(item.averageCost) : null;
}

const levelOf = (item: RecordItem): StockLevel => ({
    stock: item.stock,
    stockValue: item.stockValue,
});

/**
 * HPP and stock left after a sale, line by line in the order the server books
 * them, recipes exploded into their ingredients.
 */
export function saleImpact(
    lines: RecordLine[],
    items: Map<string, RecordItem>,
): {
    hpp: number;
    changes: Array<{ item: RecordItem; qty: number; after: number }>;
} {
    const levels = new Map<number, StockLevel>();
    const moved = new Map<number, { item: RecordItem; qty: number }>();
    let hpp = 0;

    for (const line of lines) {
        const item = items.get(line.item_id);
        const qty = parseDecimal(line.qty) || 0;

        if (!item || qty <= 0) {
            continue;
        }

        for (const part of stockedParts(item, qty)) {
            const stocked = items.get(String(part.id));

            if (!stocked) {
                continue;
            }

            const level = levels.get(part.id) ?? levelOf(stocked);
            hpp += stockOutCost(level, part.qty);
            levels.set(part.id, afterStockOut(level, part.qty));
            moved.set(part.id, {
                item: stocked,
                qty: (moved.get(part.id)?.qty ?? 0) + part.qty,
            });
        }
    }

    return {
        hpp,
        changes: [...moved].map(([id, { item, qty }]) => ({
            item,
            qty,
            after: levels.get(id)?.stock ?? item.stock,
        })),
    };
}

/** Stock added and the average cost before and after, per purchased line. */
export function purchaseImpact(
    lines: RecordLine[],
    items: Map<string, RecordItem>,
): Array<{ item: RecordItem; qty: number; before: number; average: number }> {
    return lines.flatMap((line) => {
        const item = items.get(line.item_id);
        const qty = parseDecimal(line.qty) || 0;

        if (!item || qty <= 0 || line.price === null) {
            return [];
        }

        const level = levelOf(item);
        const after = afterStockIn(level, qty, lineSubtotal(line));

        return [
            {
                item,
                qty,
                before: averageCost(level),
                average: averageCost(after),
            },
        ];
    });
}

/** What the form sends: only the fields its type uses, a known contact by id and a new one by name. */
export function toPayload(
    values: RecordFormData,
    again: boolean,
    contactIds: Set<string>,
) {
    const typed = values.contact.trim();
    const canAddContact =
        values.type !== TransactionType.Modal &&
        values.type !== TransactionType.UtangPiutang;
    const idOrNull = (value: string) => (value === '' ? null : Number(value));

    // A transfer is two rekening and an amount; its method follows the source.
    if (values.type === TransactionType.PindahDana) {
        return {
            type: values.type,
            money_account_id: idOrNull(values.money_account_id),
            to_money_account_id: idOrNull(values.to_money_account_id),
            amount: values.amount,
            occurred_at: values.occurred_at || null,
            note: values.note.trim() || null,
            again,
        };
    }

    return {
        type: values.type,
        ...(values.type === TransactionType.Penjualan
            ? {}
            : { kind: values.kind }),
        method: values.method,
        // Only non-tunai names its rekening; with one, the server picks it.
        ...(values.method === PaymentMethod.NonTunai &&
        values.money_account_id !== ''
            ? { money_account_id: Number(values.money_account_id) }
            : {}),
        occurred_at: values.occurred_at || null,
        note: values.note.trim() || null,
        again,
        ...(hasLines(values.type, values.kind)
            ? {
                  lines: values.lines.map((line) => ({
                      item_id:
                          line.item_id === '' ? null : Number(line.item_id),
                      qty:
                          line.qty.trim() === ''
                              ? null
                              : parseDecimal(line.qty),
                      price: line.price,
                  })),
              }
            : { amount: values.amount }),
        ...(values.type === TransactionType.Penjualan &&
        values.method === PaymentMethod.Kredit
            ? { paid_amount: values.paid_amount ?? 0 }
            : {}),
        ...(contactIds.has(values.contact)
            ? { contact_id: Number(values.contact) }
            : typed !== '' && canAddContact
              ? { contact_name: typed }
              : {}),
    };
}
