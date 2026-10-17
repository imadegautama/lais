import { describe, expect, it } from 'vite-plus/test';
import {
    decimalInput,
    parseCount,
    parseDecimal,
    roundHalfAway,
    roundQty,
} from './number';

describe('parseDecimal', () => {
    it('reads a comma and a dot the same way', () => {
        expect(parseDecimal('0,15')).toBe(0.15);
        expect(parseDecimal(' 0.15 ')).toBe(0.15);
        expect(parseDecimal('abc')).toBeNaN();
    });
});

describe('parseCount', () => {
    it('accepts zero and decimals, refuses empty and negative', () => {
        expect(parseCount('8,5')).toBe(8.5);
        expect(parseCount('0')).toBe(0);
        expect(parseCount('')).toBeNull();
        expect(parseCount('-1')).toBeNull();
        expect(parseCount('banyak')).toBeNull();
    });
});

describe('decimalInput', () => {
    it('writes the decimal comma back', () => {
        expect(decimalInput(0.15)).toBe('0,15');
        expect(decimalInput(null)).toBe('');
    });
});

describe('roundHalfAway', () => {
    it('rounds halves away from zero like PHP', () => {
        expect(roundHalfAway(2.5)).toBe(3);
        expect(roundHalfAway(-2.5)).toBe(-3);
        expect(roundHalfAway(-2.4)).toBe(-2);
        expect(roundQty(0.1 + 0.2)).toBe(0.3);
    });
});
