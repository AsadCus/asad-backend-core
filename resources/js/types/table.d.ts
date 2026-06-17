import '@tanstack/table-core';
import type { FilterFn, RowData, SortingFn } from '@tanstack/table-core';

declare module '@tanstack/table-core' {
    interface FilterFns {
        includesValue: FilterFn<unknown>;
        dateRangeFilter: FilterFn<unknown>;
    }

    interface SortingFns {
        displayDate: SortingFn<unknown>;
    }

    interface ColumnFiltersOptions<TData extends RowData> {
        filterFns?: Record<keyof FilterFns, FilterFn<TData>>;
    }

    interface SortingOptions<TData extends RowData> {
        sortingFns?: Record<keyof SortingFns, SortingFn<TData>>;
    }

    interface ColumnMeta<_TData extends RowData, _TValue> {
        className?: string;
        exportable?: boolean;
        _typeHint?: (_row: _TData, _value: _TValue) => void;
    }
}
