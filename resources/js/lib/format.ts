const numberId = new Intl.NumberFormat('id-ID');
const TIME_ZONE = 'Asia/Jakarta';
const MINUS = '−';

/** Rp15.000 / −Rp15.000. Money is always integer rupiah. */
export function formatRupiah(value: number): string {
    const sign = value < 0 ? MINUS : '';

    return `${sign}Rp${numberId.format(Math.abs(Math.round(value)))}`;
}

/** Rp850 / Rp15,5 rb / Rp1,2 jt / Rp2,1 M. For tight spaces such as chart axes. */
export function formatRupiahCompact(value: number): string {
    const sign = value < 0 ? MINUS : '';
    const abs = Math.abs(value);
    const units: Array<[number, string]> = [
        [1_000_000_000, ' M'],
        [1_000_000, ' jt'],
        [1_000, ' rb'],
    ];

    for (const [size, suffix] of units) {
        if (abs >= size) {
            const short = new Intl.NumberFormat('id-ID', {
                maximumFractionDigits: 1,
            }).format(abs / size);

            return `${sign}Rp${short}${suffix}`;
        }
    }

    return `${sign}Rp${numberId.format(abs)}`;
}

/** 15,8% */
export function formatPercent(value: number, fractionDigits = 1): string {
    return `${new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: fractionDigits,
    }).format(value)}%`;
}

type DateStyle = 'short' | 'long' | 'day' | 'time' | 'datetime';

const dateFormats: Record<DateStyle, Intl.DateTimeFormatOptions> = {
    short: { day: 'numeric', month: 'short', year: 'numeric' },
    long: { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' },
    day: { day: 'numeric', month: 'short' },
    time: { hour: '2-digit', minute: '2-digit' },
    datetime: {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    },
};

/** 14 Okt 2026, in WIB regardless of the viewer's device clock. */
export function formatDate(
    value: string | Date,
    style: DateStyle = 'short',
): string {
    const date = typeof value === 'string' ? new Date(value) : value;

    return new Intl.DateTimeFormat('id-ID', {
        ...dateFormats[style],
        timeZone: TIME_ZONE,
    }).format(date);
}

/** 0,6 kg / 3 butir */
export function formatQty(value: number, unit: string): string {
    const qty = new Intl.NumberFormat('id-ID', {
        maximumFractionDigits: 3,
    }).format(value);

    return `${qty} ${unit}`;
}

/**
 * Reads how owners type money: "15rb", "15 ribu", "15k", "1,5jt",
 * "1.5 juta", "Rp15.000", "15000". Returns integer rupiah, or null
 * when the text is not a number.
 */
export function parseMoney(input: string): number | null {
    const text = input.toLowerCase().replace(/rp\.?/g, '').replace(/\s+/g, '');
    const match = text.match(/^(\d+(?:[.,]\d+)*)(rb|ribu|k|jt|juta)?$/);

    if (!match) {
        return null;
    }

    const [, digits, suffix] = match;
    const multiplier =
        suffix === 'jt' || suffix === 'juta' ? 1_000_000 : suffix ? 1_000 : 1;

    // With a suffix, "1,5" and "1.5" both mean one and a half.
    // Without one, Indonesian notation applies: "." groups thousands, "," is decimal.
    const normalized = suffix
        ? digits.replace(',', '.')
        : digits.replace(/\./g, '').replace(',', '.');
    const amount = Number.parseFloat(normalized);

    return Number.isFinite(amount) ? Math.round(amount * multiplier) : null;
}
