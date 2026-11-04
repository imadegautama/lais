import { describe, expect, it } from 'vite-plus/test';
import type { PublicOrder } from '@/types/domain';
import { stepIndex, statusMessage } from './vocabulary';

const order = (overrides: Partial<PublicOrder> = {}): PublicOrder => ({
    code: 'A012',
    status: 'diproses',
    customerName: 'Bu Sari',
    fulfillment: 'ambil',
    address: null,
    wantedAt: null,
    note: null,
    total: 45000,
    paid: 20000,
    paymentStatus: 'dp',
    createdAt: '2026-11-18T07:00:00+00:00',
    acceptedAt: null,
    readyAt: null,
    completedAt: null,
    rejectedAt: null,
    rejectReason: null,
    token: 'x'.repeat(32),
    lines: [],
    ...overrides,
});

const url = 'https://lais.test/p/abc';

describe('statusMessage', () => {
    it('says what is left to pay and links the nota', () => {
        const text = statusMessage(order(), 'Warung Ani', url);

        expect(text).toContain('A012 di Warung Ani sedang kami siapkan');
        expect(text).toContain('Sisa yang perlu dibayar Rp25.000.');
        expect(text.endsWith(url)).toBe(true);
    });

    it('tells pick-up from delivery when ready', () => {
        expect(
            statusMessage(order({ status: 'siap' }), 'Warung Ani', url),
        ).toContain('siap diambil');
        expect(
            statusMessage(
                order({ status: 'siap', fulfillment: 'antar' }),
                'Warung Ani',
                url,
            ),
        ).toContain('sedang diantar');
    });

    it('thanks a paid customer and explains a refusal with the refund', () => {
        expect(
            statusMessage(
                order({ status: 'selesai', paid: 45000 }),
                'Warung Ani',
                url,
            ),
        ).toContain('sudah lunas');
        expect(
            statusMessage(
                order({ status: 'ditolak', rejectReason: 'Bahan habis' }),
                'Warung Ani',
                url,
            ),
        ).toContain(
            'tidak bisa kami proses (Bahan habis). Uang muka Rp20.000 kami kembalikan.',
        );
    });
});

describe('stepIndex', () => {
    it('places each status on the nota steps; Ditolak has none', () => {
        expect(
            ['menunggu', 'diproses', 'siap', 'selesai', 'ditolak'].map(
                (status) => stepIndex(status as PublicOrder['status']),
            ),
        ).toEqual([0, 1, 2, 3, -1]);
    });
});
