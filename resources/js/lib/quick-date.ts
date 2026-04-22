export type QuickDateKey =
    | '3days'
    | '1week'
    | '1month'
    | '3months'
    | 'endmonth'
    | 'endnextmonth'
    | 'today'
    | 'yesterday'
    | 'last7days'
    | 'last30days'
    | 'thismonth'
    | 'last3months'
    | 'last6months'
    | 'last1year';

export interface QuickDateOption {
    label: string;
    value: QuickDateKey;
}

export const QUICK_DATE_SINGLE_OPTIONS: QuickDateOption[] = [
    { label: 'Today', value: 'today' },
];

export const QUICK_DATE_RANGE_OPTIONS: QuickDateOption[] = [
    { label: 'Last 7 days', value: 'last7days' },
    { label: 'Last 30 days', value: 'last30days' },
    { label: 'Last 3 months', value: 'last3months' },
    { label: 'Last 6 months', value: 'last6months' },
    { label: 'Last 1 year', value: 'last1year' },
];

export function resolveQuickDate(key: QuickDateKey): Date {
    const date = new Date();

    switch (key) {
        case '3days':
            date.setDate(date.getDate() + 3);
            break;
        case '1week':
            date.setDate(date.getDate() + 7);
            break;
        case '1month':
            date.setMonth(date.getMonth() + 1);
            break;
        case '3months':
            date.setMonth(date.getMonth() + 3);
            break;
        case 'endmonth':
            date.setMonth(date.getMonth() + 1, 0);
            break;
        case 'endnextmonth':
            date.setMonth(date.getMonth() + 2, 0);
            break;
        case 'yesterday':
            date.setDate(date.getDate() - 1);
            break;
        case 'last7days':
            date.setDate(date.getDate() - 7);
            break;
        case 'last30days':
            date.setDate(date.getDate() - 30);
            break;
        case 'thismonth':
            date.setDate(1);
            break;
        case 'last3months':
            date.setMonth(date.getMonth() - 3);
            break;
        case 'last6months':
            date.setMonth(date.getMonth() - 6);
            break;
        case 'last1year':
            date.setFullYear(date.getFullYear() - 1);
            break;
        case 'today':
        default:
            break;
    }

    return date;
}

export function resolveQuickDateRange(key: QuickDateKey): {
    from: Date;
    to?: Date;
} {
    const from = resolveQuickDate(key);
    const to =
        key.startsWith('last') || key === 'thismonth' ? new Date() : undefined;

    return { from, to };
}
