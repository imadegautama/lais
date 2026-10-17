/** "0,15" and "0.15" are the same amount. NaN for anything else. */
export function parseDecimal(text: string): number {
    return Number(text.trim().replace(',', '.'));
}

/** A count typed by the owner, or null when it is empty, not a number, or negative. */
export function parseCount(text: string): number | null {
    const value = parseDecimal(text);

    return text.trim() !== '' && Number.isFinite(value) && value >= 0
        ? value
        : null;
}

/** A form field value back to text the way owners type it ("0,15"). */
export function decimalInput(value: number | null | undefined): string {
    return value === null || value === undefined
        ? ''
        : String(value).replace('.', ',');
}

/** Rounds like PHP's round(): halves go away from zero, so -2.5 becomes -3. */
export function roundHalfAway(value: number, decimals = 0): number {
    const factor = 10 ** decimals;

    return (Math.sign(value) * Math.round(Math.abs(value) * factor)) / factor;
}

/** Stock quantities keep three decimals, like the database. */
export function roundQty(value: number): number {
    return roundHalfAway(value, 3);
}
