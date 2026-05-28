import { DatePickerField } from '@/components/date-picker';
import { ProperInputSelect } from '@/components/proper-input-select';
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
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateForDisplay } from '@/lib/utils';
import { importMethod } from '@/routes/manifests';
import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import {
    AlertCircleIcon,
    ArrowLeftIcon,
    CheckCircleIcon,
    FileSpreadsheetIcon,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import type { WorkBook } from 'xlsx';
import { read, utils, writeFile } from 'xlsx';

// ─── Types ────────────────────────────────────────────────────────────────────

const SHARING_PLAN_VALUES = [
    'single',
    'double',
    'triple',
    'quad',
    'child_with_bed',
    'child_no_bed',
    'infant',
] as const;

type SharingPlan = (typeof SHARING_PLAN_VALUES)[number];

interface ParsedMemberRow extends Record<string, FormDataConvertible> {
    name: string;
    email: string | null;
    contact: string | null;
    nric_number: string | null;
    passport_number: string | null;
    passport_issue_date: string | null;
    passport_expiry_date: string | null;
    passport_place_of_issue: string | null;
    nationality: string | null;
    gender: string | null;
    date_of_birth: string | null;
    address: string | null;
    sharing_plan: string;
    is_leader: boolean;
    has_chronic_disease: boolean;
    is_using_wheelchair: boolean;
    invoice_amount: number | null;
    receipt_date: string | null;
    receipt_amount: number | null;
    receipt_method: string | null;
    receipt_reference: string | null;
}

export interface ManifestImportOption {
    id: number;
    label: string;
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

function parseBool(value: unknown): boolean {
    const s = cell(value).toLowerCase();
    return s === 'yes' || s === 'true' || s === '1' || s === 'y';
}

function parseDate(value: unknown): string | null {
    const s = cell(value);
    if (s === '') return null;
    if (/^\d+$/.test(s)) {
        const d = new Date(Math.round((Number(s) - 25569) * 86400 * 1000));
        if (!isNaN(d.getTime())) return d.toISOString().split('T')[0];
    }
    const d = new Date(s);
    if (!isNaN(d.getTime())) return d.toISOString().split('T')[0];
    return s;
}

function parseAmount(value: unknown): number | null {
    const s = cell(value);
    if (s === '') return null;
    const n = Number(s.replace(/,/g, ''));
    if (isNaN(n)) return null;
    return n;
}

function parseManifestImportFile(workbook: WorkBook): ParsedMemberRow[] {
    const sheetName = workbook.SheetNames.find(
        (n) => n.toLowerCase() === 'members',
    );
    if (!sheetName) {
        throw new Error(
            'Missing "Members" sheet. Please use the provided import template.',
        );
    }

    const sheet = workbook.Sheets[sheetName];
    const rows = utils.sheet_to_json<Record<string, unknown>>(sheet, {
        defval: '',
    });

    const dataRows = rows
        .slice(1)
        .filter((row) =>
            Object.values(row).some((v) => String(v ?? '').trim() !== ''),
        );

    if (dataRows.length === 0) {
        throw new Error(
            'No member rows found. Fill the Members sheet starting from row 3.',
        );
    }

    return dataRows.map((row) => ({
        name: cell(row.name),
        email: cellOrNull(row.email),
        contact: cellOrNull(row.contact),
        nric_number: cellOrNull(row.nric_number),
        passport_number: cellOrNull(row.passport_number),
        passport_issue_date: parseDate(row.passport_issue_date),
        passport_expiry_date: parseDate(row.passport_expiry_date),
        passport_place_of_issue: cellOrNull(row.passport_place_of_issue),
        nationality: cellOrNull(row.nationality),
        gender: cellOrNull(row.gender),
        date_of_birth: parseDate(row.date_of_birth),
        address: cellOrNull(row.address),
        sharing_plan: cell(row.sharing_plan).toLowerCase(),
        is_leader: parseBool(row.is_leader),
        has_chronic_disease: parseBool(row.has_chronic_disease),
        is_using_wheelchair: parseBool(row.is_using_wheelchair),
        invoice_amount: parseAmount(row.invoice_amount),
        receipt_date: parseDate(row.receipt_date),
        receipt_amount: parseAmount(row.receipt_amount),
        receipt_method: cellOrNull(row.receipt_method),
        receipt_reference: cellOrNull(row.receipt_reference),
    }));
}

function isValidSharingPlan(value: string): value is SharingPlan {
    return (SHARING_PLAN_VALUES as readonly string[]).includes(value);
}

const SHARING_PLAN_LABELS: Record<SharingPlan, string> = {
    single: 'Single',
    double: 'Double',
    triple: 'Triple',
    quad: 'Quad',
    child_with_bed: 'Child with Bed',
    child_no_bed: 'Child without Bed',
    infant: 'Infant',
};

function formatSharingPlanLabel(value: string): string {
    return isValidSharingPlan(value) ? SHARING_PLAN_LABELS[value] : value;
}

// ─── Template generator ───────────────────────────────────────────────────────

export function generateManifestImportTemplate(): void {
    type InstrRow = [string, string, string];

    const headers = [
        'name',
        'email',
        'contact',
        'nric_number',
        'passport_number',
        'passport_issue_date',
        'passport_expiry_date',
        'passport_place_of_issue',
        'nationality',
        'gender',
        'date_of_birth',
        'address',
        'sharing_plan',
        'is_leader',
        'has_chronic_disease',
        'is_using_wheelchair',
        'invoice_amount',
        'receipt_date',
        'receipt_amount',
        'receipt_method',
        'receipt_reference',
    ];

    const exampleRow = [
        '(Example - do not modify this row) Ahmad bin Ali',
        'ahmad.ali@example.com',
        '0123456789',
        '900101011234',
        'A12345678',
        '01 Jan 2020',
        '01 Jan 2030',
        'Putrajaya',
        'Malaysian',
        'male',
        '01 Jan 1990',
        'No. 12, Jalan Maju, 50000 KL',
        'double',
        'yes',
        'no',
        'no',
        '12000',
        '15 Mar 2024',
        '12000',
        'bank_transfer',
        'TXN-2024-0001',
    ];

    const ws = utils.aoa_to_sheet([headers, exampleRow]);
    ws['!cols'] = headers.map((h, i) => ({
        wch: Math.max(h.length + 2, String(exampleRow[i] ?? '').length + 2, 14),
    }));

    headers.forEach((_, i) => {
        const ref = utils.encode_cell({ r: 0, c: i });
        if (!ws[ref]) ws[ref] = { v: headers[i], t: 's' };
        ws[ref].s = { font: { bold: true } };
    });

    const wb = utils.book_new();
    utils.book_append_sheet(wb, ws, 'Members');

    const _ = '';
    const instrRows: InstrRow[] = [
        ['MANIFEST MEMBER IMPORT (BACKFILL) — INSTRUCTIONS', _, _],
        [
            'Each row creates ONE member on the selected manifest, plus the full chain: customer, confirmation, quotation, order, invoice, and (optional) receipt.',
            _,
            _,
        ],
        [
            'All rows in this file are grouped under ONE shared Customer Confirmation. Re-importing creates a NEW confirmation batch — duplicates are detected by passport number.',
            _,
            _,
        ],
        [_, _, _],

        ['Members Sheet Columns', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],

        ['name', 'YES', 'Full name of the member'],
        [
            'email',
            'no',
            'Valid email. If matches an existing customer, that customer is reused.',
        ],
        ['contact', 'no', 'Phone or mobile number'],
        ['nric_number', 'no', 'National ID / IC number'],
        [
            'passport_number',
            'no',
            'Used for de-duplication. If matches an existing customer, that customer is reused.',
        ],
        [
            'passport_issue_date',
            'no',
            'Date format: DD MMM YYYY (e.g. 01 Jan 2020) or YYYY-MM-DD',
        ],
        ['passport_expiry_date', 'no', 'Date format: DD MMM YYYY or YYYY-MM-DD'],
        ['passport_place_of_issue', 'no', 'City / country where passport was issued'],
        ['nationality', 'no', 'E.g. Malaysian, Indonesian, Saudi'],
        ['gender', 'no', 'male / female'],
        [
            'date_of_birth',
            'no',
            'Date format: DD MMM YYYY (e.g. 01 Jan 1990) or YYYY-MM-DD',
        ],
        ['address', 'no', 'Full mailing address'],

        [
            'sharing_plan',
            'YES',
            'One of: single, double, triple, quad, child_with_bed, child_no_bed, infant',
        ],
        ['is_leader', 'no', 'yes / no (default no)'],
        ['has_chronic_disease', 'no', 'yes / no'],
        ['is_using_wheelchair', 'no', 'yes / no'],

        [
            'invoice_amount',
            'no',
            'Numeric. If blank, defaults to the package price for the chosen sharing_plan.',
        ],
        [
            'receipt_date',
            'no',
            'Date the member historically paid. Required if receipt_amount is set.',
        ],
        [
            'receipt_amount',
            'no',
            'Numeric. If blank or 0, no receipt is created (invoice stays outstanding).',
        ],
        [
            'receipt_method',
            'no',
            'E.g. cash, bank_transfer, cheque, online. Free-text.',
        ],
        ['receipt_reference', 'no', 'Bank reference / cheque number / etc.'],

        [_, _, _],

        ['Tips & Common Errors', _, _],
        ['Do not rename or remove the Members sheet.', _, _],
        ['Do not edit or delete the example row (row 2).', _, _],
        [
            'For members who paid in multiple installments, leave receipt_* blank and add the extra receipts manually after import.',
            _,
            _,
        ],
        [
            'Duplicate passport_number rows on the same manifest are rejected.',
            _,
            _,
        ],
    ];

    const instrWs = utils.aoa_to_sheet(instrRows);
    instrWs['!cols'] = [{ wch: 60 }, { wch: 12 }, { wch: 72 }];

    utils.book_append_sheet(wb, instrWs, 'Instructions');

    writeFile(wb, 'manifest-import-template.xlsx');
}

// ─── Dialog component ─────────────────────────────────────────────────────────

interface Props {
    open: boolean;
    onClose: () => void;
    manifests: ManifestImportOption[];
    defaultManifestId?: number | null;
}

export function ManifestImportDialog({
    open,
    onClose,
    manifests,
    defaultManifestId,
}: Props) {
    const [step, setStep] = useState<'upload' | 'preview'>('upload');
    const [parsedRows, setParsedRows] = useState<ParsedMemberRow[]>([]);
    const [parseError, setParseError] = useState<string | null>(null);
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const [manifestId, setManifestId] = useState<string>(
        defaultManifestId != null ? String(defaultManifestId) : '',
    );
    const [applicationDate, setApplicationDate] = useState<string>(
        new Date().toISOString().split('T')[0],
    );
    const fileInputRef = useRef<HTMLInputElement>(null);

    const validRows = useMemo(
        () =>
            parsedRows.filter(
                (r) => r.name && isValidSharingPlan(r.sharing_plan),
            ).length,
        [parsedRows],
    );

    const resetDialog = () => {
        setStep('upload');
        setParsedRows([]);
        setParseError(null);
        setSubmitError(null);
        setIsSubmitting(false);
        setIsDragging(false);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleClose = () => {
        resetDialog();
        onClose();
    };

    const processFile = (file: File) => {
        setParseError(null);
        setSubmitError(null);
        const reader = new FileReader();
        reader.onload = (event) => {
            try {
                const data = event.target?.result;
                const workbook = read(data, { type: 'array' });
                const rows = parseManifestImportFile(workbook);
                setParsedRows(rows);
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

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) processFile(file);
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
        const file = e.dataTransfer.files?.[0];
        if (file) processFile(file);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = () => setIsDragging(false);

    const handleSubmit = () => {
        if (!manifestId) {
            setSubmitError('Please select a target manifest.');
            return;
        }
        if (parsedRows.length === 0) return;

        setIsSubmitting(true);
        setSubmitError(null);
        router.post(
            importMethod({ id: manifestId }).url,
            {
                context: { date_of_application: applicationDate },
                data: parsedRows,
            },
            {
                onFinish: () => setIsSubmitting(false),
                onSuccess: () => handleClose(),
                onError: (errs) => {
                    setSubmitError(
                        typeof errs.import === 'string'
                            ? errs.import
                            : 'Import failed. Please check your data and try again.',
                    );
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-h-[95%] max-w-[95%] overflow-y-auto md:min-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {step === 'upload'
                            ? 'Import Manifest Members'
                            : 'Preview Import Data'}
                    </DialogTitle>
                    <DialogDescription>
                        {step === 'upload'
                            ? 'Select a target manifest, then upload an .xlsx file using the provided template. Each row creates one member plus its full payment chain.'
                            : `Review the parsed data before importing. ${parsedRows.length} row(s) found, ${validRows} look valid.`}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto">
                    {step === 'upload' && (
                        <div className="space-y-4 py-2">
                            <div className="grid gap-2">
                                <Label htmlFor="manifest-import-manifest">
                                    Target Manifest
                                </Label>
                                <ProperInputSelect
                                    id="manifest-import-manifest"
                                    options={manifests.map((m) => ({
                                        value: String(m.id),
                                        label: m.label,
                                    }))}
                                    value={manifestId}
                                    onValueChange={(v) =>
                                        setManifestId(String(v))
                                    }
                                    placeholder="Search & select a manifest…"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="manifest-import-date">
                                    Default Application Date
                                </Label>
                                <DatePickerField
                                    id="manifest-import-date"
                                    value={applicationDate}
                                    valueFormat="iso"
                                    disabled={false}
                                    onChange={setApplicationDate}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Used as the date_of_application on the
                                    shared customer confirmation created for
                                    this batch.
                                </p>
                            </div>

                            <label
                                htmlFor="manifest-import-file"
                                className={`flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed px-6 py-12 text-center transition-colors ${isDragging ? 'border-primary/70 bg-muted/50' : 'border-muted-foreground/30 hover:border-primary/50 hover:bg-muted/30'}`}
                                onDragOver={handleDragOver}
                                onDragLeave={handleDragLeave}
                                onDrop={handleDrop}
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
                                    id="manifest-import-file"
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
                            {submitError && (
                                <div className="flex items-start gap-2 rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
                                    <AlertCircleIcon className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span className="whitespace-pre-wrap">
                                        {submitError}
                                    </span>
                                </div>
                            )}

                            <div className="flex items-center gap-2">
                                <CheckCircleIcon className="h-4 w-4 text-green-600" />
                                <span className="text-sm font-medium">
                                    {parsedRows.length} member row(s) parsed
                                </span>
                                <Badge variant="secondary">
                                    {validRows} valid
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
                                            <TableHead>Passport</TableHead>
                                            <TableHead>Sharing</TableHead>
                                            <TableHead>Leader</TableHead>
                                            <TableHead>Invoice</TableHead>
                                            <TableHead>Receipt</TableHead>
                                            <TableHead>Receipt Date</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {parsedRows.map((r, i) => {
                                            const planValid =
                                                isValidSharingPlan(
                                                    r.sharing_plan,
                                                );
                                            return (
                                                <TableRow key={i}>
                                                    <TableCell className="text-muted-foreground">
                                                        {i + 1}
                                                    </TableCell>
                                                    <TableCell className="font-medium">
                                                        {r.name || (
                                                            <span className="text-destructive">
                                                                (missing)
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {r.passport_number ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {planValid ? (
                                                            formatSharingPlanLabel(
                                                                r.sharing_plan,
                                                            )
                                                        ) : (
                                                            <span className="text-destructive">
                                                                {r.sharing_plan ||
                                                                    '(missing)'}
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {r.is_leader
                                                            ? 'Yes'
                                                            : '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {r.invoice_amount ??
                                                            '(auto)'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {r.receipt_amount ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {r.receipt_date
                                                            ? formatDateForDisplay(
                                                                  r.receipt_date,
                                                              )
                                                            : '—'}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
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
                                isSubmitting ||
                                parsedRows.length === 0 ||
                                !manifestId
                            }
                        >
                            {isSubmitting
                                ? 'Importing...'
                                : `Import ${parsedRows.length} Member(s)`}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
