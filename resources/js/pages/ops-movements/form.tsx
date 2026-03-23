import { DatePickerField } from '@/components/date-picker';
import { DocumentField } from '@/components/document-field';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useForm } from '@inertiajs/react';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    type OpsAccommodationSchema,
    type OpsBudgetItemSchema,
    type OpsBudgetTitleSchema,
    type OpsDocumentItemSchema,
    type OpsFlightSchema,
    type OpsMovementSchema,
    type OpsOfficialSchema,
} from './schema';
import { opsMovementValidationSchema } from './validation';

interface OpsMovementFormProps {
    initialData: OpsMovementSchema;
    onCancel: () => void;
}

type OpsDocumentTabKey = 'itinerary' | 'booklet';

const OPS_DOCUMENT_TABS: Array<{
    key: OpsDocumentTabKey;
    label: string;
    hint: string;
    accept: string;
}> = [
    {
        key: 'itinerary',
        label: 'Itinerary',
        hint: 'Upload itinerary files for this ops movement.',
        accept: '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv',
    },
    {
        key: 'booklet',
        label: 'Booklet',
        hint: 'Upload booklet files for this ops movement.',
        accept: '.pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv',
    },
];

function CopyableText({
    value,
}: {
    value: string | number | null | undefined;
}) {
    return (
        <Input
            value={
                value === null || value === undefined || value === ''
                    ? '-'
                    : String(value)
            }
            readOnly
            onFocus={(event) => event.currentTarget.select()}
            className="bg-muted/40"
        />
    );
}

function createEmptyDocumentEntry(): OpsDocumentItemSchema {
    return {
        file: null,
        file_name: null,
        file_path: null,
        removed: false,
    };
}

function removeDocumentEntryAtIndex(
    rows: OpsDocumentItemSchema[],
    index: number,
): OpsDocumentItemSchema[] {
    if (index < 0 || index >= rows.length) {
        return rows;
    }

    const nextRows = [...rows];
    const currentRow = nextRows[index];

    if (!currentRow) {
        return nextRows;
    }

    if (currentRow.id || currentRow.file_path) {
        nextRows[index] = {
            ...currentRow,
            file: null,
            file_name: null,
            file_path: null,
            removed: true,
        };

        return nextRows;
    }

    nextRows.splice(index, 1);

    return nextRows;
}

function buildOpsDocumentFileName(
    fieldLabel: string,
    iteration: number,
    packageNumber?: string | null,
): string {
    const normalizedPackageNumber = String(packageNumber ?? '').trim();
    const safePackageNumber =
        normalizedPackageNumber.length > 0 ? normalizedPackageNumber : 'Draft';

    return `Ops Movement ${fieldLabel} #${iteration} - ${safePackageNumber}`;
}

function normalizeDocumentEntriesForSubmit(
    entries: OpsDocumentItemSchema[] | undefined,
): OpsDocumentItemSchema[] {
    if (!Array.isArray(entries)) {
        return [];
    }

    return entries
        .map((entry) => ({
            id: entry.id,
            file: entry.file ?? null,
            file_name:
                typeof entry.file_name === 'string' &&
                entry.file_name.trim().length > 0
                    ? entry.file_name.trim()
                    : null,
            file_path:
                typeof entry.file_path === 'string' &&
                entry.file_path.trim().length > 0
                    ? entry.file_path.trim()
                    : null,
            removed: Boolean(entry.removed),
        }))
        .filter((entry) => {
            return (
                entry.removed ||
                entry.id ||
                entry.file ||
                (entry.file_path && entry.file_path.length > 0)
            );
        });
}

function createEmptyBudgetItem(): OpsBudgetItemSchema {
    return {
        item_name: '',
        unit_price: 0,
        quantity: 1,
        remarks: '',
    };
}

function createEmptyBudgetTitle(index: number): OpsBudgetTitleSchema {
    return {
        title: `Title ${index + 1}`,
        sort_order: index + 1,
        items: [createEmptyBudgetItem()],
    };
}

function toDecimal(value: unknown): number {
    const parsed = Number(value ?? 0);

    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return parsed;
}

