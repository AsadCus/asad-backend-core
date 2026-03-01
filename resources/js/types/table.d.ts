import '@tanstack/react-table';
import type { FilterFn, RowData } from '@tanstack/react-table';

declare module '@tanstack/react-table' {
    interface FilterFns {
        includesValue: FilterFn<unknown>;
        dateRangeFilter: FilterFn<unknown>;
    }

    interface ColumnFiltersOptions<_TData extends RowData> {
        filterFns?: Record<keyof FilterFns, FilterFn<_TData>>;
    }

    interface ColumnMeta<_TData extends RowData, _TValue> {
        className?: string;
        exportable?: boolean;
        _typeHint?: (_row: _TData, _value: _TValue) => void;
    }
}
