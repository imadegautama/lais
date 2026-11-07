import { describe, expect, it } from 'vitest';
import { examples } from './examples';

const sum = (values: number[]) => values.reduce((a, b) => a + b, 0);

describe('landing demo', () => {
    it('balances every journal', () => {
        for (const { journal } of examples) {
            expect(sum(journal.map((line) => line.debit))).toBe(
                sum(journal.map((line) => line.credit)),
            );
        }
    });

    it('answers the question from the three records before it', () => {
        const records = examples.filter((example) => example.draft !== null);
        const question = examples.find((example) => example.id === 'tanya');

        expect(records).toHaveLength(3);
        expect(sum(records.map((record) => record.cash.amount))).toBe(
            question?.cash.amount,
        );
        expect(sum(records.map((record) => record.profit.amount))).toBe(
            question?.profit.amount,
        );
    });

    it('moves cash by the total of each draft', () => {
        for (const { cash, draft } of examples) {
            if (draft !== null) {
                expect(Math.abs(cash.amount)).toBe(draft.total);
            }
        }
    });
});
