/**
 * The VAPID public key as the Push API wants it: base64url text to bytes.
 */
export function vapidKeyBytes(key: string): Uint8Array<ArrayBuffer> {
    const padded = key + '='.repeat((4 - (key.length % 4)) % 4);
    const binary = atob(padded.replace(/-/g, '+').replace(/_/g, '/'));

    return Uint8Array.from(binary, (char) => char.charCodeAt(0));
}
