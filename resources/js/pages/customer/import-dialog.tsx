import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { importMethod } from '@/routes/customer';
import type { FormDataConvertible } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';
import {
    AlertCircleIcon,
    ArrowLeftIcon,
    CheckCircleIcon,
    FileSpreadsheetIcon,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { WorkBook } from 'xlsx';
import { read, utils, writeFile } from 'xlsx';

// ─── Types ────────────────────────────────────────────────────────────────────

interface ParsedCustomerPayload extends Record<string, FormDataConvertible> {
    name: string;
    email: string;
    contact?: string | null;
    password?: string | null;
    nric_number?: string | null;
    nationality?: string | null;
    gender?: string | null;
    marital_status?: string | null;
    date_of_birth?: string | null;
    place_of_birth?: string | null;
    address?: string | null;
    passport_number?: string | null;
    passport_issue_date?: string | null;
    passport_expiry_date?: string | null;
    passport_place_of_issue?: string | null;
    first_time_umrah?: boolean | null;
    has_chronic_disease?: boolean | null;
    is_using_wheelchair?: boolean | null;
    chronic_disease_details?: string | null;
}

// ─── Parse helpers ────────────────────────────────────────────────────────────

function cell(value: unknown): string {
    if (value === null || value === undefined) return '';
    return String(value).trim();
}

function cellOrNull(value: unknown): string | null {
    const s = cell(value);
    return s === '' ? null : s;
}

function parseBool(value: unknown): boolean | null {
    const s = cell(value).toLowerCase();
    if (s === '') return null;
    return s === 'yes' || s === 'true' || s === '1';
}

function parseDate(value: unknown): string | null {
    const s = cell(value);
    if (s === '') return null;
    // Excel serial number
    if (/^\d+$/.test(s)) {
        const d = new Date(Math.round((Number(s) - 25569) * 86400 * 1000));
        if (!isNaN(d.getTime())) return d.toISOString().split('T')[0];
    }
    // Attempt natural parse
    const d = new Date(s);
    if (!isNaN(d.getTime())) return d.toISOString().split('T')[0];
    return s; // return raw if unparseable, let backend validate
}

function parseCustomerImportFile(workbook: WorkBook): ParsedCustomerPayload[] {
    const sheetName = workbook.SheetNames.find(
        (n) => n.toLowerCase() === 'customers',
    );
    if (!sheetName) {
        throw new Error(
            'Missing "Customers" sheet. Please use the correct import template.',
        );
    }

    const sheet = workbook.Sheets[sheetName];
    const rows = utils.sheet_to_json<Record<string, unknown>>(sheet, {
        defval: '',
    });

    // rows[0] = example row (skip), rows[1+] = real data
    const dataRows = rows
        .slice(1)
        .filter((row) =>
            Object.values(row).some((v) => String(v ?? '').trim() !== ''),
        );

    if (dataRows.length === 0) {
        throw new Error(
            'No customer data found. Fill in row 3 or below in the Customers sheet.',
        );
    }

    return dataRows.map((row) => ({
        name: cell(row.name),
        email: cell(row.email),
        contact: cellOrNull(row.contact),
        password: cellOrNull(row.password),
        nric_number: cellOrNull(row.nric_number),
        nationality: cellOrNull(row.nationality),
        gender: cellOrNull(row.gender),
        marital_status: cellOrNull(row.marital_status),
        date_of_birth: parseDate(row.date_of_birth),
        place_of_birth: cellOrNull(row.place_of_birth),
        address: cellOrNull(row.address),
        passport_number: cellOrNull(row.passport_number),
        passport_issue_date: parseDate(row.passport_issue_date),
        passport_expiry_date: parseDate(row.passport_expiry_date),
        passport_place_of_issue: cellOrNull(row.passport_place_of_issue),
        first_time_umrah: parseBool(row.first_time_umrah),
        has_chronic_disease: parseBool(row.has_chronic_disease),
        is_using_wheelchair: parseBool(row.is_using_wheelchair),
        chronic_disease_details: cellOrNull(row.chronic_disease_details),
    }));
}

// ─── Template generator ───────────────────────────────────────────────────────

export function generateCustomerImportTemplate(): void {
    const xlUtils = utils;
    const xlWrite = writeFile;

    type InstrRow = [string, string, string];

    function addSheet(
        wb: ReturnType<typeof utils.book_new>,
        sheetName: string,
        headers: string[],
        exampleRow: (string | number | null)[],
    ): void {
        const ws = xlUtils.aoa_to_sheet([headers, exampleRow]);
        ws['!cols'] = headers.map((h, i) => ({
            wch: Math.max(
                h.length + 2,
                String(exampleRow[i] ?? '').length + 2,
                14,
            ),
        }));

        // Bold header row
        headers.forEach((_, i) => {
            const ref = xlUtils.encode_cell({ r: 0, c: i });
            if (!ws[ref]) ws[ref] = { v: headers[i], t: 's' };
            ws[ref].s = { font: { bold: true } };
        });

        xlUtils.book_append_sheet(wb, ws, sheetName);
    }

    const wb = xlUtils.book_new();

    // ── Customers sheet ──────────────────────────────────────────────────────
    const headers = [
        'name',
        'email',
        'contact',
        'password',
        'nric_number',
        'nationality',
        'gender',
        'marital_status',
        'date_of_birth',
        'place_of_birth',
        'address',
        'passport_number',
        'passport_issue_date',
        'passport_expiry_date',
        'passport_place_of_issue',
        'first_time_umrah',
        'has_chronic_disease',
        'is_using_wheelchair',
        'chronic_disease_details',
    ];

    const exampleRow = [
        '(Example - do not modify this row) Ahmad bin Ali',
        'ahmad.ali@example.com',
        '0123456789',
        'secret123',
        '900101011234',
        'Malaysian',
        'male',
        'married',
        '01 Jan 1990',
        'Kuala Lumpur',
        'No. 12, Jalan Maju, 50000 KL',
        'A12345678',
        '01 Jan 2020',
        '01 Jan 2030',
        'Putrajaya',
        'yes',
        'no',
        'no',
        '',
    ];

    addSheet(wb, 'Customers', headers, exampleRow);

    // ── Instructions sheet ───────────────────────────────────────────────────
    const _ = '';
    const instrRows: InstrRow[] = [
        // intro
        ['CUSTOMER IMPORT INSTRUCTIONS', _, _],
        [
            'Fill the Customers sheet starting from row 3. Each row = one customer. Row 2 is an example — do not modify it.',
            _,
            _,
        ],
        [_, _, _],

        // Customers section header
        ['Customers Sheet Columns', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],

        // user identity
        ['name', 'YES', 'Full name of the customer'],
        [
            'email',
            'YES',
            'Valid email. Must be unique — duplicate emails are rejected.',
        ],
        ['contact', 'no', 'Phone or mobile number'],
        [
            'password',
            'no',
            'Min 6 characters. Leave blank to use a temporary default password.',
        ],

        // customer record
        ['nric_number', 'no', 'National ID / IC number'],
        ['nationality', 'no', 'E.g. Malaysian, Indonesian, Saudi'],
        ['gender', 'no', 'male / female'],
        ['marital_status', 'no', 'single / married / divorced / widowed'],
        [
            'date_of_birth',
            'no',
            'Date format: DD MMM YYYY (e.g. 01 Jan 1990) or YYYY-MM-DD',
        ],
        ['place_of_birth', 'no', 'City or country of birth'],
        ['address', 'no', 'Full mailing address'],

        // passport
        ['passport_number', 'no', 'E.g. A12345678'],
        ['passport_issue_date', 'no', 'Date format: DD MMM YYYY or YYYY-MM-DD'],
        [
            'passport_expiry_date',
            'no',
            'Date format: DD MMM YYYY or YYYY-MM-DD',
        ],
        [
            'passport_place_of_issue',
            'no',
            'City / country where passport was issued',
        ],

        // flags
        [
            'first_time_umrah',
            'no',
            'yes / no  (is this customer doing Umrah for the first time?)',
        ],
        ['has_chronic_disease', 'no', 'yes / no'],
        ['is_using_wheelchair', 'no', 'yes / no'],
        [
            'chronic_disease_details',
            'no',
            'Describe any chronic illness. Only relevant if has_chronic_disease = yes.',
        ],

        [_, _, _],

        // tips
        ['Tips & Common Errors', _, _],
        ['Do not add extra sheets or rename the Customers sheet.', _, _],
        ['Do not delete or edit the example row (row 2).', _, _],
        [
            'Dates must be in DD MMM YYYY format (e.g. 15 Mar 2025). Excel date cells are also accepted.',
            _,
            _,
        ],
        [
            'Boolean fields (first_time_umrah, has_chronic_disease, is_using_wheelchair): type yes or no.',
            _,
            _,
        ],
        [
            'Passport / photo files cannot be imported via Excel. Upload them individually after import.',
            _,
            _,
        ],
        [
            'Each customer creates both a User account and a Customer profile.',
            _,
            _,
        ],
    ];

    const instrWs = xlUtils.aoa_to_sheet(instrRows);
    instrWs['!cols'] = [{ wch: 55 }, { wch: 12 }, { wch: 72 }];

    // Bold: section headers (col A only) and the column-header row
    // Row 25 = "Tips & Common Errors" (shifted -1 after customer_number removal).
    const boldColA = new Set([0, 3, 25]);
    const boldAllCols = new Set([4]);

    instrRows.forEach((row, r) => {
        if (boldColA.has(r)) {
            const ref = xlUtils.encode_cell({ r, c: 0 });
            if (!instrWs[ref]) instrWs[ref] = { v: row[0], t: 's' };
            instrWs[ref].s = { font: { bold: true } };
        }
        if (boldAllCols.has(r)) {
            for (let c = 0; c < 3; c++) {
                const ref = xlUtils.encode_cell({ r, c });
                if (!instrWs[ref]) instrWs[ref] = { v: row[c], t: 's' };
                instrWs[ref].s = { font: { bold: true } };
            }
        }
    });

    xlUtils.book_append_sheet(wb, instrWs, 'Instructions');

    xlWrite(wb, 'customer-import-template.xlsx');
}

// ─── Dialog component ─────────────────────────────────────────────────────────

interface Props {
    open: boolean;
    onClose: () => void;
}

export function CustomerImportDialog({ open, onClose }: Props) {
    const [step, setStep] = useState<'upload' | 'preview'>('upload');
    const [parsedCustomers, setParsedCustomers] = useState<
        ParsedCustomerPayload[]
    >([]);
    const [parseError, setParseError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { errors } = usePage().props as { errors: Record<string, string> };

    const resetDialog = () => {
        setStep('upload');
        setParsedCustomers([]);
        setParseError(null);
        setIsSubmitting(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleClose = () => {
        resetDialog();
        onClose();
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setParseError(null);

        const reader = new FileReader();
        reader.onload = (event) => {
            try {
                const data = event.target?.result;
                const workbook = read(data, { type: 'array' });
                const customers = parseCustomerImportFile(workbook);
                setParsedCustomers(customers);
                setStep('preview');
            } catch (err) {
                setParseError(
                    err instanceof Error
                        ? err.message
                        : 'Failed to parse file. Please check the template.',
                );
            }
        };
        reader.readAsArrayBuffer(file);
    };

    const handleSubmit = () => {
        if (parsedCustomers.length === 0) return;
        setIsSubmitting(true);
        router.post(
            importMethod().url,
            { data: parsedCustomers },
            {
                onFinish: () => setIsSubmitting(false),
                onSuccess: () => handleClose(),
            },
        );
    };

    const importError = errors?.import;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="flex max-h-[90vh] max-w-4xl flex-col">
                <DialogHeader>
                    <DialogTitle>
                        {step === 'upload'
                            ? 'Import Customers'
                            : 'Preview Import Data'}
                    </DialogTitle>
                    <DialogDescription>
                        {step === 'upload'
                            ? 'Upload an .xlsx file using the provided template. Each row creates one customer.'
                            : `Review the parsed data before importing. ${parsedCustomers.length} customer(s) found.`}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto">
                    {step === 'upload' && (
                        <div className="space-y-4 py-2">
                            {importError && (
                                <div className="flex items-start gap-2 rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
                                    <AlertCircleIcon className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{importError}</span>
                                </div>
                            )}

                            <label
                                htmlFor="customer-import-file"
                                className="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-muted-foreground/30 px-6 py-12 text-center transition-colors hover:border-primary/50 hover:bg-muted/30"
                            >
                                <FileSpreadsheetIcon className="h-10 w-10 text-muted-foreground" />
                                <div>
                                    <p className="font-medium">
                                        Click to upload or drag &amp; drop
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        .xlsx files only
                                    </p>
                                </div>
                                <input
                                    id="customer-import-file"
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".xlsx"
                                    className="sr-only"
                                    onChange={handleFileChange}
                                />
                            </label>

                            {parseError && (
                                <div className="flex items-start gap-2 rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
                                    <AlertCircleIcon className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{parseError}</span>
                                </div>
                            )}
                        </div>
                    )}

                    {step === 'preview' && (
                        <div className="space-y-4 py-2">
                            <div className="flex items-center gap-2">
                                <CheckCircleIcon className="h-4 w-4 text-green-600" />
                                <span className="text-sm font-medium">
                                    {parsedCustomers.length} customer(s) ready
                                    to import
                                </span>
                                <Badge variant="secondary">
                                    {parsedCustomers.length}
                                </Badge>
                            </div>

                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-8">
                                                #
                                            </TableHead>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Email</TableHead>
                                            <TableHead>Contact</TableHead>
                                            <TableHead>Gender</TableHead>
                                            <TableHead>Nationality</TableHead>
                                            <TableHead>Passport No.</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {parsedCustomers.map((c, i) => (
                                            <TableRow key={i}>
                                                <TableCell className="text-muted-foreground">
                                                    {i + 1}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {c.name || (
                                                        <span className="text-destructive">
                                                            (missing)
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {c.email || (
                                                        <span className="text-destructive">
                                                            (missing)
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {c.contact ?? '—'}
                                                </TableCell>
                                                <TableCell className="capitalize">
                                                    {c.gender ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {c.nationality ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {c.passport_number ?? '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-2">
                    {step === 'preview' && (
                        <Button
                            variant="outline"
                            onClick={() => setStep('upload')}
                            disabled={isSubmitting}
                        >
                            <ArrowLeftIcon className="h-4 w-4" />
                            Back
                        </Button>
                    )}
                    <Button variant="outline" onClick={handleClose}>
                        Cancel
                    </Button>
                    {step === 'preview' && (
                        <Button
                            onClick={handleSubmit}
                            disabled={
                                isSubmitting || parsedCustomers.length === 0
                            }
                        >
                            {isSubmitting
                                ? 'Importing...'
                                : `Import ${parsedCustomers.length} Customer(s)`}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
