import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
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
import { importMethod } from '@/routes/packages';
import type { FormDataConvertible } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/react';
import {
    AlertCircleIcon,
    ArrowLeftIcon,
    CheckCircleIcon,
    FileSpreadsheetIcon,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { read, utils, writeFile, type WorkBook } from 'xlsx';
import {
    officialTypeOptions,
    packageStatusColors,
    packageStatusLabels,
} from './schema';

interface ParsedAccommodation {
    location: string;
    hotel_name: string;
    type_of_meal?: string;
    first_meal?: string;
    last_meal?: string;
    check_in?: string;
    check_out?: string;
    ic?: string;
    ic_contact_number?: string;
    remarks?: string;
}

interface ParsedFlight {
    from?: string;
    to?: string;
    description?: string;
    airline?: string;
    pnr?: string;
    departure_datetime?: string;
    arrival_datetime?: string;
    remarks?: string;
}

interface ParsedTrainTicket {
    from?: string;
    to?: string;
    travel_date?: string;
    travel_time?: string;
    remarks?: string;
}

interface ParsedTransportationPlan {
    from?: string;
    to?: string;
    travel_date?: string;
    travel_time?: string;
    remarks?: string;
}

interface ParsedRawdahTasreeh {
    date?: string;
    women_passengers?: string | number;
    women_time?: string;
    men_passengers?: string | number;
    men_time?: string;
    remarks?: string;
}

interface ParsedOfficial {
    type?: string;
    name?: string;
    contact_number?: string;
    nationality?: string;
    passport_number?: string;
    gender?: string;
    date_of_birth?: string;
    place_of_birth?: string;
    passport_issue_date?: string;
    passport_expiry_date?: string;
    passport_place_of_issue?: string;
}

interface ParsedPackagePayload {
    name: string;
    status: string;
    total_seats: number;
    departure_date?: string | null;
    return_date?: string | null;
    price_single?: string | number | null;
    price_double?: string | number | null;
    price_triple?: string | number | null;
    price_quad?: string | number | null;
    child_with_bed_price?: string | number | null;
    child_no_bed_price?: string | number | null;
    infant_price?: string | number | null;
    visa_type?: string | null;
    vehicle_type?: string | null;
    vehicle_driver_name?: string | null;
    vehicle_driver_contact_number?: string | null;
    ticket_type?: string | null;
    train_description?: string | null;
    included?: string | null;
    not_included?: string | null;
    offer?: string | null;
    remarks?: string | null;
    accommodations: ParsedAccommodation[];
    flights: ParsedFlight[];
    train_tickets: ParsedTrainTicket[];
    transportation_plans: ParsedTransportationPlan[];
    rawdah_tasreehs: ParsedRawdahTasreeh[];
    officials: ParsedOfficial[];
}

function cell(value: unknown): string {
    if (value === null || value === undefined) return '';
    return String(value).trim();
}

function parseSheetRows<T>(
    workbook: WorkBook,
    sheetName: string,
    mapper: (row: Record<string, unknown>) => T,
): T[] {
    const sheet =
        workbook.Sheets[
            workbook.SheetNames.find(
                (n) => n.toLowerCase() === sheetName.toLowerCase(),
            ) ?? ''
        ];
    if (!sheet) return [];

    const rows = utils.sheet_to_json<Record<string, unknown>>(sheet, {
        defval: '',
    });

    // Row 0 is treated as headers by sheet_to_json; row 1 (index 0) = example row, skip it
    return rows
        .slice(1)
        .filter((row) =>
            Object.values(row).some((v) => String(v ?? '').trim() !== ''),
        )
        .map(mapper);
}

function parsePackageImportFile(workbook: WorkBook): ParsedPackagePayload {
    const pkgSheetName = workbook.SheetNames.find(
        (n) => n.toLowerCase() === 'package',
    );
    if (!pkgSheetName) {
        throw new Error(
            'Missing "Package" sheet. Please use the correct import template.',
        );
    }

    const pkgSheet = workbook.Sheets[pkgSheetName];
    const pkgRows = utils.sheet_to_json<Record<string, unknown>>(pkgSheet, {
        defval: '',
    });

    // pkgRows[0] = example row (skip), pkgRows[1] = first real data row
    const pkg = pkgRows[1];
    if (!pkg) {
        throw new Error(
            'No package data found. Fill in row 3 of the Package sheet.',
        );
    }

    const accommodations = parseSheetRows(
        workbook,
        'Accommodations',
        (row) => ({
            location: cell(row.location),
            hotel_name: cell(row.hotel_name),
            type_of_meal: cell(row.type_of_meal) || undefined,
            first_meal: cell(row.first_meal) || undefined,
            last_meal: cell(row.last_meal) || undefined,
            check_in: cell(row.check_in) || undefined,
            check_out: cell(row.check_out) || undefined,
            ic: cell(row.ic) || undefined,
            ic_contact_number: cell(row.ic_contact_number) || undefined,
            remarks: cell(row.remarks) || undefined,
        }),
    );

    const flights = parseSheetRows(workbook, 'Flights', (row) => ({
        from: cell(row.from) || undefined,
        to: cell(row.to) || undefined,
        description: cell(row.description) || undefined,
        airline: cell(row.airline) || undefined,
        pnr: cell(row.pnr) || undefined,
        departure_datetime: cell(row.departure_datetime) || undefined,
        arrival_datetime: cell(row.arrival_datetime) || undefined,
        remarks: cell(row.remarks) || undefined,
    }));

    const trainTickets = parseSheetRows(workbook, 'Train_Tickets', (row) => ({
        from: cell(row.from) || undefined,
        to: cell(row.to) || undefined,
        travel_date: cell(row.travel_date) || undefined,
        travel_time: cell(row.travel_time) || undefined,
        remarks: cell(row.remarks) || undefined,
    }));

    const transportationPlans = parseSheetRows(
        workbook,
        'Transportation_Plans',
        (row) => ({
            from: cell(row.from) || undefined,
            to: cell(row.to) || undefined,
            travel_date: cell(row.travel_date) || undefined,
            travel_time: cell(row.travel_time) || undefined,
            remarks: cell(row.remarks) || undefined,
        }),
    );

    const rawdahTasreehs = parseSheetRows(
        workbook,
        'Rawdah_Tasreehs',
        (row) => ({
            date: cell(row.date) || undefined,
            women_passengers: cell(row.women_passengers) || undefined,
            women_time: cell(row.women_time) || undefined,
            men_passengers: cell(row.men_passengers) || undefined,
            men_time: cell(row.men_time) || undefined,
            remarks: cell(row.remarks) || undefined,
        }),
    );

    const officials = parseSheetRows(workbook, 'Officials', (row) => ({
        type: cell(row.type) || undefined,
        name: cell(row.name) || undefined,
        contact_number: cell(row.contact_number) || undefined,
        nationality: cell(row.nationality) || undefined,
        passport_number: cell(row.passport_number) || undefined,
        gender: cell(row.gender) || undefined,
        date_of_birth: cell(row.date_of_birth) || undefined,
        place_of_birth: cell(row.place_of_birth) || undefined,
        passport_issue_date: cell(row.passport_issue_date) || undefined,
        passport_expiry_date: cell(row.passport_expiry_date) || undefined,
        passport_place_of_issue: cell(row.passport_place_of_issue) || undefined,
    }));

    return {
        name: cell(pkg.name),
        status: cell(pkg.status) || 'open',
        total_seats: Number(pkg.total_seats) || 0,
        departure_date: cell(pkg.departure_date) || null,
        return_date: cell(pkg.return_date) || null,
        price_single: cell(pkg.price_single) || null,
        price_double: cell(pkg.price_double) || null,
        price_triple: cell(pkg.price_triple) || null,
        price_quad: cell(pkg.price_quad) || null,
        child_with_bed_price: cell(pkg.child_with_bed_price) || null,
        child_no_bed_price: cell(pkg.child_no_bed_price) || null,
        infant_price: cell(pkg.infant_price) || null,
        visa_type: cell(pkg.visa_type) || null,
        vehicle_type: cell(pkg.vehicle_type) || null,
        vehicle_driver_name: cell(pkg.vehicle_driver_name) || null,
        vehicle_driver_contact_number:
            cell(pkg.vehicle_driver_contact_number) || null,
        ticket_type: cell(pkg.ticket_type) || null,
        train_description: cell(pkg.train_description) || null,
        included: cell(pkg.included) || null,
        not_included: cell(pkg.not_included) || null,
        offer: cell(pkg.offer) || null,
        remarks: cell(pkg.remarks) || null,
        accommodations,
        flights,
        train_tickets: trainTickets,
        transportation_plans: transportationPlans,
        rawdah_tasreehs: rawdahTasreehs,
        officials,
    };
}

interface Props {
    open: boolean;
    onClose: () => void;
}

export function PackageImportDialog({ open, onClose }: Props) {
    const [step, setStep] = useState<'upload' | 'preview'>('upload');
    const [parsedPayload, setParsedPayload] =
        useState<ParsedPackagePayload | null>(null);
    const [parseError, setParseError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { errors } = usePage().props as { errors: Record<string, string> };

    const resetDialog = () => {
        setStep('upload');
        setParsedPayload(null);
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
                const payload = parsePackageImportFile(workbook);
                setParsedPayload(payload);
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
        if (!parsedPayload) return;
        setIsSubmitting(true);
        const payload = parsedPayload as unknown as FormDataConvertible;
        router.post(
            importMethod().url,
            { data: [payload] },
            {
                onFinish: () => setIsSubmitting(false),
                onSuccess: () => handleClose(),
            },
        );
    };

    const importError = errors?.import;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-h-[95%] max-w-[95%] overflow-y-auto md:min-w-4xl">
                {step === 'upload' ? (
                    <>
                        <DialogHeader>
                            <DialogTitle>Import Package</DialogTitle>
                            <DialogDescription>
                                Upload a filled Excel template (.xlsx) to import
                                a package with all its details.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="py-4">
                            <label
                                htmlFor="pkg-import-file"
                                className="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-border p-10 transition-colors hover:border-primary/50 hover:bg-muted/30"
                            >
                                <FileSpreadsheetIcon className="h-10 w-10 text-muted-foreground" />
                                <div className="text-center">
                                    <p className="font-medium">
                                        Click to select an Excel file
                                    </p>
                                    <p className="text-base text-muted-foreground">
                                        .xlsx files only
                                    </p>
                                </div>
                                <input
                                    id="pkg-import-file"
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".xlsx"
                                    className="hidden"
                                    onChange={handleFileChange}
                                />
                            </label>

                            {parseError && (
                                <div className="mt-3 flex items-start gap-2 rounded-md bg-destructive/10 px-3 py-2 text-base text-destructive">
                                    <AlertCircleIcon className="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>{parseError}</span>
                                </div>
                            )}
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={handleClose}>
                                Cancel
                            </Button>
                        </DialogFooter>
                    </>
                ) : (
                    <>
                        <DialogHeader>
                            <DialogTitle>Preview Import Data</DialogTitle>
                            <DialogDescription>
                                Review the parsed data below before importing.
                            </DialogDescription>
                        </DialogHeader>

                        {parsedPayload && (
                            <div className="space-y-4 py-2">
                                {importError && (
                                    <div className="flex items-start gap-2 rounded-md bg-destructive/10 px-3 py-2 text-base text-destructive">
                                        <AlertCircleIcon className="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>{importError}</span>
                                    </div>
                                )}

                                {/* Package main fields */}
                                <div className="rounded-lg border p-4">
                                    <h4 className="mb-3 text-base font-semibold">
                                        Package Details
                                    </h4>
                                    <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-base">
                                        <div>
                                            <span className="text-muted-foreground">
                                                Name:
                                            </span>{' '}
                                            <span className="font-medium">
                                                {parsedPayload.name || (
                                                    <span className="text-destructive">
                                                        (missing)
                                                    </span>
                                                )}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">
                                                Status:
                                            </span>{' '}
                                            {(() => {
                                                const s = parsedPayload.status
                                                    .trim()
                                                    .toLowerCase();
                                                return (
                                                    <Badge
                                                        className={`${packageStatusColors[s] ?? 'bg-gray-100 text-gray-800'} rounded-full px-2 py-0.5`}
                                                    >
                                                        {packageStatusLabels[
                                                            s
                                                        ] ??
                                                            parsedPayload.status}
                                                    </Badge>
                                                );
                                            })()}
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">
                                                Total Seats:
                                            </span>{' '}
                                            {parsedPayload.total_seats}
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">
                                                Departure:
                                            </span>{' '}
                                            {parsedPayload.departure_date ||
                                                '-'}
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">
                                                Return:
                                            </span>{' '}
                                            {parsedPayload.return_date || '-'}
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">
                                                Visa:
                                            </span>{' '}
                                            {parsedPayload.visa_type || '-'}
                                        </div>
                                    </div>
                                </div>

                                {/* Summary badges */}
                                <div className="flex flex-wrap gap-2">
                                    <SummaryBadge
                                        label="Accommodations"
                                        count={
                                            parsedPayload.accommodations.length
                                        }
                                    />
                                    <SummaryBadge
                                        label="Flights"
                                        count={parsedPayload.flights.length}
                                    />
                                    <SummaryBadge
                                        label="Train Tickets"
                                        count={
                                            parsedPayload.train_tickets.length
                                        }
                                    />
                                    <SummaryBadge
                                        label="Transport Plans"
                                        count={
                                            parsedPayload.transportation_plans
                                                .length
                                        }
                                    />
                                    <SummaryBadge
                                        label="Rawdah Tasreehs"
                                        count={
                                            parsedPayload.rawdah_tasreehs.length
                                        }
                                    />
                                    <SummaryBadge
                                        label="Officials"
                                        count={parsedPayload.officials.length}
                                    />
                                </div>

                                {/* Nested data accordion */}
                                <Accordion
                                    type="multiple"
                                    className="w-full"
                                    defaultValue={[]}
                                >
                                    {parsedPayload.accommodations.length >
                                        0 && (
                                        <AccordionItem value="accommodations">
                                            <AccordionTrigger className="text-base">
                                                Accommodations (
                                                {
                                                    parsedPayload.accommodations
                                                        .length
                                                }
                                                )
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <PreviewTable
                                                    headers={[
                                                        'Location',
                                                        'Hotel Name',
                                                        'Meal',
                                                        'Check In',
                                                        'Check Out',
                                                    ]}
                                                    rows={parsedPayload.accommodations.map(
                                                        (a) => [
                                                            a.location,
                                                            a.hotel_name,
                                                            a.type_of_meal ??
                                                                '-',
                                                            a.check_in ?? '-',
                                                            a.check_out ?? '-',
                                                        ],
                                                    )}
                                                />
                                            </AccordionContent>
                                        </AccordionItem>
                                    )}

                                    {parsedPayload.flights.length > 0 && (
                                        <AccordionItem value="flights">
                                            <AccordionTrigger className="text-base">
                                                Flights (
                                                {parsedPayload.flights.length})
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <PreviewTable
                                                    headers={[
                                                        'From',
                                                        'To',
                                                        'Airline',
                                                        'Departure',
                                                        'Arrival',
                                                    ]}
                                                    rows={parsedPayload.flights.map(
                                                        (f) => [
                                                            f.from ?? '-',
                                                            f.to ?? '-',
                                                            f.airline ?? '-',
                                                            f.departure_datetime ??
                                                                '-',
                                                            f.arrival_datetime ??
                                                                '-',
                                                        ],
                                                    )}
                                                />
                                            </AccordionContent>
                                        </AccordionItem>
                                    )}

                                    {parsedPayload.train_tickets.length > 0 && (
                                        <AccordionItem value="train_tickets">
                                            <AccordionTrigger className="text-base">
                                                Train Tickets (
                                                {
                                                    parsedPayload.train_tickets
                                                        .length
                                                }
                                                )
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <PreviewTable
                                                    headers={[
                                                        'From',
                                                        'To',
                                                        'Travel Date',
                                                        'Time',
                                                    ]}
                                                    rows={parsedPayload.train_tickets.map(
                                                        (t) => [
                                                            t.from ?? '-',
                                                            t.to ?? '-',
                                                            t.travel_date ??
                                                                '-',
                                                            t.travel_time ??
                                                                '-',
                                                        ],
                                                    )}
                                                />
                                            </AccordionContent>
                                        </AccordionItem>
                                    )}

                                    {parsedPayload.transportation_plans.length >
                                        0 && (
                                        <AccordionItem value="transportation_plans">
                                            <AccordionTrigger className="text-base">
                                                Transportation Plans (
                                                {
                                                    parsedPayload
                                                        .transportation_plans
                                                        .length
                                                }
                                                )
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <PreviewTable
                                                    headers={[
                                                        'From',
                                                        'To',
                                                        'Travel Date',
                                                        'Time',
                                                    ]}
                                                    rows={parsedPayload.transportation_plans.map(
                                                        (t) => [
                                                            t.from ?? '-',
                                                            t.to ?? '-',
                                                            t.travel_date ??
                                                                '-',
                                                            t.travel_time ??
                                                                '-',
                                                        ],
                                                    )}
                                                />
                                            </AccordionContent>
                                        </AccordionItem>
                                    )}

                                    {parsedPayload.rawdah_tasreehs.length >
                                        0 && (
                                        <AccordionItem value="rawdah_tasreehs">
                                            <AccordionTrigger className="text-base">
                                                Rawdah Tasreehs (
                                                {
                                                    parsedPayload
                                                        .rawdah_tasreehs.length
                                                }
                                                )
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <PreviewTable
                                                    headers={[
                                                        'Date',
                                                        'Women Pax',
                                                        'Women Time',
                                                        'Men Pax',
                                                        'Men Time',
                                                    ]}
                                                    rows={parsedPayload.rawdah_tasreehs.map(
                                                        (r) => [
                                                            r.date ?? '-',
                                                            String(
                                                                r.women_passengers ??
                                                                    '-',
                                                            ),
                                                            r.women_time ?? '-',
                                                            String(
                                                                r.men_passengers ??
                                                                    '-',
                                                            ),
                                                            r.men_time ?? '-',
                                                        ],
                                                    )}
                                                />
                                            </AccordionContent>
                                        </AccordionItem>
                                    )}

                                    {parsedPayload.officials.length > 0 && (
                                        <AccordionItem value="officials">
                                            <AccordionTrigger className="text-base">
                                                Officials (
                                                {parsedPayload.officials.length}
                                                )
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <PreviewTable
                                                    headers={[
                                                        'Type',
                                                        'Name',
                                                        'Passport',
                                                        'Gender',
                                                        'Contact',
                                                    ]}
                                                    rows={parsedPayload.officials.map(
                                                        (o) => {
                                                            const typeLabel =
                                                                officialTypeOptions.find(
                                                                    (opt) =>
                                                                        opt.value ===
                                                                        o.type,
                                                                )?.label ??
                                                                o.type ??
                                                                '-';
                                                            const genderLabel =
                                                                o.gender
                                                                    ? o.gender
                                                                          .charAt(
                                                                              0,
                                                                          )
                                                                          .toUpperCase() +
                                                                      o.gender.slice(
                                                                          1,
                                                                      )
                                                                    : '-';
                                                            return [
                                                                typeLabel,
                                                                o.name ?? '-',
                                                                o.passport_number ??
                                                                    '-',
                                                                genderLabel,
                                                                o.contact_number ??
                                                                    '-',
                                                            ];
                                                        },
                                                    )}
                                                />
                                            </AccordionContent>
                                        </AccordionItem>
                                    )}
                                </Accordion>
                            </div>
                        )}

                        <DialogFooter className="gap-2">
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setStep('upload');
                                    setParsedPayload(null);
                                    if (fileInputRef.current)
                                        fileInputRef.current.value = '';
                                }}
                                disabled={isSubmitting}
                            >
                                <ArrowLeftIcon className="h-4 w-4" />
                                Back
                            </Button>
                            <Button
                                onClick={handleSubmit}
                                disabled={isSubmitting || !parsedPayload}
                            >
                                {isSubmitting ? (
                                    <>Importing...</>
                                ) : (
                                    <>
                                        <CheckCircleIcon className="h-4 w-4" />
                                        Import Package
                                    </>
                                )}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function SummaryBadge({ label, count }: { label: string; count: number }) {
    return (
        <div className="flex items-center gap-1 rounded-full border bg-muted/40 px-3 py-1 text-sm">
            <span className="font-semibold">{count}</span>
            <span className="text-muted-foreground">{label}</span>
        </div>
    );
}

function PreviewTable({
    headers,
    rows,
}: {
    headers: string[];
    rows: string[][];
}) {
    return (
        <div className="overflow-x-auto rounded border text-sm">
            <table className="w-full">
                <thead className="bg-muted/50">
                    <tr>
                        {headers.map((h) => (
                            <th
                                key={h}
                                className="px-3 py-2 text-left font-medium text-muted-foreground"
                            >
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, i) => (
                        <tr key={i} className="border-t">
                            {row.map((cell, j) => (
                                <td
                                    key={j}
                                    className="px-3 py-2 text-foreground"
                                >
                                    {cell || (
                                        <span className="text-muted-foreground">
                                            -
                                        </span>
                                    )}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export function generatePackageImportTemplate(): void {
    const wb = utils.book_new();

    const addSheet = (
        name: string,
        headers: string[],
        exampleRow: (string | number)[],
    ) => {
        const ws = utils.aoa_to_sheet([
            headers,
            ['(Example - do not modify this row)', ...exampleRow.slice(1)],
        ]);

        // Bold headers
        headers.forEach((_, colIndex) => {
            const cellRef = utils.encode_cell({ r: 0, c: colIndex });
            if (!ws[cellRef]) ws[cellRef] = { v: headers[colIndex], t: 's' };
            ws[cellRef].s = { font: { bold: true } };
        });

        // Auto-fit columns based on header name and example value
        ws['!cols'] = headers.map((h, i) => ({
            wch: Math.max(
                h.length + 2,
                String(exampleRow[i] ?? '').length + 2,
                14,
            ),
        }));

        utils.book_append_sheet(wb, ws, name);
    };

    addSheet(
        'Package',
        [
            'name',
            'status',
            'total_seats',
            'departure_date',
            'return_date',
            'price_single',
            'price_double',
            'price_triple',
            'price_quad',
            'child_with_bed_price',
            'child_no_bed_price',
            'infant_price',
            'visa_type',
            'vehicle_type',
            'vehicle_driver_name',
            'vehicle_driver_contact_number',
            'ticket_type',
            'train_description',
            'included',
            'not_included',
            'offer',
            'remarks',
        ],
        [
            'Umrah Package 2025',
            'open',
            40,
            '01 January 2025',
            '15 January 2025',
            5000,
            4500,
            4200,
            4000,
            3000,
            2500,
            1000,
            'Umrah Visa',
            'Bus',
            'Ahmad bin Ali',
            '0123456789',
            'two_way',
            '',
            'Airfare, Hotel, Visa',
            'Personal Expenses',
            'Early Bird Discount',
            '',
        ],
    );

    addSheet(
        'Accommodations',
        [
            'location',
            'hotel_name',
            'type_of_meal',
            'first_meal',
            'last_meal',
            'check_in',
            'check_out',
            'ic',
            'ic_contact_number',
            'remarks',
        ],
        [
            'Makkah',
            'Al Safwah Royale Orchid',
            'Full Board',
            'Dinner',
            'Breakfast',
            '01 January 2025',
            '08 January 2025',
            'Ahmad',
            '0123456789',
            '',
        ],
    );

    addSheet(
        'Flights',
        [
            'from',
            'to',
            'description',
            'airline',
            'pnr',
            'departure_datetime',
            'arrival_datetime',
            'remarks',
        ],
        [
            'KUL',
            'JED',
            'Departure',
            'Malaysia Airlines',
            'ABC123',
            '01 January 2025 08:00',
            '01 January 2025 12:00',
            '',
        ],
    );

    addSheet(
        'Train_Tickets',
        ['from', 'to', 'travel_date', 'travel_time', 'remarks'],
        ['Madinah', 'Makkah', '05 January 2025', '09:00', ''],
    );

    addSheet(
        'Transportation_Plans',
        ['from', 'to', 'travel_date', 'travel_time', 'remarks'],
        ['Hotel', 'Masjid al-Haram', '02 January 2025', '05:00', ''],
    );

    addSheet(
        'Rawdah_Tasreehs',
        [
            'date',
            'women_passengers',
            'women_time',
            'men_passengers',
            'men_time',
            'remarks',
        ],
        ['05 January 2025', 20, '08:00', 20, '10:00', ''],
    );

    addSheet(
        'Officials',
        [
            'type',
            'name',
            'contact_number',
            'nationality',
            'passport_number',
            'gender',
            'date_of_birth',
            'place_of_birth',
            'passport_issue_date',
            'passport_expiry_date',
            'passport_place_of_issue',
        ],
        [
            'mutawif',
            'Ahmad bin Ali',
            '0123456789',
            'Malaysian',
            'A12345678',
            'male',
            '01 January 1980',
            'Singapore',
            '01 January 2020',
            '01 January 2030',
            'Singapore',
        ],
    );

    // Instructions sheet — 3-column table: Column | Required? | Valid Values / Notes
    type InstrRow = [string, string, string];
    const _ = ''; // empty cell

    const instructionsData: InstrRow[] = [
        ['PACKAGE IMPORT TEMPLATE — FIELD REFERENCE & INSTRUCTIONS', _, _],
        [_, _, _],
        ['HOW TO USE', _, _],
        [
            '1.  Open the "Package" sheet. Fill in row 3 onward. Row 2 is an example — keep it or delete it.',
            _,
            _,
        ],
        [
            '2.  Fill each related sheet (Accommodations, Flights, etc.) — one record per row.',
            _,
            _,
        ],
        [
            '3.  Related sheets are optional. Leave them blank if the package has no flights, officials, etc.',
            _,
            _,
        ],
        [
            '4.  Leave optional fields blank — do NOT delete any column headers.',
            _,
            _,
        ],
        [
            '5.  Save as .xlsx and upload via the Import button in the application.',
            _,
            _,
        ],
        [_, _, _],
        ['PACKAGE SHEET', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],
        ['name', 'REQUIRED', 'Any text. e.g.  Umrah Package Jan 2025'],
        ['status', 'REQUIRED', 'open | full | closed | ongoing | completed'],
        ['total_seats', 'REQUIRED', 'Whole number ≥ 1. e.g.  40'],
        [
            'departure_date',
            'optional',
            'Date. e.g.  01 January 2025  or  2025-01-01',
        ],
        ['return_date', 'optional', 'Date. e.g.  15 January 2025'],
        [
            'price_single',
            'optional',
            'Number ≥ 0. Per-pax price for single room. e.g.  5000',
        ],
        [
            'price_double',
            'optional',
            'Number ≥ 0. Per-pax price for double room.',
        ],
        [
            'price_triple',
            'optional',
            'Number ≥ 0. Per-pax price for triple room.',
        ],
        ['price_quad', 'optional', 'Number ≥ 0. Per-pax price for quad room.'],
        [
            'child_with_bed_price',
            'optional',
            'Number ≥ 0. Child (7-11 years) with bed price.',
        ],
        [
            'child_no_bed_price',
            'optional',
            'Number ≥ 0. Child (2-6 years) without bed price.',
        ],
        ['infant_price', 'optional', 'Number ≥ 0. Infant (0-2 years) price.'],
        [
            'visa_type',
            'optional',
            'Not Applicable | Tourist Visa | Umrah Visa  (or any free text)',
        ],
        ['vehicle_type', 'optional', 'Any text. e.g.  Bus | Van | Coaster'],
        ['vehicle_driver_name', 'optional', "Driver's full name."],
        ['vehicle_driver_contact_number', 'optional', "Driver's phone number."],
        ['ticket_type', 'optional', 'one_way | two_way'],
        [
            'train_description',
            'optional',
            'Any text description for the train arrangement.',
        ],
        [
            'included',
            'optional',
            'What is included. Free text; one item per line is recommended.',
        ],
        ['not_included', 'optional', 'What is not included. Free text.'],
        ['offer', 'optional', 'Special offers or promotions. Free text.'],
        ['remarks', 'optional', 'General remarks. Free text.'],
        [_, _, _],
        ['ACCOMMODATIONS SHEET', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],
        ['location', 'REQUIRED', 'e.g.  Makkah | Madinah'],
        [
            'hotel_name',
            'REQUIRED',
            'Full hotel name. e.g.  Al Safwah Royale Orchid',
        ],
        [
            'type_of_meal',
            'optional',
            'Breakfast Only | Half Board | Full Board (3 Meals)',
        ],
        [
            'first_meal',
            'optional',
            'Breakfast | Lunch | Dinner  (first meal served at check-in)',
        ],
        [
            'last_meal',
            'optional',
            'Breakfast | Lunch | Dinner  (last meal served before check-out)',
        ],
        ['check_in', 'optional', 'Date. e.g.  01 January 2025'],
        ['check_out', 'optional', 'Date. e.g.  08 January 2025'],
        ['ic', 'optional', "In-charge person's name."],
        ['ic_contact_number', 'optional', "In-charge person's phone number."],
        ['remarks', 'optional', 'Any notes.'],
        [_, _, _],
        ['FLIGHTS SHEET', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],
        ['from', 'optional', 'Departure airport or city code. e.g.  KUL'],
        ['to', 'optional', 'Arrival airport or city code. e.g.  JED'],
        ['description', 'optional', 'e.g.  Departure | Return'],
        ['airline', 'optional', 'e.g.  Malaysia Airlines | AirAsia'],
        ['pnr', 'optional', 'Booking reference code. e.g.  ABC123'],
        [
            'departure_datetime',
            'optional',
            'Date + time. e.g.  01 January 2025 08:00',
        ],
        [
            'arrival_datetime',
            'optional',
            'Date + time. e.g.  01 January 2025 12:00',
        ],
        ['remarks', 'optional', 'Any notes.'],
        [_, _, _],
        ['TRAIN_TICKETS SHEET', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],
        ['from', 'optional', 'Departure station. e.g.  Madinah'],
        ['to', 'optional', 'Arrival station. e.g.  Makkah'],
        ['travel_date', 'optional', 'Date. e.g.  05 January 2025'],
        ['travel_time', 'optional', 'Time in HH:MM. e.g.  09:00'],
        ['remarks', 'optional', 'Any notes.'],
        [_, _, _],
        ['TRANSPORTATION_PLANS SHEET', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],
        ['from', 'optional', 'Departure point. e.g.  Hotel'],
        ['to', 'optional', 'Destination. e.g.  Masjid al-Haram'],
        ['travel_date', 'optional', 'Date. e.g.  02 January 2025'],
        ['travel_time', 'optional', 'Time in HH:MM. e.g.  05:00'],
        ['remarks', 'optional', 'Any notes.'],
        [_, _, _],
        ['RAWDAH_TASREEHS SHEET', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],
        ['date', 'optional', 'Date. e.g.  05 January 2025'],
        [
            'women_passengers',
            'optional',
            'Whole number ≥ 0. Number of women pilgrims.',
        ],
        ['women_time', 'optional', 'Time in HH:MM. e.g.  08:00'],
        [
            'men_passengers',
            'optional',
            'Whole number ≥ 0. Number of men pilgrims.',
        ],
        ['men_time', 'optional', 'Time in HH:MM. e.g.  10:00'],
        ['remarks', 'optional', 'Any notes.'],
        [_, _, _],
        ['OFFICIALS SHEET', _, _],
        ['Column', 'Required?', 'Valid Values / Notes'],
        ['type', 'optional', 'mutawif | mutawifah | official'],
        ['name', 'optional', 'Full name. e.g.  Ahmad bin Ali'],
        ['contact_number', 'optional', 'Phone number. e.g.  0123456789'],
        ['nationality', 'optional', 'e.g.  Malaysian | Indonesian'],
        ['passport_number', 'optional', 'e.g.  A12345678'],
        ['gender', 'optional', 'male | female'],
        ['date_of_birth', 'optional', 'Date. e.g.  01 January 1980'],
        ['place_of_birth', 'optional', 'e.g.  Singapore'],
        ['passport_issue_date', 'optional', 'Date. e.g.  01 January 2020'],
        ['passport_expiry_date', 'optional', 'Date. e.g.  01 January 2030'],
        ['passport_place_of_issue', 'optional', 'e.g.  Singapore'],
        [_, _, _],
        ['COMMON IMPORT ERRORS', _, _],
        [
            '"name is required"',
            _,
            'Fill the name field in the Package sheet (row 3 or later).',
        ],
        [
            '"status is invalid"',
            _,
            'Use exactly one of: open | full | closed | ongoing | completed (all lowercase).',
        ],
        [
            '"total_seats must be at least 1"',
            _,
            'Enter a whole positive number, e.g. 40.',
        ],
        [
            '"...is not a valid date"',
            _,
            'Use a readable date format: 01 January 2025  or  2025-01-01.',
        ],
        [
            '"No package data found"',
            _,
            'Package sheet row 3 is empty — add your data row.',
        ],
        [
            '"Missing Package sheet"',
            _,
            'Do not rename or delete the "Package" tab.',
        ],
    ];

    // Section header rows (bold col A only) and column-header rows (bold A+B+C)
    const boldColA = new Set([0, 2, 9, 34, 47, 58, 65, 72, 79, 86, 97]);
    const boldAllCols = new Set([10, 35, 48, 59, 66, 73, 80, 87]);

    const instructionsWs = utils.aoa_to_sheet(instructionsData);

    boldColA.forEach((r) => {
        const ref = utils.encode_cell({ r, c: 0 });
        if (instructionsWs[ref])
            instructionsWs[ref].s = { font: { bold: true } };
    });
    boldAllCols.forEach((r) => {
        [0, 1, 2].forEach((c) => {
            const ref = utils.encode_cell({ r, c });
            if (instructionsWs[ref])
                instructionsWs[ref].s = { font: { bold: true } };
        });
    });

    // Fixed widths: A = field name col, B = required col, C = description col
    instructionsWs['!cols'] = [{ wch: 35 }, { wch: 12 }, { wch: 72 }];

    utils.book_append_sheet(wb, instructionsWs, 'Instructions');

    writeFile(wb, 'package-import-template.xlsx');
}
