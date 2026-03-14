import { ProperInput } from '@/components/proper-input';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Table } from '@tanstack/react-table';
import {
    ChevronDownIcon,
    RefreshCcwIcon,
    Rows2Icon,
    Rows3Icon,
    Rows4Icon,
    SearchIcon,
    Settings2Icon,
} from 'lucide-react';

interface DataTableSettingsProps<TData> {
    table: Table<TData>;
    globalFilter: string;
    setGlobalFilter: (value: string) => void;
    density: string | undefined;
    setDensity: (value: string) => void;
    searchQuery: string;
    setSearchQuery: (value: string) => void;
    renderFilter?: (table: Table<TData>) => React.ReactNode;
    searchFilterMode?: 'inside' | 'outside';
    columnFilterMode?: 'inside' | 'outside';
}

export function DataTableSettings<TData>({
    table,
    globalFilter,
    setGlobalFilter,
    density,
    setDensity,
    searchQuery,
    setSearchQuery,
    renderFilter,
    searchFilterMode = 'inside',
    columnFilterMode = 'inside',
}: DataTableSettingsProps<TData>) {
    const showInsideSearch = searchFilterMode === 'inside';
    const showInsideColumnFilters =
        columnFilterMode === 'inside' && Boolean(renderFilter);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="flex items-center gap-2">
                    <Settings2Icon className="h-4 w-4" />
                    <span className="hidden sm:block">Settings</span>
                    <ChevronDownIcon className="h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                className="max-h-[530px] w-full max-w-full overflow-y-auto p-4"
                align="start"
            >
                <div
                    className={`grid gap-4 ${
                        showInsideColumnFilters
                            ? 'grid-cols-1 md:grid-cols-2'
                            : 'grid-cols-1'
                    }`}
                >
                    <div className="space-y-2">
                        {/* Search */}
                        {showInsideSearch && (
                            <div className="space-y-1">
                                <DropdownMenuLabel>Search</DropdownMenuLabel>
                                <div className="relative">
                                    <ProperInput
                                        value={globalFilter ?? ''}
                                        onCommit={setGlobalFilter}
                                        placeholder="Search..."
                                        className="w-full pl-8"
                                        inputProps={{
                                            onChange: (e) =>
                                                setGlobalFilter(e.target.value),
                                            onKeyDown: (e) =>
                                                e.stopPropagation(),
                                        }}
                                    />
                                    <SearchIcon className="absolute inset-y-0 left-2 my-auto h-4 w-4 text-muted-foreground" />
                                </div>
                            </div>
                        )}

                        {/* Columns */}
                        <div className="space-y-1">
                            <DropdownMenuLabel>Columns</DropdownMenuLabel>
                            <div className="relative">
                                <ProperInput
                                    value={searchQuery}
                                    onCommit={setSearchQuery}
                                    className="pl-8"
                                    placeholder="Search columns"
                                    inputProps={{
                                        onChange: (e) =>
                                            setSearchQuery(e.target.value),
                                        onKeyDown: (e) => e.stopPropagation(),
                                    }}
                                />
                                <SearchIcon className="absolute inset-y-0 left-2 my-auto h-4 w-4 text-muted-foreground" />
                            </div>

                            <div className="mt-2 max-h-[200px] overflow-y-auto rounded-md border p-1">
                                {table
                                    .getAllLeafColumns()
                                    .filter((column) => column.getCanHide())
                                    .map((column) => {
                                        if (
                                            searchQuery &&
                                            !column.id
                                                .toLowerCase()
                                                .includes(
                                                    searchQuery.toLowerCase(),
                                                )
                                        ) {
                                            return null;
                                        }

                                        return (
                                            <DropdownMenuCheckboxItem
                                                key={column.id}
                                                className="capitalize"
                                                checked={column.getIsVisible()}
                                                onCheckedChange={(value) =>
                                                    column.toggleVisibility(
                                                        !!value,
                                                    )
                                                }
                                                onSelect={(e) =>
                                                    e.preventDefault()
                                                }
                                            >
                                                {column.id.replace(/_/g, ' ')}
                                            </DropdownMenuCheckboxItem>
                                        );
                                    })}
                            </div>

                            <Button
                                variant="ghost"
                                size="sm"
                                className="mt-1 w-full justify-start"
                                onClick={() => {
                                    table.resetColumnVisibility();
                                    setSearchQuery('');
                                }}
                            >
                                <RefreshCcwIcon className="mr-2 h-4 w-4" />{' '}
                                Reset Columns
                            </Button>
                        </div>

                        {/* Density */}
                        <div className="space-y-1">
                            <DropdownMenuLabel>Density</DropdownMenuLabel>
                            <Select value={density} onValueChange={setDensity}>
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Density" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="compact">
                                        <div className="flex items-center gap-2">
                                            <Rows4Icon className="h-4 w-4" />
                                            Compact
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="standard">
                                        <div className="flex items-center gap-2">
                                            <Rows3Icon className="h-4 w-4" />
                                            Standard
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="flexible">
                                        <div className="flex items-center gap-2">
                                            <Rows2Icon className="h-4 w-4" />
                                            Flexible
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Filters */}
                    {showInsideColumnFilters && renderFilter && (
                        <div className="space-y-2 md:border-l md:pl-4">
                            {renderFilter(table)}
                        </div>
                    )}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
