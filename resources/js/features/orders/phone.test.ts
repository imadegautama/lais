import { describe, expect, it } from 'vite-plus/test';
import { displayPhone, waLink, waNumber } from './phone';

describe('waNumber', () => {
    it('writes every way of typing a number as 62…, like Orders::phone()', () => {
        expect(waNumber('081234567890')).toBe('6281234567890');
        expect(waNumber('+62 812-3456-7890')).toBe('6281234567890');
        expect(waNumber('6281234567890')).toBe('6281234567890');
    });
});

describe('waLink', () => {
    it('opens a chat with the message typed in', () => {
        expect(waLink('0812 3456 7890', 'Pesanan A001 & siap')).toBe(
            'https://wa.me/6281234567890?text=Pesanan%20A001%20%26%20siap',
        );
    });
});

describe('displayPhone', () => {
    it('shows the local form in groups', () => {
        expect(displayPhone('6281234567890')).toBe('0812-3456-7890');
    });
});
