const numberId = new Intl.NumberFormat('id-ID');
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

    for (const [index, [size, suffix]] of units.entries()) {
        if (abs >= size) {
            // 999.950 rounds to "1.000 rb"; step up and show "1 jt" instead.
            const [unitSize, unitSuffix] =
                index > 0 && Math.round((abs / size) * 10) >= 10_000
                    ? units[index - 1]
                    : [size, suffix];
            const short = new Intl.NumberFormat('id-ID', {
                maximumFractionDigits: 1,
            }).format(abs / unitSize);

            return `${sign}Rp${short}${unitSuffix}`;
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

/**
 * The app's time zone (APP_TIMEZONE), so dates read the same on every
 * device. app.tsx sets it from the shared `timeZone` prop before each page
 * renders; until then, and in tests, it is WIB.
 */
let timeZone = { name: 'Asia/Jakarta', label: 'WIB' };

export function setTimeZone(zone: { name: string; label: string }): void {
    timeZone = zone;
}

/** "Asia/Makassar": for Intl calls outside formatDate. */
export function appTimeZone(): string {
    return timeZone.name;
}

/** "WIB", "WITA", or "WIT", written after a time. */
export function zoneLabel(): string {
    return timeZone.label;
}

/** 14 Okt 2026, in the app time zone regardless of the viewer's device clock. */
export function formatDate(
    value: string | Date,
    style: DateStyle = 'short',
): string {
    const date = typeof value === 'string' ? new Date(value) : value;

    return new Intl.DateTimeFormat('id-ID', {
        ...dateFormats[style],
        timeZone: timeZone.name,
    }).format(date);
}

/**
 * "Beras" / "Beras dan Telur" / "Beras, Telur, dan 3 lainnya", from the first
 * names of a longer list of `count`.
 */
export function formatNameList(names: string[], count: number): string {
    return count <= 2
        ? names.join(' dan ')
        : `${names.join(', ')}, dan ${count - names.length} lainnya`;
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

    // With a suffix, "1,5" and "1.5" both mean one and a half, but a "." before
    // exactly three digits still groups thousands ("1.500rb").
    // Without one, Indonesian notation applies: "." groups thousands, "," is decimal.
    const normalized = suffix
        ? digits.replace(/\.(?=\d{3}(?!\d))/g, '').replace(',', '.')
        : digits.replace(/\./g, '').replace(',', '.');
    const amount = Number.parseFloat(normalized);

    return Number.isFinite(amount) ? Math.round(amount * multiplier) : null;
}
