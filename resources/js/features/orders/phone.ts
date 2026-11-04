/**
 * "0812…", "+62812…", "62 812-…" → "62812…", the form wa.me expects.
 * Same rule as App\Services\Orders::phone().
 */
export function waNumber(phone: string): string {
    const digits = phone.replace(/\D/g, '');

    return digits.startsWith('0') ? `62${digits.slice(1)}` : digits;
}

/** A chat with `phone` that opens with `text` typed in, ready to send. */
export function waLink(phone: string, text: string): string {
    return `https://wa.me/${waNumber(phone)}?text=${encodeURIComponent(text)}`;
}

/** 6281234567890 → 0812-3456-7890, how people read their own number. */
export function displayPhone(phone: string): string {
    const local = waNumber(phone).replace(/^62/, '0');

    return local.replace(/^(\d{4})(\d{4})(\d+)$/, '$1-$2-$3');
}
