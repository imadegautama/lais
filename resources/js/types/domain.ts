import type {
    AccountType,
    ActivityCategory,
    ContactType,
    ItemKind,
    MoneyAccountKind,
    OrderFulfillment,
    OrderStatus,
    PaymentMethod,
    PaymentStatus,
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
    ActivityCategory,
    ContactType,
    ItemKind,
    MoneyAccountKind,
    OrderFulfillment,
    OrderStatus,
    PaymentMethod,
    PaymentStatus,
    PeriodKey,
    StockMovementReason,
    TransactionKind,
    TransactionSource,
    TransactionType,
};

export type Rupiah = number;

export type MoneyDirection = 'in' | 'out' | 'none';

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
    /** The rekening the money moved through; the source of a Pindah dana. */
    moneyAccount?: string | null;
    /** The target of a Pindah dana. */
    toMoneyAccount?: string | null;
    voided: boolean;
};

/** A rekening with its balance (MoneyAccountResource). */
export type MoneyAccountOption = {
    id: number;
    kind: MoneyAccountKind;
    name: string;
    balance: Rupiah;
};

export type MoneyAccountsIndexProps = {
    needsOnboarding: boolean;
    /** Kas tunai first, then the rekening in the order they were added. */
    accounts: MoneyAccountOption[];
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
    /** The item's id. */
    id: number;
    name: string;
    unit: string;
    stock: number;
    /** Null when the item has no minimum; it alerts on days left alone. */
    minimum: number | null;
    /** Null when there are fewer than 3 days of usage data. */
    daysLeft: number | null;
};

