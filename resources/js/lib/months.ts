export interface MonthOption {
    value: string;
    label: string;
}

export const MONTHS: MonthOption[] = [
    { value: '01', label: 'January' },
    { value: '02', label: 'February' },
    { value: '03', label: 'March' },
    { value: '04', label: 'April' },
    { value: '05', label: 'May' },
    { value: '06', label: 'June' },
    { value: '07', label: 'July' },
    { value: '08', label: 'August' },
    { value: '09', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
];

export function getYearOptions(currentYear: number): string[] {
    return [
        String(currentYear - 2),
        String(currentYear - 1),
        String(currentYear),
        String(currentYear + 1),
    ];
}

export function splitYearMonth(ym: string): { year: string; month: string } {
    return {
        year: ym.slice(0, 4),
        month: ym.slice(5, 7),
    };
}

export function joinYearMonth(year: string, month: string): string {
    return `${year}-${month}`;
}
