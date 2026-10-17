// Domain vocabulary shared by pages and components. Mirrors PRD §4.2.
// Money is integer rupiah everywhere.

export type Rupiah = number;

export type TransactionType =
    | 'penjualan'
    | 'pembelian'
    | 'biaya'
    | 'utang_piutang'
    | 'modal';

export type PaymentMethod = 'tunai' | 'non_tunai' | 'kredit' | 'pribadi';

export type TransactionSource = 'web' | 'wa' | 'toko' | 'ocr';

export type MoneyDirection = 'in' | 'out' | 'none';

export type ItemKind = 'produk' | 'bahan' | 'komposisi';

export type OrderStatus =
    | 'menunggu'
    | 'diproses'
    | 'siap'
    | 'selesai'
    | 'ditolak';

export type PaymentStatus = 'belum' | 'dp' | 'lunas';

export type PeriodKey = 'hari_ini' | 'minggu_ini' | 'bulan_ini';

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
    id: string;
    occurredAt: string;
    type: TransactionType;
    description: string;
    method: PaymentMethod;
    source: TransactionSource;
    amount: Rupiah;
    direction: MoneyDirection;
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
    name: string;
    unit: string;
    averageCost: number;
};

export type StockMovementReason = 'stok_awal' | 'koreksi';

export type StockMovementRow = {
    id: number;
    occurredAt: string;
    reason: StockMovementReason;
    qty: number;
    value: Rupiah;
};

export type ItemsIndexProps = {
    items: ItemRow[];
    summary: {
        inventoryValue: Rupiah;
        lowStockCount: number;
        negativeStockCount: number;
    };
};

export type ItemFormProps = {
    item: (ItemRow & { recipe: RecipeLineInput[] }) | null;
    initialKind: ItemKind;
    materials: RecipeMaterial[];
    movements: StockMovementRow[];
};
