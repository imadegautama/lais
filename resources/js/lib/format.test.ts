import { afterEach, describe, expect, it } from 'vite-plus/test';
import {
    appTimeZone,
    formatDate,
    formatNameList,
    formatRupiahCompact,
    parseMoney,
    setTimeZone,
    zoneLabel,
} from './format';

describe('parseMoney', () => {
    it.each([
        ['15000', 15_000],
        ['15.000', 15_000],
        ['Rp15.000', 15_000],
        ['15rb', 15_000],
        ['15 ribu', 15_000],
        ['15k', 15_000],
        ['1,5jt', 1_500_000],
        ['1.5 juta', 1_500_000],
        ['1.500rb', 1_500_000],
        ['2,5rb', 2_500],
        ['12.500', 12_500],
    ])('reads %s as %d', (input, expected) => {
        expect(parseMoney(input)).toBe(expected);
    });

    it.each(['', 'abc', '15rbu', '-5'])('rejects %j', (input) => {
        expect(parseMoney(input)).toBeNull();
    });
});

describe('formatRupiahCompact', () => {
    it.each([
        [850, 'Rp850'],
        [15_500, 'Rp15,5 rb'],
        [999_950, 'Rp1 jt'],
        [1_250_000, 'Rp1,3 jt'],
        [-2_100_000_000, '−Rp2,1 M'],
    ])('formats %d as %s', (value, expected) => {
        expect(formatRupiahCompact(value)).toBe(expected);
    });
});

describe('formatNameList', () => {
    it('names one or two, then counts the rest', () => {
        expect(formatNameList(['Beras'], 1)).toBe('Beras');
        expect(formatNameList(['Beras', 'Telur'], 2)).toBe('Beras dan Telur');
        expect(formatNameList(['Beras', 'Telur'], 5)).toBe(
            'Beras, Telur, dan 3 lainnya',
        );
    });
});

describe('time zone', () => {
    afterEach(() => setTimeZone({ name: 'Asia/Jakarta', label: 'WIB' }));

    // 13.00 UTC is 20.00 WIB and 21.00 WITA, whatever the test machine's clock says.
    const moment = '2026-11-08T13:00:00Z';

    it('formats in WIB until the server says otherwise', () => {
        expect(formatDate(moment, 'time')).toBe('20.00');
        expect(zoneLabel()).toBe('WIB');
    });

    it('follows the zone the server sends', () => {
        setTimeZone({ name: 'Asia/Makassar', label: 'WITA' });

        expect(formatDate(moment, 'time')).toBe('21.00');
        expect(appTimeZone()).toBe('Asia/Makassar');
        expect(zoneLabel()).toBe('WITA');
    });
});
