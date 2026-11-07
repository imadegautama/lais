import { describe, expect, it } from 'vite-plus/test';
import type { PublicOrder } from '@/types/domain';
import { stepIndex } from './vocabulary';

describe('stepIndex', () => {
    it('places each status on the nota steps; Ditolak has none', () => {
        expect(
            ['menunggu', 'diproses', 'siap', 'selesai', 'ditolak'].map(
                (status) => stepIndex(status as PublicOrder['status']),
            ),
        ).toEqual([0, 1, 2, 3, -1]);
    });
});
