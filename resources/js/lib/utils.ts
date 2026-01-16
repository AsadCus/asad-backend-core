import { type ClassValue, clsx } from 'clsx';
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

export function parseDisplayDate(value?: string | null) {
    if (!value) {
        return undefined;
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return undefined;
    }

    return parsed;
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
