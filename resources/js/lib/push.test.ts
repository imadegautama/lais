import { describe, expect, it } from 'vite-plus/test';
import { vapidKeyBytes } from './push';

describe('vapidKeyBytes', () => {
    it('reads base64url without padding', () => {
        // "Lais?" is TGFpcz8 in base64url: the ? lands on a character that differs from base64.
        expect(Array.from(vapidKeyBytes('TGFpcz8'))).toEqual([
            76, 97, 105, 115, 63,
        ]);
        expect(Array.from(vapidKeyBytes('-_8'))).toEqual([251, 255]);
    });
});