export type DashboardProps = {
    /** The books are not opened yet (no saldo awal). */
    needsOnboarding: boolean;
    /** No record yet besides the saldo awal. */
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
    /** Shown on the shop page; sold kinds only. */
    photoUrl: string | null;
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

/** StockMovementResource. Name and unit come along on lists that mix items. */
export type StockMovementRow = {
    id: number;
    occurredAt: string;
    reason: StockMovementReason;
    qty: number;
    value: Rupiah;
    /** Null on movements recorded before this was kept. */
    stockAfter: number | null;
    name?: string;
    unit?: string;
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
    /** The product's Riwayat; only when editing. */
    activities?: ActivityEntry[];
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

export type RecordLine = { item_id: string; qty: string; price: number | null };

/** The record form's data (C3), also sent filled in by "Ubah" (TransactionFormResource). */
export type RecordFormData = {
    type: TransactionType;
    kind: TransactionKind | '';
    /** An account the business added, for a biaya or a pemasukan lain. */
    account_id: string;
    /** How long a piece of peralatan lasts, as the owner types it. */
    useful_life_years: string;
    method: PaymentMethod;
    occurred_at: string;
    lines: RecordLine[];
    amount: number | null;
    paid_amount: number | null;
    /** A contact id, or a new name typed into the field. */
    contact: string;
    note: string;
    /** The chosen rekening (non-tunai), or the source of a Pindah dana. */
    money_account_id: string;
    /** The target of a Pindah dana. */
    to_money_account_id: string;
};

export type OwnAccountOption = {
    id: number;
    name: string;
    type: AccountType;
};

export type TransactionCreateProps = {
    initialType: TransactionType;
    /** Server time (WIB) as a datetime-local value. */
    now: string;
    items: RecordItem[];
    contacts: ContactOption[];
    /** Active rekening with their balances; as if the edited record were cancelled. */
    moneyAccounts: MoneyAccountOption[];
    /** The accounts this business added and still uses. */
    ownAccounts: OwnAccountOption[];
    /** Set when the form edits a record ("Ubah"); its proof moves to the new version unless replaced or removed. */
    editing?: { id: number; description: string; proofUrl: string | null };
    initial?: RecordFormData;
};

export type TransactionDetailProps = {
    transaction: TransactionRow & {
        kind: TransactionKind | null;
        note: string | null;
        paidAmount: Rupiah;
        cashFlow: Rupiah;
        voidedAt: string | null;
        voidReason: string | null;
        /** The record "Ubah" saved in place of this one. */
        replacedById: number | null;
        /** The record this one replaced. */
        replacesId: number | null;
        /** Set when an order made it; it is changed from that order only. */
        orderCode: string | null;
        /** The proof photo, served only to the owner. */
        proofUrl: string | null;
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
    movements: StockMovementRow[];
    journal: Array<{
        id: number;
        description: string;
        occurredAt: string;
        lines: Array<{
            code: string;
            account: string;
            /** Which rekening, on a 1101/1102 line. */
            moneyAccount: string | null;
            debit: Rupiah;
            credit: Rupiah;
        }>;
    }>;
    activities: ActivityEntry[];
};

/** One audit-log entry (ActivityResource): Log aktivitas and each record's Riwayat. */
export type ActivityEntry = {
    id: number;
    at: string;
    category: ActivityCategory;
    event: string | null;
    /** Who did it; "Tidak dikenal" for a failed sign-in, "Sistem" without a user. */
    actor: string;
    source: TransactionSource | null;
    /** The whole sentence, written when it happened: "Mengubah produk Nasi Goreng". */
    description: string;
    /** Reason, email tried, IP, or the value of a stock count. */
    detail: string | null;
    /** The record it is about, while it can still be opened. */
    href: string | null;
    changes: Array<{ label: string; old: string; new: string }>;
};

export type ActivityIndexProps = {
    activities: TablePage<ActivityEntry>;
};

/** What every report page gets around its numbers (ReportController::frame). */
export type ReportFrame = {
    business: { name: string };
    /** `key` is null when the owner picked the dates. */
    period: { key: PeriodKey | null; label: string };
    /** How the column of the earlier period is headed. */
    previousLabel: string;
    periods: Array<Pick<Period, 'key' | 'label'>>;
    /** The days covered, Y-m-d, both inclusive. */
    range: { from: string; to: string };
    /** The query that brings this range back, carried between reports. */
    query: Record<string, string>;
    /** When the books were opened, Y-m-d. */
    startedOn: string | null;
};

/** ReportStory: what the report says, before any figure. */
export type ReportStory = {
    tone: 'good' | 'warning' | 'bad' | 'neutral';
    headline: string;
    detail: string;
};

export type MonthlyTrend = Array<{
    month: string;
    label: string;
    sales: Rupiah;
    netProfit: Rupiah;
}>;

export type ReportsIndexProps = ReportFrame & {
    story: ReportStory;
    trend: MonthlyTrend;
    netProfit: Rupiah;
    previousNetProfit: Rupiah;
    sales: Rupiah;
    closingEquity: Rupiah;
    balanceSheet: BalanceSheet;
    cashChange: Rupiah;
    money: Rupiah;
    topProduct: string | null;
    equipmentValue: Rupiah;
    accountCount: number;
    balancedTotal: Rupiah;
    unsetAssets: number;
};

/** Reports::profitAndLoss + expensesByKind. */
type ProfitAndLoss = {
    sales: Rupiah;
    cogs: Rupiah;
    grossProfit: Rupiah;
    otherIncome: Rupiah;
    expenses: Rupiah;
    depreciation: Rupiah;
    otherExpenses: Rupiah;
    stockDifference: Rupiah;
    netProfit: Rupiah;
    expensesByKind: Partial<Record<TransactionKind, Rupiah>>;
    ownAccounts: Array<{
        code: string;
        name: string;
        type: AccountType;
        amount: Rupiah;
    }>;
};

export type ProfitAndLossProps = ReportFrame & {
    story: ReportStory;
    report: ProfitAndLoss;
    /** The whole period before, for the comparative column. */
    previous: ProfitAndLoss;
    trend: MonthlyTrend;
};

/** Reports::cashReconciliation: each step is its effect on cash. */
export type CashStepKey =
    | 'inventory'
    | 'receivables'
    | 'equipment'
    | 'depreciation'
    | 'payables'
    | 'customerDeposits'
    | 'deposits'
    | 'drawings';

export type EquityProps = ReportFrame & {
    story: ReportStory;
    report: {
        opening: Rupiah;
        deposits: Rupiah;
        netProfit: Rupiah;
        drawings: Rupiah;
        closing: Rupiah;
    };
    reconciliation: {
        netProfit: Rupiah;
        steps: Array<{ key: CashStepKey; amount: Rupiah }>;
        cashChange: Rupiah;
        difference: Rupiah;
    };
};

/** Reports::balanceSheet, at the end of a day. */
export type BalanceSheet = {
    assets: {
        cash: Rupiah;
        nonCash: Rupiah;
        receivables: Rupiah;
        inventory: Rupiah;
        equipment: Rupiah;
        /** Negative: what has been depreciated so far. */
        accumulatedDepreciation: Rupiah;
        total: Rupiah;
    };
    liabilities: { payables: Rupiah; customerDeposits: Rupiah; total: Rupiah };
    equity: {
        capital: Rupiah;
        drawings: Rupiah;
        profit: Rupiah;
        total: Rupiah;
    };
    balanced: boolean;
};

export type BalanceSheetProps = ReportFrame & {
    story: ReportStory;
    /** Y-m-d, never after today. */
    date: string;
    today: string;
    report: BalanceSheet;
    /** The end of the month before, for the comparative column. */
    previous: BalanceSheet;
    previousDate: string;
};

/** Reports::cashBook, for every rekening or the one chosen. */
export type CashBookProps = ReportFrame & {
    story: ReportStory;
    moneyAccounts: Array<{ id: number; name: string }>;
    moneyAccountId: number | null;
    book: {
        opening: Rupiah;
        closing: Rupiah;
        totalIn: Rupiah;
        totalOut: Rupiah;
        lines: Array<{
            occurredAt: string;
            description: string;
            account: string;
            in: Rupiah;
            out: Rupiah;
            balance: Rupiah;
        }>;
    };
};

/** Reports::productPerformance and Reports::slowStock. */
export type ProductsReportProps = ReportFrame & {
    story: ReportStory;
    products: Array<{
        id: number;
        name: string;
        unit: string;
        qty: number;
        sales: Rupiah;
        cogs: Rupiah;
        profit: Rupiah;
        margin: number | null;
    }>;
    slow: Array<{
        id: number;
        name: string;
        stock: number;
        unit: string;
        value: Rupiah;
        lastSoldAt: string | null;
    }>;
};

/** Depreciation::book. `lifeMonths` is null until the owner sets it; `locked` once a month is posted. */
export type FixedAsset = {
    id: number;
    name: string;
    cost: Rupiah;
    acquiredOn: string;
    lifeMonths: number | null;
    accumulated: Rupiah;
    bookValue: Rupiah;
    locked: boolean;
};

export type FixedAssetsProps = {
    business: { name: string };
    story: ReportStory;
    assets: FixedAsset[];
};

/** Reports::chart: debit and credit are the neraca saldo's columns. */
export type ChartAccount = {
    id: number;
    code: string;
    name: string;
    type: AccountType;
    system: boolean;
    archived: boolean;
    debit: Rupiah;
    credit: Rupiah;
    balance: Rupiah;
};

export type ChartProps = ReportFrame & {
    accounts: ChartAccount[];
    expensesByKind: Partial<Record<TransactionKind, Rupiah>>;
};

export type TrialBalanceProps = ReportFrame & {
    story: ReportStory;
    date: string;
    today: string;
    accounts: ChartAccount[];
};

export type LedgerProps = ReportFrame & {
    account: { code: string; name: string };
    ledger: {
        opening: Rupiah;
        closing: Rupiah;
        lines: Array<{
            occurredAt: string;
            description: string;
            debit: Rupiah;
            credit: Rupiah;
            balance: Rupiah;
        }>;
    };
};

/** Catatan atas laporan keuangan: details add up to the position's lines. */
export type NotesProps = ReportFrame & {
    category: string | null;
    today: string;
    position: BalanceSheet;
    details: {
        money: Array<{ name: string; amount: Rupiah }>;
        inventory: Array<{
            name: string;
            qty: string;
            unit: string;
            amount: Rupiah;
        }>;
        equipment: FixedAsset[];
        receivables: Array<{ name: string; amount: Rupiah }>;
        payables: Array<{ name: string; amount: Rupiah }>;
    };
    expensesByKind: Partial<Record<TransactionKind, Rupiah>>;
    previousExpensesByKind: Partial<Record<TransactionKind, Rupiah>>;
};

/** The shop's face (A2–A4); `whatsapp` is already in 62… form. */
export type ShopInfo = {
    name: string;
    slug: string;
    open: boolean;
    whatsapp: string | null;
    qrisUrl: string | null;
};

/** A product on the order form. The price is shown, never sent back. */
export type ShopItem = {
    id: number;
    name: string;
    unit: string;
    price: Rupiah;
    photoUrl: string | null;
    orderable: boolean;
};

export type OrderLine = {
    name: string;
    unit: string;
    qty: number;
    price: Rupiah;
    subtotal: Rupiah;
};

/** An order as the customer's nota shows it. */
export type PublicOrder = {
    code: string;
    status: OrderStatus;
    customerName: string;
    fulfillment: OrderFulfillment;
    address: string | null;
    wantedAt: string | null;
    note: string | null;
    total: Rupiah;
    /** Uang muka and payments after Selesai, never the sale itself. */
    paid: Rupiah;
    paymentStatus: PaymentStatus;
    createdAt: string;
    acceptedAt: string | null;
    readyAt: string | null;
    completedAt: string | null;
    rejectedAt: string | null;
    rejectReason: string | null;
    token: string;
    lines: OrderLine[];
};

export type OrderPayment = {
    id: number;
    occurredAt: string;
    label: string;
    method: PaymentMethod;
    moneyAccount: string | null;
    amount: Rupiah;
    hasProof: boolean;
};

export type OwnerOrder = PublicOrder & {
    customerPhone: string;
    source: TransactionSource;
    /** The sale recorded when the order was done. */
    saleId: number | null;
    payments: OrderPayment[];
    /** Settled from the Utang-piutang page, so not among `payments`. */
    paidElsewhere: Rupiah;
};

export type ShopShowProps = { shop: ShopInfo; items: ShopItem[] };

export type ShopOrderProps = { shop: ShopInfo; order: PublicOrder };

export type OrdersIndexProps = {
    shop: ShopInfo & { url: string };
    status: OrderStatus;
    counts: Record<OrderStatus, number>;
    /** One page of the tab, searched and sorted on the server (TableQuery). */
    orders: TablePage<OwnerOrder>;
    /** Every product with a price, for an order the owner enters. */
    items: ShopItem[];
    moneyAccounts: MoneyAccountOption[];
};

export type OrderShowProps = {
    shop: ShopInfo & { url: string };
    order: OwnerOrder;
    notaUrl: string;
    /** "Kirim kabar": the news for the customer, written by App\Support\OrderMessages. */
    statusMessage: string;
    moneyAccounts: MoneyAccountOption[];
};

/** A customer or supplier on the debts page; the phone is for "Ingatkan". */
export type DebtContact = {
    id: number;
    name: string;
    type: ContactType;
    phone: string | null;
};

export type DebtRow = DebtContact & {
    balance: Rupiah;
    /** The oldest bill still open. */
    since: string;
    ageDays: number;
    open: number;
};

export type DebtTab = 'piutang' | 'utang';

export type DebtsIndexProps = {
    tab: DebtTab;
    totals: Record<
        DebtTab,
        { balance: Rupiah; contacts: number; oldestDays: number | null }
    >;
    rows: DebtRow[];
    moneyAccounts: MoneyAccountOption[];
    businessName: string;
};

export type DebtBill = {
    transactionId: number;
    occurredAt: string;
    description: string;
    orderCode: string | null;
    amount: Rupiah;
    remaining: Rupiah;
    ageDays: number;
};

export type DebtPayment = {
    transactionId: number;
    occurredAt: string;
    orderCode: string | null;
    method: PaymentMethod;
    moneyAccount: string | null;
    amount: Rupiah;
    hasProof: boolean;
};

export type DebtShowProps = {
    contact: DebtContact;
    balance: Rupiah;
    bills: DebtBill[];
    payments: DebtPayment[];
    moneyAccounts: MoneyAccountOption[];
    businessName: string;
};
