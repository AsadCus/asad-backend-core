import { type ClassValue, clsx } from 'clsx';
import { DateTime } from 'luxon';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export const ages = [
    { value: '18-25', label: '18-25' },
    { value: '26-35', label: '26-35' },
    { value: '36-45', label: '36-45' },
    { value: '45+', label: '45+' },
];

export const experiences = [
    { value: '0-1', label: '0-1 year' },
    { value: '2-3', label: '2-3 year' },
    { value: '4-5', label: '4-5 year' },
    { value: '5+', label: '5+ year' },
];

export const capitalize = (str?: string | null) =>
    str ? str.charAt(0).toUpperCase() + str.slice(1) : '-';

export const calculateAge = (dob: string) => {
    const birthDate = new Date(dob);
    const diff = Date.now() - birthDate.getTime();
    const ageDt = new Date(diff);
    return Math.abs(ageDt.getUTCFullYear() - 1970);
};

// date
export function formatDateForDisplay(date?: Date | string | null) {
    if (!date) {
        return '';
    }

    // Convert string to Date if needed
    const dateObj = typeof date === 'string' ? new Date(date) : date;

    if (Number.isNaN(dateObj.getTime())) {
        return '';
    }

    return dateObj.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Luxon parse formats mirroring the localized strings the backend produces via
 * Carbon `translatedFormat(...)`. KEEP IN SYNC: add a row here whenever a new
 * month-name-bearing `translatedFormat('…')` pattern is introduced in app/.
 * Full-month formats precede short-month so "April" never mis-parses as `LLL`.
 */
const LOCALIZED_DATE_FORMATS = [
    'd LLLL yyyy', // d F Y / j F Y
    'd LLLL yyyy H:mm', // d F Y H:i
    'd LLLL yyyy, H:mm', // d F Y, H:i
    'd LLL yyyy', // d M Y
    'd LLL yyyy, H:mm', // d M Y, H:i
    'd LLL yyyy, h:mm a', // d M Y, h:i A
] as const;

/** Active app locale, mirrored onto <html lang> server-side (app.blade.php). */
function activeLocale(): string {
    if (typeof document !== 'undefined' && document.documentElement.lang) {
        return document.documentElement.lang;
    }
    return 'en';
}

export function parseDisplayDate(
    value?: string | null,
    locale: string = activeLocale(),
): Date | undefined {
    if (!value) {
        return undefined;
    }

    for (const format of LOCALIZED_DATE_FORMATS) {
        const parsed = DateTime.fromFormat(value, format, { locale });
        if (parsed.isValid) {
            return parsed.toJSDate();
        }
    }

    // ponytail: native fallback preserves all prior behavior — ISO date-input
    // values ("2026-08-03"), datetimes, English month-first strings, etc.
    const fallback = new Date(value);
    return Number.isNaN(fallback.getTime()) ? undefined : fallback;
}

export function compareNaturalText(left: unknown, right: unknown): number {
    return String(left ?? '').localeCompare(String(right ?? ''), undefined, {
        numeric: true,
        sensitivity: 'base',
    });
}

export function compareFormattedDate(left: unknown, right: unknown): number {
    const leftDate = parseDisplayDate(String(left ?? ''));
    const rightDate = parseDisplayDate(String(right ?? ''));

    if (leftDate && rightDate) {
        return leftDate.getTime() - rightDate.getTime();
    }

    if (leftDate) {
        return 1;
    }

    if (rightDate) {
        return -1;
    }

    return compareNaturalText(left, right);
}

// for input date
export function isBeforeToday(date: Date) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
}

// format
export function formatCurrency(
    value: string | number | null | undefined,
    currency = '$',
): string {
    const amount = Number(value ?? 0);
    const sign = amount < 0 ? '-' : '';
    return `${sign}${currency}${Math.abs(amount).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

// file download
export const triggerDownload = (url: string) => {
    const link = document.createElement('a');
    link.href = url;
    link.download = '';
    document.body.appendChild(link);
    link.click();
    link.remove();
};
