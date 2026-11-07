import { describe, expect, it } from 'vite-plus/test';
import { ageLabel, ageTone, reminderMessage } from './vocabulary';

describe('reminderMessage', () => {
    it('names the amount, the date, and the shop', () => {
        const text = reminderMessage(
            'Bu Sari',
            25000,
            '2026-11-01T03:00:00+00:00',
            'Warung Ani',
        );

        expect(text).toContain('Halo Bu Sari');
        expect(text).toContain('tagihan Rp25.000 sejak');
        expect(text).toContain('1 Nov 2026');
        expect(text).toContain('Kami dari Warung Ani');
    });
});

describe('ageTone', () => {
    it('turns yellow at a week and red after a month', () => {
        expect([0, 6, 7, 30, 31].map(ageTone)).toEqual([
            'neutral',
            'neutral',
            'warning',
            'warning',
            'danger',
        ]);
    });
});

describe('ageLabel', () => {
    it('says today for a bill from today', () => {
        expect([0, 1, 18].map(ageLabel)).toEqual([
            'Hari ini',
            '1 hari',
            '18 hari',
        ]);
    });
});
