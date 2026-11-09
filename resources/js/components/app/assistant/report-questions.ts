/**
 * "Tanya AI" on report pages: which report is open (sent with each question,
 * so the answer uses its own period) and what the owner may want to ask.
 * The pages are FormAssistant::REPORTS.
 */
export type ReportContext = {
    page: string;
    periode: string | null;
    dari: string | null;
    sampai: string | null;
};

const QUESTIONS: Record<string, string[]> = {
    'reports/index': [
        'Jelaskan kondisi usahaku',
        'Kenapa labanya naik atau turun?',
        'Biaya terbesar apa?',
    ],
    'reports/profit-and-loss': [
        'Jelaskan laporan ini',
        'Kenapa labanya naik atau turun?',
        'Biaya terbesar apa?',
    ],
    'reports/balance-sheet': [
        'Jelaskan laporan ini',
        'Apakah usahaku sehat?',
        'Utangku aman atau tidak?',
    ],
    'reports/equity': [
        'Jelaskan laporan ini',
        'Kenapa modalku berubah?',
        'Ambil pribadiku kebanyakan tidak?',
    ],
    'reports/cash-book': [
        'Jelaskan laporan ini',
        'Kenapa kas naik atau turun?',
        'Kenapa laba naik tapi kas turun?',
    ],
    'reports/products': [
        'Produk mana paling untung?',
        'Produk mana yang marginnya tipis?',
        'Stok mana yang diam?',
    ],
};

const GENERAL = ['Jelaskan laporan ini', 'Apa arti angka-angka di sini?'];

export function isReport(component: string): boolean {
    return component.startsWith('reports/');
}

export function reportQuestions(component: string): string[] {
    return QUESTIONS[component] ?? GENERAL;
}

/** The open report and its period, as the page's own address carries it. */
export function reportContext(
    component: string,
    url: string,
): ReportContext | null {
    if (!isReport(component)) {
        return null;
    }

    const query = new URL(url, 'http://lais.local').searchParams;

    return {
        page: component,
        periode: query.get('periode'),
        dari: query.get('dari'),
        sampai: query.get('sampai'),
    };
}
