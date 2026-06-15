import { describe, expect, it } from 'vitest';
import { compareFormattedDate, parseDisplayDate } from './utils';

/** Local-midnight ISO date (YYYY-MM-DD) of a parsed value, for stable assertions. */
function ymd(date: Date | undefined): string | undefined {
    if (!date) return undefined;
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

describe('parseDisplayDate', () => {
    it('parses localized month names for any locale', () => {
        expect(ymd(parseDisplayDate('01 April 2027', 'en'))).toBe('2027-04-01');
        expect(ymd(parseDisplayDate('15 Agustus 2026', 'id'))).toBe(
            '2026-08-15',
        );
        expect(ymd(parseDisplayDate('01 avril 2027', 'fr'))).toBe('2027-04-01');
    });

    it('parses localized datetime variants', () => {
        const en = parseDisplayDate('09 April 2027, 14:30', 'en');
        expect(ymd(en)).toBe('2027-04-09');
        expect(en?.getHours()).toBe(14);

        expect(ymd(parseDisplayDate('09 Agustus 2026 09:05', 'id'))).toBe(
            '2026-08-09',
        );
    });

    it('falls back to native parsing for ISO values', () => {
        expect(ymd(parseDisplayDate('2026-08-03'))).toBe('2026-08-03');
    });

    it('returns undefined for empty/null', () => {
        expect(parseDisplayDate('', 'id')).toBeUndefined();
        expect(parseDisplayDate(null)).toBeUndefined();
    });
});

describe('compareFormattedDate', () => {
    it('orders display-formatted dates chronologically, not as text', () => {
        const dates = ['01 April 2027', '03 August 2026', '31 August 2026'];
        const sorted = [...dates].sort(compareFormattedDate);

        expect(sorted).toEqual([
            '03 August 2026',
            '31 August 2026',
            '01 April 2027',
        ]);
    });

    it('groups empty/unparseable values ahead of real dates (ascending)', () => {
        const sorted = ['01 April 2027', '', null as unknown as string].sort(
            compareFormattedDate,
        );

        expect(sorted[sorted.length - 1]).toBe('01 April 2027');
    });
});
