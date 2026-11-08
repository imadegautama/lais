import { describe, expect, it } from 'vite-plus/test';
import { localPhone } from './phone';

describe('local phone', () => {
    it('writes a stored number the way owners do', () => {
        expect(localPhone('6281234567890')).toBe('081234567890');
        expect(localPhone(null)).toBeNull();
    });
});
