import type {
    ContactType,
    ItemKind,
    PaymentMethod,
    Period as PeriodKey,
    StockReason as StockMovementReason,
    TransactionKind,
    TransactionSource,
    TransactionType,
} from '@/types/enums';
import type { TablePage } from '@/types/ui';

// Shapes of the page props. The vocabulary itself comes from app/Enums
// through the generated enums.ts. Money is integer rupiah everywhere.

export type {
    ContactType,
    ItemKind,
    PaymentMethod,
    PeriodKey,
    StockMovementReason,
    TransactionKind,
    TransactionSource,
    TransactionType,
};

export type Rupiah = number;

export type MoneyDirection = 'in' | 'out' | 'none';

export type OrderStatus =
    | 'menunggu'
    | 'diproses'
    | 'siap'
    | 'selesai'
    | 'ditolak';

export type PaymentStatus = 'belum' | 'dp' | 'lunas';

export type Period = {
    key: PeriodKey;
    label: string;
    /** Named comparison, e.g. "vs kemarin". Null when there is no earlier data. */
    comparisonLabel: string | null;
};

export type Metric = {
    value: Rupiah;
    /** Same metric for the comparison period, null when it does not exist. */
    previous: Rupiah | null;
};

export type TransactionRow = {
    id: number;
    occurredAt: string;
    type: TransactionType;
    description: string;
    method: PaymentMethod;
    source: TransactionSource;
    amount: Rupiah;
    direction: MoneyDirection;
    /** Customer or supplier name, when there is one. */
    contact: string | null;
    voided: boolean;
};

/** A link target that exists, or an honest "not built yet" marker. */
export type ActionTarget = { href: string } | { comingSoon: true };

export type ActionItem = {
    id: string;
    kind: 'pesanan' | 'stok' | 'piutang';
    title: string;
    detail: string;
    actionLabel: string;
    target: ActionTarget;
};

export type HealthRule = {
    key: 'laba' | 'kas' | 'utang' | 'prive';
    label: string;
    passed: boolean;
    detail: string;
};

export type StockAlert = {
    id: string;
    name: string;
    unit: string;
    stock: number;
    minimum: number;
    /** Null when there are fewer than 3 days of usage data. */
    daysLeft: number | null;
};

export type DashboardProps = {
    isDemo: boolean;
    isEmpty: boolean;
    business: { name: string; ownerName: string };
    period: Period;
    periods: Array<Pick<Period, 'key' | 'label'>>;
    summary: {
        netProfit: Metric;
        sales: Metric & { count: number };
        cash: { total: Rupiah; onHand: Rupiah; nonCash: Rupiah };
        personalDraw: Metric;
    };
    actions: ActionItem[];
    wallets: {
        businessCash: Rupiah;
        drawnThisMonth: Rupiah;
        netProfitThisMonth: Rupiah;
        equityOpening: Rupiah;
        equityNow: Rupiah;
    };
    health: HealthRule[];
    stockAlerts: StockAlert[];
    salesChart: Array<{ date: string; sales: Rupiah }>;
    recentTransactions: TransactionRow[];
};

/** One row of the product list, as sent by ItemController::row(). */
export type ItemRow = {
    id: number;
    kind: ItemKind;
    name: string;
    aliases: string[];
    unit: string;
    /** Null for bahan, which is not sold. */
    price: Rupiah | null;
    stock: number;
    stockValue: Rupiah;
    minStock: number | null;
    /** Recipe total for komposisi, weighted average cost otherwise. */
    unitCost: Rupiah;
    marginPercent: number | null;
    showInStore: boolean;
    available: boolean;
    lowStock: boolean;
    negativeStock: boolean;
};

export type RecipeLineInput = { material_id: number; qty: number };

/** An item that can go into a recipe. */
export type RecipeMaterial = {
    id: number;
    kind: Extract<ItemKind, 'produk' | 'bahan'>;
    name: string;
    unit: string;
    averageCost: number;
};

export type StockMovementRow = {
    id: number;
    occurredAt: string;
    reason: StockMovementReason;
    qty: number;
    value: Rupiah;
};

export type ItemsIndexProps = {
    items: TablePage<ItemRow>;
    /** Totals over every item, not just the current page. */
    summary: {
        itemCount: number;
        stockedCount: number;
        inventoryValue: Rupiah;
        lowStockCount: number;
        /** At most two names; the count says how many more. */
        lowStockNames: string[];
        negativeStockCount: number;
        negativeStockNames: string[];
    };
};

export type ItemFormProps = {
    item: (ItemRow & { recipe: RecipeLineInput[] }) | null;
    initialKind: ItemKind;
    materials: RecipeMaterial[];
    /** Units this business already uses. */
    units: string[];
    movements: StockMovementRow[];
};

/** An item the record form can sell or buy, with what the impact preview needs. */
export type RecordItem = {
    id: number;
    kind: ItemKind;
    name: string;
    aliases: string[];
    unit: string;
    price: Rupiah | null;
    stock: number;
    stockValue: Rupiah;
    averageCost: number;
    recipe: Array<{ materialId: number; qty: number }>;
};

export type ContactOption = {
    id: number;
    type: ContactType;
    name: string;
    /** Still owed by a customer, or still owed to a supplier. */
    balance: Rupiah;
};

export type TransactionsIndexProps = {
    transactions: TablePage<TransactionRow> & {
        summary: { cashIn: Rupiah; cashOut: Rupiah; count: number };
    };
    hasTransactions: boolean;
};

export type TransactionCreateProps = {
    initialType: TransactionType;
    /** Server time (WIB) as a datetime-local value. */
    now: string;
    items: RecordItem[];
    contacts: ContactOption[];
};

export type TransactionDetailProps = {
    transaction: TransactionRow & {
        kind: TransactionKind | null;
        note: string | null;
        paidAmount: Rupiah;
        cashFlow: Rupiah;
        voidedAt: string | null;
        voidReason: string | null;
    };
    lines: Array<{
        id: number;
        name: string;
        unit: string;
        qty: number;
        price: Rupiah;
        subtotal: Rupiah;
        hpp: Rupiah | null;
    }>;
    movements: Array<{
        id: number;
        name: string;
        unit: string;
        qty: number;
        value: Rupiah;
        stockAfter: number | null;
        reason: StockMovementReason;
    }>;
    journal: Array<{
        id: number;
        description: string;
        occurredAt: string;
        lines: Array<{
            code: string;
            account: string;
            debit: Rupiah;
            credit: Rupiah;
        }>;
    }>;
};
