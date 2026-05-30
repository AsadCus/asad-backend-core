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
import { importMethod } from '@/routes/manifests';
import type { FormDataConvertible } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import {
    AlertCircleIcon,
    ArrowLeftIcon,
    CheckCircleIcon,
    FileSpreadsheetIcon,
    UsersIcon,
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
    member_key: string | null;
    booking_ref: string | null;
    payer_ref: string | null;
    sharing_group_key: string | null;
    relationship: string | null;
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
}

interface ParsedPaymentRow extends Record<string, FormDataConvertible> {
    booking_ref: string | null;
    payer_ref: string | null;
    installment_no: number | null;
    invoice_amount: number | null;
    invoice_date: string | null;
    due_date: string | null;
    paid_amount: number | null;
    paid_date: string | null;
    payment_method: string | null;
    reference: string | null;
}

interface ParsedImport {
    members: ParsedMemberRow[];
    payments: ParsedPaymentRow[];
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

function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

/** Format a Date using LOCAL components — avoids the UTC day-shift that
 *  `toISOString()` causes for "DD MMM YYYY" dates in ahead-of-UTC zones. */
function ymdLocal(d: Date): string {
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function parseDate(value: unknown): string | null {
    const s = cell(value);
    if (s === '') return null;
    // Already canonical ISO.
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
    // Excel serial number (date-only at UTC midnight — keep the UTC date).
    if (/^\d+$/.test(s)) {
        const d = new Date(Math.round((Number(s) - 25569) * 86400 * 1000));
        return isNaN(d.getTime()) ? null : d.toISOString().split('T')[0];
    }
    // Textual formats (e.g. "15 Mar 2024"). Return null on garbage rather than
    // passing a fake string through to the backend.
    const d = new Date(s);
    return isNaN(d.getTime()) ? null : ymdLocal(d);
}

function parseAmount(value: unknown): number | null {
    const s = cell(value);
    if (s === '') return null;
    const n = Number(s.replace(/,/g, ''));
    if (isNaN(n)) return null;
    return n;
}

function parseIntOrNull(value: unknown): number | null {
    const n = parseAmount(value);
    return n === null ? null : Math.trunc(n);
}

/** Read non-empty data rows from a sheet, skipping the example row (row 2). */
function dataRowsOf(
    workbook: WorkBook,
    sheetName: string,
): Record<string, unknown>[] {
    const sheet = workbook.Sheets[sheetName];
    const rows = utils.sheet_to_json<Record<string, unknown>>(sheet, {
        defval: '',
    });

    return rows
        .slice(1) // skip the example row
        .filter((row) =>
            Object.values(row).some((v) => String(v ?? '').trim() !== ''),
        );
}

function parseManifestImportFile(workbook: WorkBook): ParsedImport {
    const membersSheet = workbook.SheetNames.find(
        (n) => n.toLowerCase() === 'members',
    );
    if (!membersSheet) {
        throw new Error(
            'Missing "Members" sheet. Please use the provided import template.',
        );
    }

    const memberRows = dataRowsOf(workbook, membersSheet);
    if (memberRows.length === 0) {
        throw new Error(
            'No member rows found. Fill the Members sheet starting from row 3.',
        );
    }

    const members: ParsedMemberRow[] = memberRows.map((row) => ({
        member_key: cellOrNull(row.member_key),
        booking_ref: cellOrNull(row.booking_ref),
        payer_ref: cellOrNull(row.payer_ref),
        sharing_group_key: cellOrNull(row.sharing_group_key),
        relationship: cellOrNull(row.relationship),
        name: cell(row.name),
        email: cellOrNull(row.email),
        contact: cellOrNull(row.contact),
        nric_number: cellOrNull(row.nric_number),
        passport_number: cellOrNull(row.passport_number),
        passport_issue_date: parseDate(row.passport_issue_date),
        passport_expiry_date: parseDate(row.passport_expiry_date),
        passport_place_of_issue: cellOrNull(row.passport_place_of_issue),
        nationality: cellOrNull(row.nationality),
        gender: cellOrNull(row.gender)?.toLowerCase() ?? null,
        date_of_birth: parseDate(row.date_of_birth),
        address: cellOrNull(row.address),
        sharing_plan: cell(row.sharing_plan).toLowerCase(),
        is_leader: parseBool(row.is_leader),
        has_chronic_disease: parseBool(row.has_chronic_disease),
        is_using_wheelchair: parseBool(row.is_using_wheelchair),
    }));

    // Payments sheet is optional.
    const paymentsSheet = workbook.SheetNames.find(
        (n) => n.toLowerCase() === 'payments',
    );

    const payments: ParsedPaymentRow[] = paymentsSheet
        ? dataRowsOf(workbook, paymentsSheet).map((row) => ({
              booking_ref: cellOrNull(row.booking_ref),
              payer_ref: cellOrNull(row.payer_ref),
              installment_no: parseIntOrNull(row.installment_no),
              invoice_amount: parseAmount(row.invoice_amount),
              invoice_date: parseDate(row.invoice_date),
              due_date: parseDate(row.due_date),
              paid_amount: parseAmount(row.paid_amount),
              paid_date: parseDate(row.paid_date),
              payment_method: cellOrNull(row.payment_method),
              reference: cellOrNull(row.reference),
          }))
        : [];

    return { members, payments };
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

// ─── Preview model (client-side structural validation) ──────────────────────────

interface PreviewMember {
    index: number;
    row: ParsedMemberRow;
    memberKey: string;
    payerKey: string;
    errors: string[];
}

interface PreviewPayer {
    payerKey: string;
    payerName: string;
    members: PreviewMember[];
    installments: ParsedPaymentRow[];
    invoiceTotal: number;
    paidTotal: number;
}

interface PreviewBooking {
    displayRef: string;
    members: PreviewMember[];
    payers: PreviewPayer[];
}

interface PreviewModel {
    bookings: PreviewBooking[];
    memberCount: number;
    bookingCount: number;
    payerCount: number;
    errorCount: number;
}

function synthMemberKey(row: ParsedMemberRow, index: number): string {
    return row.member_key?.trim() || `M${index + 1}`;
}

function effectiveBookingRef(row: ParsedMemberRow, index: number): string {
    return row.booking_ref?.trim() || `__auto_${index + 1}`;
}

function resolvePayer(memberKey: string, payerRef: string | null): string {
    const p = payerRef?.trim();
    return !p || p === memberKey ? memberKey : p;
}

function buildPreview(
    members: ParsedMemberRow[],
    payments: ParsedPaymentRow[],
): PreviewModel {
    const rows: PreviewMember[] = members.map((row, index) => {
        const memberKey = synthMemberKey(row, index);
        return {
            index,
            row,
            memberKey,
            payerKey: resolvePayer(memberKey, row.payer_ref),
            errors: [],
        };
    });

    const keyCounts = new Map<string, number>();
    rows.forEach((r) =>
        keyCounts.set(r.memberKey, (keyCounts.get(r.memberKey) ?? 0) + 1),
    );

    // Group members by their effective booking ref.
    const bookingMap = new Map<string, PreviewMember[]>();
    rows.forEach((r) => {
        const bookingKey = effectiveBookingRef(r.row, r.index);
        if (!bookingMap.has(bookingKey)) bookingMap.set(bookingKey, []);
        bookingMap.get(bookingKey)!.push(r);
    });

    // Index payments by raw "booking_ref|payer_ref".
    const payIndex = new Map<string, ParsedPaymentRow[]>();
    payments.forEach((p) => {
        const key = `${p.booking_ref?.trim() ?? ''}|${p.payer_ref?.trim() ?? ''}`;
        if (!payIndex.has(key)) payIndex.set(key, []);
        payIndex.get(key)!.push(p);
    });

    const bookings: PreviewBooking[] = [];

    bookingMap.forEach((memberRows) => {
        const localKeys = new Set(memberRows.map((r) => r.memberKey));
        const selfPay = new Set(
            memberRows
                .filter((r) => r.payerKey === r.memberKey)
                .map((r) => r.memberKey),
        );
        const rawBookingRef = memberRows[0].row.booking_ref?.trim() ?? '';

        memberRows.forEach((r) => {
            if (!r.row.name) r.errors.push('Missing name');
            if (!isValidSharingPlan(r.row.sharing_plan))
                r.errors.push('Invalid sharing_plan');
            if ((keyCounts.get(r.memberKey) ?? 0) > 1)
                r.errors.push(`Duplicate member_key "${r.memberKey}"`);

            const payerRef = r.row.payer_ref?.trim();
            if (payerRef && payerRef !== r.memberKey) {
                if (!localKeys.has(payerRef))
                    r.errors.push(
                        `payer_ref "${payerRef}" not in this booking`,
                    );
                else if (!selfPay.has(payerRef))
                    r.errors.push(`payer "${payerRef}" is not self-paying`);
            }
        });

        const payerGroups = new Map<string, PreviewMember[]>();
        memberRows.forEach((r) => {
            if (!payerGroups.has(r.payerKey)) payerGroups.set(r.payerKey, []);
            payerGroups.get(r.payerKey)!.push(r);
        });

        const payers: PreviewPayer[] = [];
        payerGroups.forEach((grpMembers, payerKey) => {
            const payerName =
                memberRows.find((r) => r.memberKey === payerKey)?.row.name ??
                payerKey;
            const installments =
                payIndex.get(`${rawBookingRef}|${payerKey}`) ?? [];
            payers.push({
                payerKey,
                payerName,
                members: grpMembers,
                installments,
                invoiceTotal: installments.reduce(
                    (s, p) => s + (p.invoice_amount ?? 0),
                    0,
                ),
                paidTotal: installments.reduce(
                    (s, p) => s + (p.paid_amount ?? 0),
                    0,
                ),
            });
        });

        bookings.push({
            displayRef: rawBookingRef || '(individual)',
            members: memberRows,
            payers,
        });
    });

    return {
        bookings,
        memberCount: rows.length,
        bookingCount: bookings.length,
        payerCount: bookings.reduce((s, b) => s + b.payers.length, 0),
        errorCount: rows.filter((r) => r.errors.length > 0).length,
    };
}

// ─── Template generator ───────────────────────────────────────────────────────

export function generateManifestImportTemplate(): void {
    const wb = utils.book_new();

    // ── Members sheet ──
    const memberHeaders = [
        'member_key',
        'booking_ref',
        'payer_ref',
        'sharing_group_key',
        'is_leader',
        'relationship',
        'sharing_plan',
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
        'has_chronic_disease',
        'is_using_wheelchair',
    ];

    const memberExample = [
        'M1',
        'B1',
        '', // payer_ref blank = pays for self
        'R1',
        'yes',
        'self',
        'double',
        '(Example - delete this row) Ahmad bin Ali',
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
        'no',
        'no',
    ];

    const membersWs = utils.aoa_to_sheet([memberHeaders, memberExample]);
    membersWs['!cols'] = memberHeaders.map((h, i) => ({
        wch: Math.max(
            h.length + 2,
            String(memberExample[i] ?? '').length + 2,
            14,
        ),
    }));
    memberHeaders.forEach((_, i) => {
        const ref = utils.encode_cell({ r: 0, c: i });
        if (!membersWs[ref]) membersWs[ref] = { v: memberHeaders[i], t: 's' };
        membersWs[ref].s = { font: { bold: true } };
    });
    utils.book_append_sheet(wb, membersWs, 'Members');

    // ── Payments sheet ──
    const paymentHeaders = [
        'booking_ref',
        'payer_ref',
        'installment_no',
        'invoice_amount',
        'invoice_date',
        'due_date',
        'paid_amount',
        'paid_date',
        'payment_method',
        'reference',
    ];

    const paymentExample = [
        'B1',
        'M1',
        '1',
        '12000',
        '15 Mar 2024',
        '15 Mar 2024',
        '12000',
        '15 Mar 2024',
        'bank_transfer',
        'TXN-2024-0001',
    ];

    const paymentsWs = utils.aoa_to_sheet([paymentHeaders, paymentExample]);
    paymentsWs['!cols'] = paymentHeaders.map((h, i) => ({
        wch: Math.max(
            h.length + 2,
            String(paymentExample[i] ?? '').length + 2,
            14,
        ),
    }));
    paymentHeaders.forEach((_, i) => {
        const ref = utils.encode_cell({ r: 0, c: i });
        if (!paymentsWs[ref])
            paymentsWs[ref] = { v: paymentHeaders[i], t: 's' };
        paymentsWs[ref].s = { font: { bold: true } };
    });
    utils.book_append_sheet(wb, paymentsWs, 'Payments');

    // ── Instructions sheet ──
    type InstrRow = [string, string, string];
    const _ = '';
    const instrRows: InstrRow[] = [
        ['MANIFEST MIGRATION IMPORT — INSTRUCTIONS', _, _],
        [
            'This rebuilds the full booking chain exactly like data entered by hand: Enquiry → Customer Confirmation → Quotation(s) → Order → Invoice(s) → Receipt(s) → Manifest member (grouped into rooms).',
            _,
            _,
        ],
        [
            'Fill in the Members sheet (one row per person) and, optionally, the Payments sheet (one row per installment). The TARGET MANIFEST and the APPLICATION DATE are chosen in the upload dialog — not in this file.',
            _,
            _,
        ],
        [
            'booking_ref / payer_ref / member_key / sharing_group_key are short labels YOU invent (e.g. B1, M1, R1). They only link rows together within this file; they are not stored.',
            _,
            _,
        ],
        [_, _, _],

        ['HOW GROUPING WORKS (the key columns)', _, _],
        [
            'member_key',
            'unique',
            'A short id you choose for each person (e.g. M1, M2). Used so other rows can reference them. If blank, one is generated automatically.',
        ],
        [
            'booking_ref',
            'no',
            'Members sharing a booking_ref go under ONE Enquiry + Customer Confirmation. Blank = that member is their own separate booking.',
        ],
        [
            'payer_ref',
            'no',
            'The member_key of whoever PAYS for this person. Blank = pays for themselves. Several members sharing one payer_ref = ONE group quotation under that payer. A payer must pay for themselves (no chains).',
        ],
        [
            'sharing_group_key',
            'no',
            'Members sharing this value are put in the same room/sharing group. Blank = auto-grouped by sharing_plan capacity (double=2, triple=3, quad=4).',
        ],
        [_, _, _],

        ['WORKED EXAMPLE', _, _],
        [
            'Members sheet (only the columns that matter for grouping are shown):',
            _,
            _,
        ],
        [
            'member_key | booking_ref | payer_ref | sharing_group_key | is_leader | name | sharing_plan',
            _,
            _,
        ],
        ['  M1 |  B1 |  (blank) |  R1 |  yes |  Ahmad |  double', _, _],
        ['  M2 |  B1 |  M1      |  R1 |  no  |  Siti  |  double', _, _],
        ['  M3 |  B2 |  (blank) |  R2 |  yes |  Omar  |  single', _, _],
        [
            '→ Booking B1 = one confirmation with Ahmad + Siti, ONE quotation under Ahmad covering both (because Siti’s payer_ref = M1). They share room R1. Booking B2 = Omar, self-paid, room R2.',
            _,
            _,
        ],
        ['Payments sheet (matching the example above):', _, _],
        [
            'booking_ref | payer_ref | installment_no | invoice_amount | paid_amount',
            _,
            _,
        ],
        ['  B1 |  M1 |  1 |  6000 |  6000     (deposit — paid)', _, _],
        ['  B1 |  M1 |  2 |  4000 |  (blank)  (balance — outstanding)', _, _],
        ['  B2 |  M3 |  1 |  4000 |  4000     (paid in full)', _, _],
        [
            '→ Ahmad’s order gets 2 installment invoices (6000 paid, 4000 outstanding); B1’s invoice_amounts (6000+4000) must equal the quotation total. Omar’s order gets 1 paid invoice.',
            _,
            _,
        ],
        [_, _, _],

        ['MEMBERS SHEET COLUMNS', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],
        ['name', 'YES', 'Full name of the member'],
        [
            'sharing_plan',
            'YES',
            'single, double, triple, quad, child_with_bed, child_no_bed, infant',
        ],
        ['is_leader', 'no', 'yes / no (group leader)'],
        ['relationship', 'no', 'E.g. spouse, child, parent'],
        [
            'email',
            'no',
            'If it matches an existing customer, that customer is reused',
        ],
        ['contact', 'no', 'Phone / mobile number'],
        ['nric_number', 'no', 'National ID / IC number'],
        [
            'passport_number',
            'no',
            'Used for de-duplication & matching existing customers',
        ],
        [
            'passport_issue_date',
            'no',
            'DD MMM YYYY (e.g. 01 Jan 2020) or YYYY-MM-DD',
        ],
        ['passport_expiry_date', 'no', 'DD MMM YYYY or YYYY-MM-DD'],
        ['passport_place_of_issue', 'no', 'City / country of issue'],
        ['nationality', 'no', 'E.g. Malaysian, Indonesian, Saudi'],
        ['gender', 'no', 'male / female'],
        ['date_of_birth', 'no', 'DD MMM YYYY or YYYY-MM-DD'],
        ['address', 'no', 'Full mailing address'],
        ['has_chronic_disease', 'no', 'yes / no'],
        ['is_using_wheelchair', 'no', 'yes / no'],
        [_, _, _],

        ['PAYMENTS SHEET COLUMNS (optional)', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],
        [
            'booking_ref',
            'YES',
            'Must match a booking_ref from the Members sheet',
        ],
        [
            'payer_ref',
            'YES',
            'Must match the member_key of a self-paying payer in that booking',
        ],
        ['installment_no', 'no', 'Order of installments (1, 2, 3 …)'],
        [
            'invoice_amount',
            'YES',
            'Amount billed for this installment. The installments for a payer must add up to the quotation total (package price × covered members), or the booking is rejected.',
        ],
        ['invoice_date', 'no', 'DD MMM YYYY or YYYY-MM-DD'],
        ['due_date', 'no', 'DD MMM YYYY or YYYY-MM-DD'],
        [
            'paid_amount',
            'no',
            'If > 0, a receipt is recorded for this installment. Leave blank/0 if still outstanding.',
        ],
        ['paid_date', 'no', 'Date the payment was received (can be backdated)'],
        ['payment_method', 'no', 'E.g. cash, bank_transfer, cheque, online'],
        ['reference', 'no', 'Bank reference / cheque number / etc.'],
        [_, _, _],

        ['TIPS & COMMON ERRORS', _, _],
        ['Do not rename or remove the Members / Payments sheets.', _, _],
        [
            'Do not edit or delete the example row (row 2) — it is ignored on import.',
            _,
            _,
        ],
        [
            'A payer with NO Payments rows gets one invoice for the full quotation total (left outstanding).',
            _,
            _,
        ],
        [
            'Duplicate passport_number rows on the same manifest are rejected.',
            _,
            _,
        ],
        [
            'Each booking imports independently — a bad booking is reported and skipped; the rest still import.',
            _,
            _,
        ],
    ];

    const instrWs = utils.aoa_to_sheet(instrRows);
    instrWs['!cols'] = [{ wch: 44 }, { wch: 12 }, { wch: 90 }];
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
    const [parsed, setParsed] = useState<ParsedImport>({
        members: [],
        payments: [],
    });
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

    const preview = useMemo(
        () => buildPreview(parsed.members, parsed.payments),
        [parsed],
    );

    const resetDialog = () => {
        setStep('upload');
        setParsed({ members: [], payments: [] });
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
                setParsed(parseManifestImportFile(workbook));
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
        if (parsed.members.length === 0) return;

        setIsSubmitting(true);
        setSubmitError(null);
        router.post(
            importMethod({ id: manifestId }).url,
            {
                context: { date_of_application: applicationDate },
                members: parsed.members,
                payments: parsed.payments,
            },
            {
                onFinish: () => setIsSubmitting(false),
                onSuccess: () => handleClose(),
                onError: (errs) => {
                    // Prefer our import-level message; otherwise surface the first
                    // field error so FormRequest failures aren't silently swallowed.
                    const first = errs.import ?? Object.values(errs)[0];
                    setSubmitError(
                        typeof first === 'string' && first
                            ? first
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
                            ? 'Select a target manifest, then upload an .xlsx file using the provided template. The import rebuilds the full chain: confirmations, group quotations, installment invoices, receipts, and grouped manifest members.'
                            : `Review the structure before importing — ${preview.memberCount} member(s), ${preview.bookingCount} booking(s), ${preview.payerCount} payer group(s).`}
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
                                    Used as the date_of_application on each
                                    customer confirmation created by this
                                    import.
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

                            <div className="flex flex-wrap items-center gap-2">
                                <CheckCircleIcon className="h-4 w-4 text-green-600" />
                                <span className="text-sm font-medium">
                                    {preview.memberCount} member(s)
                                </span>
                                <Badge variant="secondary">
                                    {preview.bookingCount} booking(s)
                                </Badge>
                                <Badge variant="secondary">
                                    {preview.payerCount} payer group(s)
                                </Badge>
                                {parsed.payments.length > 0 && (
                                    <Badge variant="secondary">
                                        {parsed.payments.length} installment(s)
                                    </Badge>
                                )}
                                {preview.errorCount > 0 && (
                                    <Badge variant="destructive">
                                        {preview.errorCount} row(s) with issues
                                    </Badge>
                                )}
                            </div>

                            <div className="space-y-3">
                                {preview.bookings.map((booking, bi) => (
                                    <div
                                        key={bi}
                                        className="rounded-lg border bg-muted/20"
                                    >
                                        <div className="flex items-center gap-2 border-b px-3 py-2">
                                            <UsersIcon className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-sm font-semibold">
                                                Booking: {booking.displayRef}
                                            </span>
                                            <Badge variant="outline">
                                                {booking.members.length}{' '}
                                                member(s)
                                            </Badge>
                                        </div>

                                        <div className="divide-y">
                                            {booking.payers.map((payer, pi) => (
                                                <div
                                                    key={pi}
                                                    className="px-3 py-2"
                                                >
                                                    <div className="mb-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                        <span>
                                                            Quotation paid by{' '}
                                                            <span className="font-medium text-foreground">
                                                                {
                                                                    payer.payerName
                                                                }
                                                            </span>
                                                        </span>
                                                        {payer.installments
                                                            .length > 0 ? (
                                                            <span>
                                                                ·{' '}
                                                                {
                                                                    payer
                                                                        .installments
                                                                        .length
                                                                }{' '}
                                                                installment(s),
                                                                billed{' '}
                                                                {payer.invoiceTotal.toLocaleString()}
                                                                , paid{' '}
                                                                {payer.paidTotal.toLocaleString()}
                                                            </span>
                                                        ) : (
                                                            <span>
                                                                · one invoice
                                                                (auto total),
                                                                outstanding
                                                            </span>
                                                        )}
                                                    </div>

                                                    <div className="flex flex-wrap gap-1.5">
                                                        {payer.members.map(
                                                            (m) => (
                                                                <span
                                                                    key={
                                                                        m.index
                                                                    }
                                                                    className={`inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs ${m.errors.length > 0 ? 'border-destructive/50 bg-destructive/10 text-destructive' : 'bg-background'}`}
                                                                    title={
                                                                        m.errors.join(
                                                                            '; ',
                                                                        ) ||
                                                                        undefined
                                                                    }
                                                                >
                                                                    <span className="font-medium">
                                                                        {m.row
                                                                            .name ||
                                                                            '(no name)'}
                                                                    </span>
                                                                    <span className="text-muted-foreground">
                                                                        {formatSharingPlanLabel(
                                                                            m
                                                                                .row
                                                                                .sharing_plan,
                                                                        )}
                                                                    </span>
                                                                    {m.row
                                                                        .is_leader && (
                                                                        <Badge
                                                                            variant="secondary"
                                                                            className="px-1 py-0 text-[10px]"
                                                                        >
                                                                            leader
                                                                        </Badge>
                                                                    )}
                                                                    {m.errors
                                                                        .length >
                                                                        0 && (
                                                                        <AlertCircleIcon className="h-3 w-3" />
                                                                    )}
                                                                </span>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {preview.errorCount > 0 && (
                                <div className="flex items-start gap-2 rounded-md border border-amber-500/50 bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-400">
                                    <AlertCircleIcon className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>
                                        Some rows have issues (hover the red
                                        chips for details). Bookings containing
                                        those rows will be skipped on the
                                        server; the rest still import.
                                    </span>
                                </div>
                            )}
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
                                parsed.members.length === 0 ||
                                !manifestId
                            }
                        >
                            {isSubmitting
                                ? 'Importing...'
                                : `Import ${preview.memberCount} Member(s)`}
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
