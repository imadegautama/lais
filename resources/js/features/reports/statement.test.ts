import { describe, expect, it } from 'vite-plus/test';
import { changeLabel } from './statement';

describe('change label', () => {
    it('reads as a signed percentage', () => {
        expect(changeLabel(112, 100)).toBe('+12%');
        expect(changeLabel(1, 100)).toBe('−99%');
    });

    it('measures a cost by its size, so a rising cost never reads as a fall', () => {
        expect(changeLabel(-300, -100)).toBe('+200%');
        expect(changeLabel(-50, -100)).toBe('−50%');
    });

    it('gives no percentage when a profit turns into a loss', () => {
        expect(changeLabel(-100, 100)).toBe('berbalik');
        expect(changeLabel(4847000, -25900)).toBe('berbalik');
        expect(changeLabel(0, -100)).toBe('−100%');
    });

    it('keeps a decimal only for small moves', () => {
        expect(changeLabel(1042, 1000)).toBe('+4,2%');
        expect(changeLabel(1001, 1000)).toBe('0%');
    });

    it('never divides by an empty period', () => {
        expect(changeLabel(5000, 0)).toBe('baru');
        expect(changeLabel(0, 0)).toBe('');
    });
});
