import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    CellContext,
    Column,
    flexRender,
    Row,
    Table,
} from '@tanstack/react-table';
import jsPDF from 'jspdf';
import autoTable from 'jspdf-autotable';
import {
    DownloadIcon,
    FileArchiveIcon,
    FileSpreadsheetIcon,
    FileTextIcon,
} from 'lucide-react';
import Papa from 'papaparse';
import * as XLSX from 'xlsx';

interface DataTableExportProps<TData> {
    table: Table<TData>;
    filename?: string;
}

export function DataTableExport<TData>({
    table,
    filename = 'data',
}: DataTableExportProps<TData>) {
    const exportableColumns = table
        .getAllLeafColumns()
        .filter(
            (col: Column<TData>) =>
                col.columnDef.meta?.exportable !== false && col.id !== 'select',
        );

    const formatCellValue = (column: Column<TData>, row: Row<TData>) => {
        if (!column.columnDef.cell) {
            return (row.original as Record<string, unknown>)[column.id] ?? '';
        }

        try {
            const cell = row
                .getAllCells()
                .find((c) => c.column.id === column.id);
            if (!cell) {
                return (
                    (row.original as Record<string, unknown>)[column.id] ?? '-'
                );
            }

            const context: CellContext<TData, unknown> = {
                row,
                column,
                cell,
                table,
                getValue: () => row.getValue(column.id),
                renderValue: () => row.getValue(column.id),
            };

            const rendered = flexRender(column.columnDef.cell, context);

            if (typeof rendered === 'string' || typeof rendered === 'number') {
                return rendered;
            }

            const rawValue = (row.original as Record<string, unknown>)[
                column.id
            ];

            // Format arrays
            if (Array.isArray(rawValue)) {
                return rawValue.join(', ');
            }

            // Format dates
            if (column.id.includes('date') && rawValue) {
                try {
                    const date = new Date(String(rawValue));
                    if (!isNaN(date.getTime())) {
                        return date.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                        });
                    }
                } catch (err) {
                    console.error('Failed to parse date:', rawValue, err);
                    return rawValue;
                }
            }

            // Format numbers
            if (column.id === 'height' || column.id === 'weight') {
                return rawValue ? Number(rawValue) : '-';
            }

            if (column.id === 'remaining_loan') {
                return rawValue ? `${Number(rawValue)} months` : '-';
            }

            if (column.id === 'cost_of_maid') {
                return rawValue ? `$${Number(rawValue)}` : '-';
            }

            if (column.id === 'singapore_experience') {
                return rawValue ? 'Yes' : 'No';
            }

            return rawValue ?? '-';
        } catch (err) {
            console.error('Cell render fail:', err);
            return (row.original as Record<string, unknown>)[column.id] ?? '-';
        }
    };

    const getDataToExport = () => {
        const selectedRows = table.getSelectedRowModel().rows as Row<TData>[];
        const rows =
            selectedRows.length > 0
                ? selectedRows
                : (table.getFilteredRowModel().rows as Row<TData>[]);

        const columnsToExport = exportableColumns.filter((col) =>
            col.getIsVisible(),
        );

        const headers: Record<string, string> = {};
        columnsToExport.forEach((col) => {
            if (col.id) {
                headers[col.id] =
                    typeof col.columnDef.header === 'string'
                        ? col.columnDef.header
                        : col.id;
            }
        });

        const formattedRows = rows.map((row) => {
            const filtered: Record<string, unknown> = {};
            columnsToExport.forEach((col) => {
                if (col.id) {
                    filtered[headers[col.id]] = formatCellValue(col, row);
                }
            });
            return filtered;
        });

        const metadata = {
            exportDate: new Date().toLocaleString('en-US'),
            totalRecords: rows.length,
            selectedRecords: selectedRows.length,
            filters:
                table.getState().columnFilters.length > 0 ? 'Applied' : 'None',
        };

        return { headers, rows: formattedRows, metadata };
    };

    const generateFilename = (ext: string) => {
        const timestamp = new Date()
            .toISOString()
            .replace(/[:.]/g, '-')
            .split('T');
        return `${filename}-export-${timestamp[0]}-${timestamp[1].split('Z')[0]}.${ext}`;
    };

    const exportToCSV = () => {
        const { rows, metadata } = getDataToExport();

        const metadataRows = [
            { '': `Export Date: ${metadata.exportDate}` },
            { '': `Total Records: ${metadata.totalRecords}` },
            { '': `Filters: ${metadata.filters}` },
            { '': '' },
        ];

        const csv =
            Papa.unparse(metadataRows) +
            '\n' +
            Papa.unparse(rows, { header: true });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', generateFilename('csv'));
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const exportToExcel = () => {
        const { rows, metadata } = getDataToExport();

        const metadataRows = [
            { '': `Export Date: ${metadata.exportDate}` },
            { '': `Total Records: ${metadata.totalRecords}` },
            { '': `Filters: ${metadata.filters}` },
            { '': '' },
        ];

        const worksheet = XLSX.utils.json_to_sheet(metadataRows, {
            skipHeader: true,
        });
        XLSX.utils.sheet_add_json(worksheet, rows, { origin: -1 });

        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Data');

        // Auto-width columns
        const colWidths = Object.keys(rows[0] || {}).map((key) => {
            const maxLength = Math.max(
                key.length,
                ...rows.map((row) => String(row[key] || '').length),
            );
            return { wch: Math.min(maxLength + 2, 50) };
        });
        worksheet['!cols'] = colWidths;

        // Freeze header row
        worksheet['!freeze'] = { xSplit: 0, ySplit: 6 };

        XLSX.writeFile(workbook, generateFilename('xlsx'));
    };

    const exportToPDF = () => {
        const { headers, rows, metadata } = getDataToExport();

        const head = [Object.values(headers)];
        const body = rows.map((row) =>
            Object.values(row).map((v) => String(v)),
        );

        const columnCount = head[0].length;
        const orientation = columnCount > 6 ? 'l' : 'p';
        const fontSize = columnCount > 10 ? 7 : columnCount > 6 ? 8 : 9;

        const doc = new jsPDF(orientation, 'pt', 'a4');

        doc.setFontSize(14);
        doc.text(
            `${filename.charAt(0).toUpperCase() + filename.slice(1)} Export`,
            40,
            30,
        );

        doc.setFontSize(9);
        doc.text(`Export Date: ${metadata.exportDate}`, 40, 50);
        doc.text(`Total Records: ${metadata.totalRecords}`, 40, 65);
        doc.text(`Filters: ${metadata.filters}`, 40, 80);

        autoTable(doc, {
            head,
            body,
            startY: 95,
            styles: {
                fontSize,
                cellPadding: 3,
                overflow: 'linebreak',
            },
            headStyles: {
                fillColor: [41, 128, 185],
                textColor: [255, 255, 255],
                halign: 'left',
                fontStyle: 'bold',
            },
            bodyStyles: {
                halign: 'left',
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245],
            },
            theme: 'striped',
            tableWidth: 'auto',
        });

        doc.save(generateFilename('pdf'));
    };

    const exportToJSON = () => {
        const { rows, metadata } = getDataToExport();

        const exportData = {
            metadata,
            data: rows,
        };

        const json = JSON.stringify(exportData, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', generateFilename('json'));
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    return (
        <div className="flex items-center">
            <div className="hidden text-sm text-muted-foreground md:block">
                {table.getSelectedRowModel().rows.length > 0 && (
                    <span className="mr-2">
                        {table.getSelectedRowModel().rows.length} of{' '}
                        {table.getFilteredRowModel().rows.length} row(s)
                        selected
                    </span>
                )}
            </div>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <DownloadIcon className="mr-2 h-4 w-4" />
                        Export
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={exportToCSV}>
                        <FileTextIcon className="mr-2 h-4 w-4" />
                        Export as CSV
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={exportToExcel}>
                        <FileSpreadsheetIcon className="mr-2 h-4 w-4" />
                        Export as Excel
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={exportToPDF}>
                        <FileArchiveIcon className="mr-2 h-4 w-4" />
                        Export as PDF
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onClick={exportToJSON}>
                        <FileTextIcon className="mr-2 h-4 w-4" />
                        Export as JSON
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
