/** "6281234567890" as owners write it: "081234567890". */
export function localPhone(phone: string | null): string | null {
    return phone === null ? null : phone.replace(/^62/, '0');
}