function formatSar(value: number): string {
    return `SAR${new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value)}`;
}

export default function OpsMovementForm({
    initialData,
    onCancel,
}: OpsMovementFormProps) {
    const [activeTab, setActiveTab] = useState('ops-movement');

    const form = useForm<OpsMovementSchema>({
        ...initialData,
        documents: {
            itinerary:
                initialData.documents?.itinerary?.length &&
                Array.isArray(initialData.documents.itinerary)
                    ? initialData.documents.itinerary
                    : [createEmptyDocumentEntry()],
            booklet:
                initialData.documents?.booklet?.length &&
                Array.isArray(initialData.documents.booklet)
                    ? initialData.documents.booklet
                    : [createEmptyDocumentEntry()],
        },
        budget:
            Array.isArray(initialData.budget) && initialData.budget.length > 0
                ? initialData.budget
                : [createEmptyBudgetTitle(0)],
    });
    const { data, setData, processing, put, transform } = form;
    const errors =
        (form as unknown as { errors?: Record<string, string> }).errors ?? {};
    const clearFormErrors = (): void => {
        (form as unknown as { clearErrors: () => void }).clearErrors();
    };
    const setFormError = (field: string, message: string): void => {
        (
            form as unknown as {
                setError: (field: string, message: string) => void;
            }
        ).setError(field, message);
    };
    const setFormErrors = (validationErrors: Record<string, string>): void => {
        (
            form as unknown as {
                setError: (errors: Record<string, string>) => void;
            }
        ).setError(validationErrors);
    };
    const setFormData = (key: string, value: unknown): void => {
        (setData as unknown as (field: string, fieldValue: unknown) => void)(
            key,
            value,
        );
    };

    const editablePayload = useMemo(() => {
        return {
            ops_base: data.ops_base ?? null,
            infotech_ref: data.infotech_ref ?? null,
            vehicle_type: data.vehicle_type ?? null,
            vehicle_driver_name: data.vehicle_driver_name ?? null,
            vehicle_driver_contact_number:
                data.vehicle_driver_contact_number ?? null,
            train_description: data.train_description ?? null,
            accommodations: (data.accommodations ?? []).map(
                (accommodation) => ({
                    id: accommodation.id,
                    ic: accommodation.ic ?? null,
                }),
            ),
            officials: (data.officials ?? []).map((official) => ({
                id: official.id,
                hotel: official.hotel ?? null,
            })),
            flights: (data.flights ?? []).map((flight) => ({
                id: flight.id,
                doa_by: flight.doa_by ?? null,
                doa_datetime: flight.doa_datetime ?? null,
                ic: flight.ic ?? null,
            })),
            documents: {
                itinerary: normalizeDocumentEntriesForSubmit(
                    data.documents?.itinerary,
                ),
                booklet: normalizeDocumentEntriesForSubmit(
                    data.documents?.booklet,
                ),
            },
            budget: (data.budget ?? []).map((section, sectionIndex) => ({
                title: section.title ?? null,
                sort_order: sectionIndex + 1,
                items: (section.items ?? []).map((item, itemIndex) => ({
                    item_name: item.item_name ?? null,
                    unit_price: toDecimal(item.unit_price),
                    quantity: toDecimal(item.quantity),
                    remarks: item.remarks ?? null,
                    sort_order: itemIndex + 1,
                })),
            })),
        };
    }, [data]);

    const getError = (fieldName: string): string | undefined => {
        return (errors as Record<string, string>)[fieldName];
    };

    const updateAccommodation = (
        index: number,
        field: keyof OpsAccommodationSchema,
        value: string | null,
    ) => {
        const nextRows = [...(data.accommodations ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            [field]: value,
        };
        setFormData('accommodations', nextRows);
    };

    const updateOfficial = (
        index: number,
        field: keyof OpsOfficialSchema,
        value: string | null,
    ) => {
        const nextRows = [...(data.officials ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            [field]: value,
        };
        setFormData('officials', nextRows);
    };

    const updateFlight = (
        index: number,
        field: keyof OpsFlightSchema,
        value: string | null,
    ) => {
        const nextRows = [...(data.flights ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            [field]: value,
        };
        setFormData('flights', nextRows);
    };

    const updateDocumentRows = (
        field: OpsDocumentTabKey,
        rows: OpsDocumentItemSchema[],
    ) => {
        setFormData('documents', {
            ...(data.documents ?? {
                itinerary: [createEmptyDocumentEntry()],
                booklet: [createEmptyDocumentEntry()],
            }),
            [field]: rows,
        });
    };

    const updateBudgetTitle = (
        titleIndex: number,
        patch: Partial<OpsBudgetTitleSchema>,
    ) => {
        const nextBudget = [...(data.budget ?? [])];
        const target = nextBudget[titleIndex];

        if (!target) {
            return;
        }

        nextBudget[titleIndex] = {
            ...target,
            ...patch,
        };
        setFormData('budget', nextBudget);
    };

    const updateBudgetItem = (
        titleIndex: number,
        itemIndex: number,
        patch: Partial<OpsBudgetItemSchema>,
    ) => {
        const nextBudget = [...(data.budget ?? [])];
        const title = nextBudget[titleIndex];

        if (!title) {
            return;
        }

        const nextItems = [...(title.items ?? [])];
        const item = nextItems[itemIndex];

        if (!item) {
            return;
        }

        nextItems[itemIndex] = {
            ...item,
            ...patch,
        };

        nextBudget[titleIndex] = {
            ...title,
            items: nextItems,
        };

        setFormData('budget', nextBudget);
    };

    const addBudgetTitle = () => {
        const current = [...(data.budget ?? [])];
        current.push(createEmptyBudgetTitle(current.length));
        setFormData('budget', current);
    };

    const removeBudgetTitle = (titleIndex: number) => {
        const current = [...(data.budget ?? [])];
        current.splice(titleIndex, 1);

        setFormData(
            'budget',
            current.length > 0 ? current : [createEmptyBudgetTitle(0)],
        );
    };

    const addBudgetItem = (titleIndex: number) => {
        const current = [...(data.budget ?? [])];
        const title = current[titleIndex];

        if (!title) {
            return;
        }

        current[titleIndex] = {
            ...title,
            items: [...(title.items ?? []), createEmptyBudgetItem()],
        };

        setFormData('budget', current);
    };

    const removeBudgetItem = (titleIndex: number, itemIndex: number) => {
        const current = [...(data.budget ?? [])];
        const title = current[titleIndex];

        if (!title) {
            return;
        }

        const nextItems = [...(title.items ?? [])];
        nextItems.splice(itemIndex, 1);

        current[titleIndex] = {
            ...title,
            items: nextItems.length > 0 ? nextItems : [createEmptyBudgetItem()],
        };

        setFormData('budget', current);
    };

    const budgetTotals = useMemo(() => {
        const sections = (data.budget ?? []).map((section) => {
            const sectionTotal = (section.items ?? []).reduce((sum, item) => {
                return (
                    sum + toDecimal(item.unit_price) * toDecimal(item.quantity)
                );
            }, 0);

            return {
                section,
                total: sectionTotal,
            };
        });

        const grandTotal = sections.reduce(
            (sum, section) => sum + section.total,
            0,
        );

        return {
            sections,
            grandTotal,
        };
    }, [data.budget]);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        clearFormErrors();

        const parseResult = opsMovementValidationSchema.safeParse(data);

        if (!parseResult.success) {
            parseResult.error.issues.forEach((issue) => {
                const key = issue.path.join('.');
                if (typeof key === 'string') {
                    setFormError(key, issue.message);
                }
            });

            return;
        }

        transform(() => editablePayload as unknown as OpsMovementSchema);

        put(`/ops-movements/${data.id}`, {
            preserveScroll: true,
            forceFormData: true,
            onError: (validationErrors) => {
                setFormErrors(validationErrors as Record<string, string>);
            },
            onFinish: () => {
                transform((currentData: OpsMovementSchema) => currentData);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <Tabs
                value={activeTab}
                onValueChange={setActiveTab}
                className="gap-3"
            >
                <ScrollArea className="w-full whitespace-nowrap">
                    <TabsList className="w-fit group-data-[orientation=horizontal]/tabs:h-11">
                        <TabsTrigger value="ops-movement" className="text-lg">
                            Ops Movement
                        </TabsTrigger>
                        <TabsTrigger value="itinerary" className="text-lg">
                            Itinerary
                        </TabsTrigger>
                        <TabsTrigger value="booklet" className="text-lg">
                            Booklet
                        </TabsTrigger>
                        <TabsTrigger value="budget" className="text-lg">
                            Budget
                        </TabsTrigger>
                    </TabsList>
                    <ScrollBar orientation="horizontal" />
                </ScrollArea>

                <TabsContent value="ops-movement" className="space-y-6">
                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Ops Movement Info
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Travel Date Range">
                                <CopyableText
                                    value={data.departure_return_range}
                                />
                            </FormField>
                            <FormField label="First Hotel">
                                <CopyableText value={data.first_hotel_name} />
                            </FormField>
                            <FormField label="Visa Type">
                                <CopyableText value={data.visa_type} />
                            </FormField>
                            <FormField label="Ops Base" error={errors.ops_base}>
                                <ProperInput
                                    value={data.ops_base ?? ''}
                                    disabled={processing}
                                    onCommit={(value) =>
                                        setFormData('ops_base', value)
                                    }
                                    placeholder="Enter ops base"
                                />
                            </FormField>
                            <FormField label="Package Number">
                                <CopyableText value={data.package_number} />
                            </FormField>
                            <FormField label="Manifest Number">
                                <CopyableText value={data.manifest_number} />
                            </FormField>
                            <FormField
                                label="Infotech Ref"
                                error={errors.infotech_ref}
                                className="md:col-span-2"
                            >
                                <ProperInput
                                    value={data.infotech_ref ?? ''}
                                    disabled={processing}
                                    onCommit={(value) =>
                                        setFormData('infotech_ref', value)
                                    }
                                    placeholder="Enter infotech reference"
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Pax / Passengers
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Adult (Non-Official)">{`${data.passengers?.adult_total ?? 0} (${data.passengers?.adult_male ?? 0} male / ${data.passengers?.adult_female ?? 0} female)`}</FormField>
                            <FormField label="Child (Non-Official)">{`${data.passengers?.child_total ?? 0} (${data.passengers?.child_boy ?? 0} boy / ${data.passengers?.child_girl ?? 0} girl)`}</FormField>
                            <FormField label="Official Total">
                                {String(data.passengers?.official_total ?? 0)}
                            </FormField>
                            <FormField label="Wheelchair (Non-Official)">
                                {String(
                                    data.passengers
                                        ?.wheelchair_non_official_total ?? 0,
                                )}
                            </FormField>
                            <FormField label="Grand Total">
                                {String(data.passengers?.grand_total ?? 0)}
                            </FormField>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">Hotels</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(data.accommodations ?? []).map(
                                (accommodation, index) => (
                                    <div
                                        key={accommodation.id}
                                        className="grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-6"
                                    >
                                        <FormField label="Location">
                                            <CopyableText
                                                value={accommodation.location}
                                            />
                                        </FormField>
                                        <FormField label="Hotel Name">
                                            <CopyableText
                                                value={accommodation.hotel_name}
                                            />
                                        </FormField>
                                        <FormField
                                            label="IC"
                                            error={getError(
                                                `accommodations.${index}.ic`,
                                            )}
                                        >
                                            <ProperInput
                                                value={accommodation.ic ?? ''}
                                                disabled={processing}
                                                onCommit={(value) =>
                                                    updateAccommodation(
                                                        index,
                                                        'ic',
                                                        value,
                                                    )
                                                }
                                                placeholder="Enter IC"
                                            />
                                        </FormField>
                                        <FormField label="Check In">
                                            <CopyableText
                                                value={accommodation.check_in}
                                            />
                                        </FormField>
                                        <FormField label="Check Out">
                                            <CopyableText
                                                value={accommodation.check_out}
                                            />
                                        </FormField>
                                        <FormField label="Meal">
                                            <CopyableText
                                                value={
                                                    accommodation.type_of_meal
                                                }
                                            />
                                        </FormField>
                                    </div>
                                ),
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">Officials</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(data.officials ?? []).map((official, index) => (
                                <div
                                    key={official.id}
                                    className="grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-2"
                                >
                                    <FormField label="Official Name">
                                        <CopyableText value={official.name} />
                                    </FormField>
                                    <FormField
                                        label="Hotel"
                                        error={getError(
                                            `officials.${index}.hotel`,
                                        )}
                                    >
                                        <ProperInput
                                            value={official.hotel ?? ''}
                                            disabled={processing}
                                            onCommit={(value) =>
                                                updateOfficial(
                                                    index,
                                                    'hotel',
                                                    value,
                                                )
                                            }
                                            placeholder="Enter hotel"
                                        />
                                    </FormField>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Flight Ticket
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(data.flights ?? []).map((flight, index) => (
                                <div
                                    key={flight.id}
                                    className="space-y-4 rounded-lg border p-4"
                                >
                                    <div className="text-lg font-semibold text-muted-foreground">
                                        {flight.description ||
                                            `Flight ${index + 1}`}
                                    </div>
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                        <FormField label="From">
                                            <CopyableText value={flight.from} />
                                        </FormField>
                                        <FormField label="Departure Datetime">
                                            <CopyableText
                                                value={
                                                    flight.departure_datetime
                                                }
                                            />
                                        </FormField>
                                        <FormField label="Airline">
                                            <CopyableText
                                                value={flight.airline}
                                            />
                                        </FormField>
                                        <FormField label="Flight No">
                                            <CopyableText value={flight.pnr} />
                                        </FormField>
                                        <FormField
                                            label="DOA By"
                                            error={getError(
                                                `flights.${index}.doa_by`,
                                            )}
                                        >
                                            <ProperInput
                                                value={flight.doa_by ?? ''}
                                                disabled={processing}
                                                onCommit={(value) =>
                                                    updateFlight(
                                                        index,
                                                        'doa_by',
                                                        value,
                                                    )
                                                }
                                                placeholder="Enter DOA by"
                                            />
                                        </FormField>
                                        <FormField
                                            label="DOA Datetime"
                                            error={getError(
                                                `flights.${index}.doa_datetime`,
                                            )}
                                        >
                                            <DatePickerField
                                                id={`doa_datetime_${index}`}
                                                value={
                                                    flight.doa_datetime || ''
                                                }
                                                fromYear={
                                                    new Date().getFullYear() - 2
                                                }
                                                toYear={
                                                    new Date().getFullYear() + 5
                                                }
                                                disabled={processing}
                                                useTime
                                                onChange={(value) =>
                                                    updateFlight(
                                                        index,
                                                        'doa_datetime',
                                                        value || null,
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            label="IC"
                                            error={getError(
                                                `flights.${index}.ic`,
                                            )}
                                        >
                                            <ProperInput
                                                value={flight.ic ?? ''}
                                                disabled={processing}
                                                onCommit={(value) =>
                                                    updateFlight(
                                                        index,
                                                        'ic',
                                                        value,
                                                    )
                                                }
                                                placeholder="Enter IC"
                                            />
                                        </FormField>
                                        <FormField label="To">
                                            <CopyableText value={flight.to} />
                                        </FormField>
                                        <FormField label="Arrival Datetime">
                                            <CopyableText
                                                value={flight.arrival_datetime}
                                            />
                                        </FormField>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Bus / Vehicle
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField
                                label="Vehicle Type"
                                error={errors.vehicle_type}
                            >
                                <ProperInput
                                    value={data.vehicle_type ?? ''}
                                    disabled={processing}
                                    onCommit={(value) =>
                                        setFormData('vehicle_type', value)
                                    }
                                    placeholder="Enter vehicle type"
                                />
                            </FormField>
                            <FormField
                                label="Driver Name"
                                error={errors.vehicle_driver_name}
                            >
                                <ProperInput
                                    value={data.vehicle_driver_name ?? ''}
                                    disabled={processing}
                                    onCommit={(value) =>
                                        setFormData(
                                            'vehicle_driver_name',
                                            value,
                                        )
                                    }
                                    placeholder="Enter driver name"
                                />
                            </FormField>
                            <FormField
                                label="Driver Contact Number"
                                error={errors.vehicle_driver_contact_number}
                            >
                                <ProperInput
                                    value={
                                        data.vehicle_driver_contact_number ?? ''
                                    }
                                    disabled={processing}
                                    onCommit={(value) =>
                                        setFormData(
                                            'vehicle_driver_contact_number',
                                            value,
                                        )
                                    }
                                    placeholder="Enter driver contact"
                                />
                            </FormField>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">Train</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <FormField
                                label="Train Description"
                                error={errors.train_description}
                            >
                                <ProperInput
                                    value={data.train_description ?? ''}
                                    disabled={processing}
                                    textarea
                                    onCommit={(value) =>
                                        setFormData('train_description', value)
                                    }
                                    placeholder="Enter train description"
                                />
                            </FormField>
                            {(data.train_tickets ?? []).map((ticket) => (
                                <div
                                    key={ticket.id}
                                    className="grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-4"
                                >
                                    <FormField label="From">
                                        <CopyableText value={ticket.from} />
                                    </FormField>
                                    <FormField label="To">
                                        <CopyableText value={ticket.to} />
                                    </FormField>
                                    <FormField label="Date">
                                        <CopyableText
                                            value={ticket.travel_date}
                                        />
                                    </FormField>
                                    <FormField label="Time">
                                        <CopyableText
                                            value={ticket.travel_time}
                                        />
                                    </FormField>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </TabsContent>

                {OPS_DOCUMENT_TABS.map((tab) => {
                    const allRows =
                        (data.documents?.[tab.key] as
                            | OpsDocumentItemSchema[]
                            | undefined) ?? [];
                    const visibleRowIndexes = allRows
                        .map((row, rowIndex) => (row.removed ? null : rowIndex))
                        .filter(
                            (rowIndex): rowIndex is number => rowIndex !== null,
                        );
                    const rowsToRender =
                        visibleRowIndexes.length > 0
                            ? visibleRowIndexes.map(
                                  (actualIndex, visibleIndex) => ({
                                      row: allRows[actualIndex],
                                      actualIndex,
                                      visibleIndex,
                                  }),
                              )
                            : [
                                  {
                                      row: createEmptyDocumentEntry(),
                                      actualIndex: -1,
                                      visibleIndex: 0,
                                  },
                              ];

                    return (
                        <TabsContent
                            key={tab.key}
                            value={tab.key}
                            className="space-y-4"
                        >
                            <div className="rounded-xl border border-border/70 p-4">
                                <div className="mb-4 flex items-center justify-between">
                                    <div>
                                        <h3 className="text-lg font-semibold">
                                            {tab.label} Documents
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {tab.hint}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => {
                                            updateDocumentRows(tab.key, [
                                                ...allRows,
                                                createEmptyDocumentEntry(),
                                            ]);
                                        }}
                                    >
                                        Add Document
                                    </Button>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                    {rowsToRender.map((renderRow) => {
                                        const {
                                            row,
                                            actualIndex,
                                            visibleIndex,
                                        } = renderRow;

                                        return (
                                            <div
                                                key={`${tab.key}-${row.id ?? `new-${visibleIndex}`}`}
                                                className="rounded-lg border p-3"
                                            >
                                                {actualIndex >= 0 && (
                                                    <div className="mb-3 flex justify-end">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 px-2 text-destructive hover:text-destructive"
                                                            disabled={
                                                                processing
                                                            }
                                                            onClick={() => {
                                                                updateDocumentRows(
                                                                    tab.key,
                                                                    removeDocumentEntryAtIndex(
                                                                        allRows,
                                                                        actualIndex,
                                                                    ),
                                                                );
                                                            }}
                                                        >
                                                            Remove
                                                        </Button>
                                                    </div>
                                                )}

                                                <DocumentField
                                                    label={`${tab.label} #${visibleIndex + 1}`}
                                                    hint={tab.hint}
                                                    accept={tab.accept}
                                                    fileValue={
                                                        row.file ?? undefined
                                                    }
                                                    existingPath={
                                                        row.file_path ??
                                                        undefined
                                                    }
                                                    existingFileName={
                                                        row.file_name ??
                                                        undefined
                                                    }
                                                    useFileNameInput
                                                    fileNameValue={
                                                        row.file_name ?? null
                                                    }
                                                    isView={false}
                                                    disabled={processing}
                                                    onSelect={(file) => {
                                                        const nextRows =
                                                            allRows.length > 0
                                                                ? [...allRows]
                                                                : [
                                                                      createEmptyDocumentEntry(),
                                                                  ];
                                                        const targetIndex =
                                                            actualIndex >= 0
                                                                ? actualIndex
                                                                : 0;
                                                        nextRows[targetIndex] =
                                                            {
                                                                ...nextRows[
                                                                    targetIndex
                                                                ],
                                                                file,
                                                                removed: false,
                                                                file_name:
                                                                    nextRows[
                                                                        targetIndex
                                                                    ]
                                                                        ?.file_name ??
                                                                    buildOpsDocumentFileName(
                                                                        tab.label,
                                                                        visibleIndex +
                                                                            1,
                                                                        data.package_number,
                                                                    ),
                                                            };
                                                        updateDocumentRows(
                                                            tab.key,
                                                            nextRows,
                                                        );
                                                    }}
                                                    onFileNameChange={(
                                                        fileName,
                                                    ) => {
                                                        const nextRows =
                                                            allRows.length > 0
                                                                ? [...allRows]
                                                                : [
                                                                      createEmptyDocumentEntry(),
                                                                  ];
                                                        const targetIndex =
                                                            actualIndex >= 0
                                                                ? actualIndex
                                                                : 0;
                                                        nextRows[targetIndex] =
                                                            {
                                                                ...nextRows[
                                                                    targetIndex
                                                                ],
                                                                file_name:
                                                                    fileName,
                                                            };
                                                        updateDocumentRows(
                                                            tab.key,
                                                            nextRows,
                                                        );
                                                    }}
                                                    onClear={() => {
                                                        updateDocumentRows(
                                                            tab.key,
                                                            removeDocumentEntryAtIndex(
                                                                allRows,
                                                                actualIndex,
                                                            ),
                                                        );
                                                    }}
                                                />
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </TabsContent>
                    );
                })}

                <TabsContent value="budget" className="space-y-3">
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={addBudgetTitle}
                        >
                            <Plus className="h-4 w-4" />
                            Add Title
                        </Button>
                    </div>

                    {budgetTotals.sections.map((sectionRow, titleIndex) => {
                        const section = sectionRow.section;

                        return (
                            <Card
                                key={`budget-title-${titleIndex}`}
                                className="border-border/70"
                            >
                                <CardHeader className="gap-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <CardTitle className="text-lg">
                                            <FormField
                                                label={`Title ${titleIndex + 1}`}
                                            >
                                                <ProperInput
                                                    value={section.title ?? ''}
                                                    disabled={processing}
                                                    onCommit={(value) =>
                                                        updateBudgetTitle(
                                                            titleIndex,
                                                            {
                                                                title: value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="Enter title"
                                                    className="md:min-w-[300px]"
                                                />
                                            </FormField>
                                        </CardTitle>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            disabled={
                                                processing ||
                                                (data.budget ?? []).length <= 1
                                            }
                                            onClick={() =>
                                                removeBudgetTitle(titleIndex)
                                            }
                                            className="text-destructive"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {(section.items ?? []).map(
                                        (item, itemIndex) => {
                                            const lineTotal =
                                                toDecimal(item.unit_price) *
                                                toDecimal(item.quantity);

                                            return (
                                                <div
                                                    key={`budget-item-${titleIndex}-${itemIndex}`}
                                                    className="grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-12"
                                                >
                                                    <div className="md:col-span-3">
                                                        <FormField label="Item Name">
                                                            <ProperInput
                                                                value={
                                                                    item.item_name ??
                                                                    ''
                                                                }
                                                                disabled={
                                                                    processing
                                                                }
                                                                onCommit={(
                                                                    value,
                                                                ) =>
                                                                    updateBudgetItem(
                                                                        titleIndex,
                                                                        itemIndex,
                                                                        {
                                                                            item_name:
                                                                                value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="Enter item"
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="md:col-span-2">
                                                        <FormField
                                                            label="Unit Price"
                                                            error={getError(
                                                                `budget.${titleIndex}.items.${itemIndex}.unit_price`,
                                                            )}
                                                        >
                                                            <ProperInput
                                                                value={String(
                                                                    item.unit_price ??
                                                                        0,
                                                                )}
                                                                disabled={
                                                                    processing
                                                                }
                                                                onCommit={(
                                                                    value,
                                                                ) =>
                                                                    updateBudgetItem(
                                                                        titleIndex,
                                                                        itemIndex,
                                                                        {
                                                                            unit_price:
                                                                                toDecimal(
                                                                                    value,
                                                                                ),
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="0.00"
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="md:col-span-2">
                                                        <FormField
                                                            label="Quantity"
                                                            error={getError(
                                                                `budget.${titleIndex}.items.${itemIndex}.quantity`,
                                                            )}
                                                        >
                                                            <ProperInput
                                                                value={String(
                                                                    item.quantity ??
                                                                        0,
                                                                )}
                                                                disabled={
                                                                    processing
                                                                }
                                                                onCommit={(
                                                                    value,
                                                                ) =>
                                                                    updateBudgetItem(
                                                                        titleIndex,
                                                                        itemIndex,
                                                                        {
                                                                            quantity:
                                                                                toDecimal(
                                                                                    value,
                                                                                ),
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="0.00"
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="md:col-span-2">
                                                        <FormField label="Total (Saudi Riyal)">
                                                            <CopyableText
                                                                value={
                                                                    lineTotal
                                                                }
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="md:col-span-2">
                                                        <FormField label="Remarks">
                                                            <ProperInput
                                                                value={
                                                                    item.remarks ??
                                                                    ''
                                                                }
                                                                disabled={
                                                                    processing
                                                                }
                                                                onCommit={(
                                                                    value,
                                                                ) =>
                                                                    updateBudgetItem(
                                                                        titleIndex,
                                                                        itemIndex,
                                                                        {
                                                                            remarks:
                                                                                value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="Remarks"
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="flex items-end md:col-span-1">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            disabled={
                                                                processing ||
                                                                (section.items
                                                                    ?.length ??
                                                                    0) <= 1
                                                            }
                                                            onClick={() =>
                                                                removeBudgetItem(
                                                                    titleIndex,
                                                                    itemIndex,
                                                                )
                                                            }
                                                            className="text-destructive"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            );
                                        },
                                    )}

                                    <div className="flex flex-col items-end gap-3 border-t pt-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            disabled={processing}
                                            onClick={() =>
                                                addBudgetItem(titleIndex)
                                            }
                                        >
                                            <Plus className="h-4 w-4" />
                                            Add Item
                                        </Button>
                                        <p className="text-lg font-semibold">
                                            {`${section.title} (SAR):`}{' '}
                                            <span className="text-primary">
                                                {formatSar(sectionRow.total)}
                                            </span>
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}

                    <Card>
                        <CardContent>
                            <div className="flex items-center justify-end">
                                <p className="text-lg font-semibold">
                                    {`Grand Total (SAR):`}{' '}
                                    <span className="text-primary">
                                        {formatSar(budgetTotals.grandTotal)}
                                    </span>
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                    disabled={processing}
                >
                    Back
                </Button>
                <Button type="submit" disabled={processing}>
                    {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                    {processing ? 'Saving...' : 'Save'}
                </Button>
            </div>
        </form>
    );
}
