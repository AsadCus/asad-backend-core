import { DatePickerField } from '@/components/date-picker';
import { DocumentField } from '@/components/document-field';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    type OpsTourLeaderSchema,
} from './schema';
import { opsMovementValidationSchema } from './validation';

interface OpsMovementFormProps {
    initialData: OpsMovementSchema;
    onCancel: () => void;
    canEdit?: boolean;
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
    const normalizedValue =
        value === null || value === undefined || String(value).trim() === ''
            ? '-'
            : String(value);

    return (
        <ProperInput
            value={normalizedValue}
            onCommit={() => undefined}
            disabled
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
        unit_price: null,
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

function createEmptyTourLeader(): OpsTourLeaderSchema {
    return {
        type: '',
        name: '',
        contact_number: '',
    };
}

function createDefaultTourLeaders(): OpsTourLeaderSchema[] {
    return [
        {
            type: 'Saudi',
            name: '',
            contact_number: '',
        },
        {
            type: 'Singapore',
            name: '',
            contact_number: '',
        },
    ];
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

const YES_NO_OPTIONS = [
    { label: 'Yes', value: 'yes' },
    { label: 'No', value: 'no' },
];

export default function OpsMovementForm({
    initialData,
    onCancel,
    canEdit = true,
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
        pif: {
            tour_leaders:
                Array.isArray(initialData.pif?.tour_leaders) &&
                    initialData.pif.tour_leaders.length > 0
                    ? initialData.pif.tour_leaders
                    : createDefaultTourLeaders(),
        },
    });
    const { data, setData, processing, post, transform } = form;
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
            visa_submitted_to_z_umrah: Boolean(data.visa_submitted_to_z_umrah),
            visa_approved: Boolean(data.visa_approved),
            accommodations: (data.accommodations ?? []).map(
                (accommodation) => ({
                    id: accommodation.id,
                    ic: accommodation.ic ?? null,
                    remarks: accommodation.remarks ?? null,
                }),
            ),
            officials: (data.officials ?? []).map((official) => ({
                id: official.id,
                hotel: official.hotel ?? null,
                hotels_by_location: (official.hotels_by_location ?? []).map(
                    (hotelRow) => ({
                        location: String(hotelRow.location ?? '').trim(),
                        hotel:
                            typeof hotelRow.hotel === 'string' &&
                                hotelRow.hotel.trim().length > 0
                                ? hotelRow.hotel.trim()
                                : null,
                    }),
                ),
            })),
            flights: (data.flights ?? []).map((flight) => ({
                id: flight.id,
                ic: flight.ic ?? null,
                remarks: flight.remarks ?? null,
            })),
            location: data.location ?? null,
            doa_by: data.doa_by ?? null,
            doa_datetime: data.doa_datetime ?? null,
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
            rawdah_tasreehs: (data.rawdah_tasreehs ?? []).map((row) => ({
                id: row.id,
                remarks: row.remarks ?? null,
            })),
            pif: {
                tour_leaders: (data.pif?.tour_leaders ?? []).map(
                    (tourLeader) => ({
                        type: tourLeader.type ?? null,
                        name: tourLeader.name ?? null,
                        contact_number: tourLeader.contact_number ?? null,
                    }),
                ),
            },
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

    const accommodationLocations = useMemo(() => {
        const locations = (data.accommodations ?? []).map(
            (accommodation, index) => {
                const normalizedLocation = String(
                    accommodation.location ?? '',
                ).trim();

                return normalizedLocation.length > 0
                    ? normalizedLocation
                    : `Location ${index + 1}`;
            },
        );

        const uniqueLocations: string[] = [];
        locations.forEach((location) => {
            if (
                !uniqueLocations.some(
                    (current) =>
                        current.toLowerCase() === location.toLowerCase(),
                )
            ) {
                uniqueLocations.push(location);
            }
        });

        return uniqueLocations;
    }, [data.accommodations]);

    const resolveOfficialHotelRows = (official: OpsOfficialSchema) => {
        const storedRows = (official.hotels_by_location ?? [])
            .map((row) => ({
                location: String(row.location ?? '').trim(),
                hotel: String(row.hotel ?? '').trim(),
            }))
            .filter((row) => row.location.length > 0);

        if (accommodationLocations.length > 0) {
            return accommodationLocations.map((location) => {
                const matched = storedRows.find(
                    (row) =>
                        row.location.toLowerCase() === location.toLowerCase(),
                );

                return {
                    location,
                    hotel:
                        matched?.hotel ?? String(official.hotel ?? '').trim(),
                };
            });
        }

        if (storedRows.length > 0) {
            return storedRows;
        }

        return [
            {
                location: 'General',
                hotel: String(official.hotel ?? '').trim(),
            },
        ];
    };

    const updateOfficialHotelByLocation = (
        officialIndex: number,
        location: string,
        value: string | null,
    ) => {
        const nextOfficials = [...(data.officials ?? [])];
        const targetOfficial = nextOfficials[officialIndex];

        if (!targetOfficial) {
            return;
        }

        const normalizedLocation = String(location ?? '').trim();
        if (normalizedLocation.length === 0) {
            return;
        }

        const nextHotelRows = resolveOfficialHotelRows(targetOfficial).map(
            (row) => ({
                location: row.location,
                hotel:
                    row.location.toLowerCase() ===
                        normalizedLocation.toLowerCase()
                        ? String(value ?? '').trim()
                        : String(row.hotel ?? '').trim(),
            }),
        );

        const primaryHotel =
            nextHotelRows.find((row) => row.hotel.length > 0)?.hotel ?? null;

        nextOfficials[officialIndex] = {
            ...targetOfficial,
            hotel: primaryHotel,
            hotels_by_location: nextHotelRows.map((row) => ({
                location: row.location,
                hotel: row.hotel.length > 0 ? row.hotel : null,
            })),
        };

        setFormData('officials', nextOfficials);
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

    const updatePifFlightRemarks = (index: number, value: string | null) => {
        updateFlight(index, 'remarks', value);
    };

    const updatePifAccommodationRemarks = (
        index: number,
        value: string | null,
    ) => {
        const nextRows = [...(data.accommodations ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            remarks: value,
        };
        setFormData('accommodations', nextRows);
    };

    const updatePifRawdahRemarks = (index: number, value: string | null) => {
        const nextRows = [...(data.rawdah_tasreehs ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            remarks: value,
        };
        setFormData('rawdah_tasreehs', nextRows);
    };

    const updatePifTransportationRemarks = (
        index: number,
        value: string | null,
    ) => {
        const nextRows = [...(data.transportation_plans ?? [])];
        nextRows[index] = {
            ...nextRows[index],
            remarks: value,
        };
        setFormData('transportation_plans', nextRows);
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

    const updateTourLeader = (
        index: number,
        field: keyof OpsTourLeaderSchema,
        value: string | null,
    ) => {
        const nextRows = [...(data.pif?.tour_leaders ?? [])];
        const target = nextRows[index];

        if (!target) {
            return;
        }

        nextRows[index] = {
            ...target,
            [field]: value,
        };

        setFormData('pif', {
            ...(data.pif ?? {}),
            tour_leaders: nextRows,
        });
    };

    const addTourLeader = () => {
        setFormData('pif', {
            ...(data.pif ?? {}),
            tour_leaders: [
                ...(data.pif?.tour_leaders ?? createDefaultTourLeaders()),
                createEmptyTourLeader(),
            ],
        });
    };

    const removeTourLeader = (index: number) => {
        const nextRows = [...(data.pif?.tour_leaders ?? [])];

        if (index < 0 || index >= nextRows.length) {
            return;
        }

        nextRows.splice(index, 1);

        setFormData('pif', {
            ...(data.pif ?? {}),
            tour_leaders:
                nextRows.length > 0 ? nextRows : createDefaultTourLeaders(),
        });
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

        if (!canEdit) {
            return;
        }

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

        transform(
            () =>
                ({
                    ...editablePayload,
                    _method: 'patch',
                }) as unknown as OpsMovementSchema,
        );

        post(`/ops-movements/${data.id}`, {
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
                        <TabsTrigger value="pif" className="text-lg">
                            PIF
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
                            <p className="text-sm text-muted-foreground">
                                Core operational details derived from package
                                and manifest, with editable ops references.
                            </p>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField
                                label="Travel Date Range"
                                fieldRequirementsProps={{
                                    hint: 'Departure to return range for this movement, derived from package dates.',
                                    example: '01 April 2026 - 10 April 2026',
                                }}
                            >
                                <CopyableText
                                    value={data.departure_return_range}
                                />
                            </FormField>
                            <FormField
                                label="First Hotel"
                                fieldRequirementsProps={{
                                    hint: 'First hotel destination from package accommodation sequence.',
                                }}
                            >
                                <CopyableText value={data.first_hotel_name} />
                            </FormField>
                            <FormField
                                label="Visa Type"
                                fieldRequirementsProps={{
                                    hint: 'Package visa category used for this movement.',
                                }}
                            >
                                <CopyableText value={data.visa_type} />
                            </FormField>
                            <FormField
                                label="Ops Base"
                                fieldRequirementsProps={{
                                    hint: 'Operational base or control location for this trip.',
                                    example: 'Makkah Ops Desk A',
                                }}
                                error={errors.ops_base}
                            >
                                <ProperInput
                                    value={data.ops_base ?? ''}
                                    disabled={processing}
                                    onCommit={(value) =>
                                        setFormData('ops_base', value)
                                    }
                                    placeholder="Enter ops base"
                                />
                            </FormField>
                            <FormField
                                label="Package Number"
                                fieldRequirementsProps={{
                                    hint: 'Source package identifier.',
                                }}
                            >
                                <CopyableText value={data.package_number} />
                            </FormField>
                            <FormField
                                label="Manifest Number"
                                fieldRequirementsProps={{
                                    hint: 'Linked manifest identifier for operations.',
                                }}
                            >
                                <CopyableText value={data.manifest_number} />
                            </FormField>
                            <FormField
                                label="Infotech Ref"
                                fieldRequirementsProps={{
                                    hint: 'External system reference used by operations.',
                                    example: 'INF-OPS-2026-0042',
                                }}
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
                            <p className="text-sm text-muted-foreground">
                                Passenger totals split by adult, child,
                                official, and wheelchair counts.
                            </p>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Adult">{`${data.passengers?.adult_total ?? 0} (${data.passengers?.adult_male ?? 0} male / ${data.passengers?.adult_female ?? 0} female)`}</FormField>
                            <FormField label="Child">{`${data.passengers?.child_total ?? 0} (${data.passengers?.child_boy ?? 0} boy / ${data.passengers?.child_girl ?? 0} girl)`}</FormField>
                            <FormField label="Official Total">
                                {String(data.passengers?.official_total ?? 0)}
                            </FormField>
                            <FormField label="Wheelchair">
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
                            <p className="text-sm text-muted-foreground">
                                Accommodation list from package with editable IC
                                notes per hotel location.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(data.accommodations ?? []).map(
                                (accommodation, index) => (
                                    <div
                                        key={accommodation.id}
                                        className="grid grid-cols-1 items-start gap-4 rounded-lg border p-4 md:grid-cols-6"
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
                            <p className="text-sm text-muted-foreground">
                                Official names come from package; hotel field is
                                a remark for whether they stay in a dedicated
                                hotel or with jemaah.
                            </p>
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
                                    {resolveOfficialHotelRows(official).map(
                                        (hotelRow, hotelIndex) => (
                                            <FormField
                                                key={`${official.id}-${hotelRow.location}-${hotelIndex}`}
                                                label={`${hotelRow.location} - Hotel`}
                                                fieldRequirementsProps={{
                                                    hint: 'Hotel remark for this accommodation location.',
                                                }}
                                                error={
                                                    getError(
                                                        `officials.${index}.hotels_by_location.${hotelIndex}.hotel`,
                                                    ) ??
                                                    getError(
                                                        `officials.${index}.hotel`,
                                                    )
                                                }
                                            >
                                                <ProperInput
                                                    value={hotelRow.hotel}
                                                    disabled={processing}
                                                    onCommit={(value) =>
                                                        updateOfficialHotelByLocation(
                                                            index,
                                                            hotelRow.location,
                                                            value,
                                                        )
                                                    }
                                                    placeholder="Enter hotel"
                                                />
                                            </FormField>
                                        ),
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Flight Info
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Flight details are package-sourced, with
                                editable operational info for location, Doa, and
                                IC.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 items-start gap-4 rounded-lg border p-4 md:grid-cols-3">
                                <FormField
                                    label="Location"
                                    error={getError('location')}
                                >
                                    <ProperInput
                                        value={data.location ?? ''}
                                        disabled={processing}
                                        textarea
                                        onCommit={(value) =>
                                            setFormData('location', value)
                                        }
                                        placeholder="Enter location"
                                    />
                                </FormField>
                                <FormField
                                    label="Doa By"
                                    error={getError('doa_by')}
                                >
                                    <ProperInput
                                        value={data.doa_by ?? ''}
                                        disabled={processing}
                                        onCommit={(value) =>
                                            setFormData('doa_by', value)
                                        }
                                        placeholder="Enter Doa by"
                                    />
                                </FormField>
                                <FormField
                                    label="Doa Datetime"
                                    error={getError('doa_datetime')}
                                >
                                    <DatePickerField
                                        id="doa_datetime"
                                        value={data.doa_datetime || ''}
                                        fromYear={new Date().getFullYear() - 2}
                                        toYear={new Date().getFullYear() + 5}
                                        disabled={processing}
                                        useTime
                                        onChange={(value) =>
                                            setFormData(
                                                'doa_datetime',
                                                value || null,
                                            )
                                        }
                                    />
                                </FormField>
                            </div>
                            {(data.flights ?? []).map((flight, index) => (
                                <div
                                    key={flight.id}
                                    className="space-y-4 rounded-lg border p-4"
                                >
                                    <div className="text-lg font-semibold text-muted-foreground">
                                        {flight.description ||
                                            (index === 0
                                                ? 'Departure'
                                                : index === 1
                                                    ? 'Return'
                                                    : `Flight ${index + 1}`)}
                                    </div>
                                    <div className="grid grid-cols-1 gap-4">
                                        <FormField label="Flight No">
                                            <CopyableText value={flight.pnr} />
                                        </FormField>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                            <FormField label="From">
                                                <CopyableText
                                                    value={flight.from}
                                                />
                                            </FormField>
                                            <FormField label="Departure Datetime">
                                                <CopyableText
                                                    value={
                                                        flight.departure_datetime
                                                    }
                                                />
                                            </FormField>
                                            <FormField label="To">
                                                <CopyableText
                                                    value={flight.to}
                                                />
                                            </FormField>
                                            <FormField label="Arrival Datetime">
                                                <CopyableText
                                                    value={
                                                        flight.arrival_datetime
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <FormField label="Airline">
                                                <CopyableText
                                                    value={flight.airline}
                                                />
                                            </FormField>
                                            <FormField
                                                label="In Charge"
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
                                                    placeholder="Enter in charge"
                                                />
                                            </FormField>
                                        </div>
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
                            <p className="text-sm text-muted-foreground">
                                Ground transport details for the movement,
                                including driver assignment info.
                            </p>
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
                            <p className="text-sm text-muted-foreground">
                                Train movement narrative and package train
                                ticket references.
                            </p>
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

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">Visa</CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Visa processing checklist for this movement.
                            </p>
                        </CardHeader>
                        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField
                                label="Submitted to Z Umrah"
                                error={errors.visa_submitted_to_z_umrah}
                            >
                                <ProperInputSelect
                                    mode="classic"
                                    options={YES_NO_OPTIONS}
                                    value={
                                        data.visa_submitted_to_z_umrah
                                            ? 'yes'
                                            : 'no'
                                    }
                                    disabled={processing}
                                    onValueChange={(value) =>
                                        setFormData(
                                            'visa_submitted_to_z_umrah',
                                            value === 'yes',
                                        )
                                    }
                                    searchable={false}
                                />
                            </FormField>
                            <FormField
                                label="Approved"
                                error={errors.visa_approved}
                            >
                                <ProperInputSelect
                                    mode="classic"
                                    options={YES_NO_OPTIONS}
                                    value={data.visa_approved ? 'yes' : 'no'}
                                    disabled={processing}
                                    onValueChange={(value) =>
                                        setFormData(
                                            'visa_approved',
                                            value === 'yes',
                                        )
                                    }
                                    searchable={false}
                                />
                            </FormField>
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
                                                layout="inline"
                                                fieldRequirementsProps={{
                                                    hint: 'Budget section title grouping related cost items.',
                                                    example: 'Hotel Logistics',
                                                }}
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
                                                    className="grid grid-cols-1 items-start gap-4 rounded-lg border p-4 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.5fr)_auto]"
                                                >
                                                    <FormField label="Item Name">
                                                        <ProperInput
                                                            value={
                                                                item.item_name ??
                                                                ''
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                            onCommit={(value) =>
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
                                                    <FormField
                                                        label="Unit Price"
                                                        error={getError(
                                                            `budget.${titleIndex}.items.${itemIndex}.unit_price`,
                                                        )}
                                                    >
                                                        <ProperInput
                                                            value={
                                                                item.unit_price ===
                                                                    null ||
                                                                    item.unit_price ===
                                                                    undefined
                                                                    ? ''
                                                                    : String(
                                                                        item.unit_price,
                                                                    )
                                                            }
                                                            type="number"
                                                            inputProps={{
                                                                step: 'any',
                                                            }}
                                                            disabled={
                                                                processing
                                                            }
                                                            onCommit={(value) =>
                                                                updateBudgetItem(
                                                                    titleIndex,
                                                                    itemIndex,
                                                                    {
                                                                        unit_price:
                                                                            value ===
                                                                                ''
                                                                                ? null
                                                                                : toDecimal(
                                                                                    value,
                                                                                ),
                                                                    },
                                                                )
                                                            }
                                                            placeholder="0.00"
                                                        />
                                                    </FormField>
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
                                                            type="number"
                                                            inputProps={{
                                                                min: 0,
                                                                step: 'any',
                                                            }}
                                                            disabled={
                                                                processing
                                                            }
                                                            onCommit={(value) =>
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
                                                    <FormField label="Amount">
                                                        {/* <FormField label="Total (Saudi Riyal)"> */}
                                                        <CopyableText
                                                            value={lineTotal}
                                                        />
                                                    </FormField>
                                                    <FormField label="Remarks">
                                                        <ProperInput
                                                            value={
                                                                item.remarks ??
                                                                ''
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                            textarea
                                                            onCommit={(value) =>
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
                                                    <div className="flex items-center justify-end">
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
                                            {`${section.title} (Saudi Riyal):`}{' '}
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
                                    {`Grand Total (Saudi Riyal):`}{' '}
                                    <span className="text-primary">
                                        {formatSar(budgetTotals.grandTotal)}
                                    </span>
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="pif" className="space-y-6">
                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Passenger Details
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Passenger count breakdown for this ops movement, sourced from the linked manifest.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                <FormField
                                    label="Adult"
                                    fieldRequirementsProps={{
                                        hint: 'Total adult passengers in this manifest.',
                                    }}
                                >
                                    <CopyableText
                                        value={data.passengers?.adult_total ?? 0}
                                    />
                                </FormField>
                                <FormField
                                    label="Child"
                                    fieldRequirementsProps={{
                                        hint: 'Total child passengers (with and without bed).',
                                    }}
                                >
                                    <CopyableText
                                        value={data.passengers?.child_total ?? 0}
                                    />
                                </FormField>
                                <FormField
                                    label="Infant"
                                    fieldRequirementsProps={{
                                        hint: 'Number of infant passengers in this manifest.',
                                    }}
                                >
                                    <CopyableText
                                        value={data.passengers?.infant_total ?? 0}
                                    />
                                </FormField>
                                <FormField
                                    label="Official / Mutawif"
                                    fieldRequirementsProps={{
                                        hint: 'Number of Mutawif and officials included in this movement.',
                                    }}
                                >
                                    <CopyableText
                                        value={data.passengers?.official_total ?? 0}
                                    />
                                </FormField>
                            </div>
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                <FormField
                                    label="Child With Bed"
                                    fieldRequirementsProps={{
                                        hint: 'Children who occupy a bed in room allocation.',
                                    }}
                                >
                                    <CopyableText
                                        value={
                                            data.passengers?.child_with_bed_total ?? 0
                                        }
                                    />
                                </FormField>
                                <FormField
                                    label="Child No Bed"
                                    fieldRequirementsProps={{
                                        hint: 'Children who share a bed (no extra bed allocated).',
                                    }}
                                >
                                    <CopyableText
                                        value={
                                            data.passengers?.child_no_bed_total ?? 0
                                        }
                                    />
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <CardTitle className="text-xl">
                                        Tour Leader
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        Saudi and Singapore officials assigned to lead this movement. Enter their names and contact numbers for the PIF.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing || !canEdit}
                                    onClick={addTourLeader}
                                >
                                    <Plus className="h-4 w-4" />
                                    Add Tour Leader
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(data.pif?.tour_leaders ?? []).map(
                                (tourLeader, index) => (
                                    <div
                                        key={`tour-leader-${index}`}
                                        className="grid grid-cols-1 items-end gap-4 rounded-lg border p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]"
                                    >
                                        <FormField
                                            label="Office Type"
                                            fieldRequirementsProps={{
                                                hint: 'Type of official, e.g. Saudi or Singapore.',
                                                example: 'Saudi / Singapore',
                                            }}
                                            error={getError(
                                                `pif.tour_leaders.${index}.type`,
                                            )}
                                        >
                                            <ProperInput
                                                value={tourLeader.type ?? ''}
                                                disabled={processing || !canEdit}
                                                onCommit={(value) =>
                                                    updateTourLeader(
                                                        index,
                                                        'type',
                                                        value,
                                                    )
                                                }
                                                placeholder="Saudi / Singapore"
                                            />
                                        </FormField>
                                        <FormField
                                            label="Official Name"
                                            fieldRequirementsProps={{
                                                hint: 'Full name of the tour leader or official.',
                                            }}
                                            error={getError(
                                                `pif.tour_leaders.${index}.name`,
                                            )}
                                        >
                                            <ProperInput
                                                value={tourLeader.name ?? ''}
                                                disabled={processing || !canEdit}
                                                onCommit={(value) =>
                                                    updateTourLeader(
                                                        index,
                                                        'name',
                                                        value,
                                                    )
                                                }
                                                placeholder="Enter official name"
                                            />
                                        </FormField>
                                        <FormField
                                            label="Contact Number"
                                            fieldRequirementsProps={{
                                                hint: 'Phone number of the official, including country code.',
                                                example: '+65 9123 4567',
                                            }}
                                            error={getError(
                                                `pif.tour_leaders.${index}.contact_number`,
                                            )}
                                        >
                                            <ProperInput
                                                value={
                                                    tourLeader.contact_number ??
                                                    ''
                                                }
                                                disabled={processing || !canEdit}
                                                onCommit={(value) =>
                                                    updateTourLeader(
                                                        index,
                                                        'contact_number',
                                                        value,
                                                    )
                                                }
                                                placeholder="Enter contact number"
                                            />
                                        </FormField>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing || !canEdit}
                                            onClick={() =>
                                                removeTourLeader(index)
                                            }
                                            className="text-destructive"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ),
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Flight Schedule
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Departure and return flights for this movement, pulled from the package flight details. Add remarks for each flight as needed.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(data.flights ?? []).map((flight, index) => (
                                <div
                                    key={`pif-flight-${flight.id}`}
                                    className="grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-3"
                                >
                                    <FormField
                                        label="Airline / Carrier"
                                        fieldRequirementsProps={{
                                            hint: 'Airline carrier code or name for this flight.',
                                        }}
                                    >
                                        <CopyableText value={flight.airline} />
                                    </FormField>
                                    <FormField
                                        label="From"
                                        fieldRequirementsProps={{
                                            hint: 'Departure airport or city.',
                                        }}
                                    >
                                        <CopyableText value={flight.from} />
                                    </FormField>
                                    <FormField
                                        label="To"
                                        fieldRequirementsProps={{
                                            hint: 'Arrival airport or city.',
                                        }}
                                    >
                                        <CopyableText value={flight.to} />
                                    </FormField>
                                    <FormField
                                        label="ETD (Departure Datetime)"
                                        fieldRequirementsProps={{
                                            hint: 'Estimated time of departure.',
                                        }}
                                    >
                                        <CopyableText
                                            value={flight.departure_datetime}
                                        />
                                    </FormField>
                                    <FormField
                                        label="ETA (Arrival Datetime)"
                                        fieldRequirementsProps={{
                                            hint: 'Estimated time of arrival.',
                                        }}
                                    >
                                        <CopyableText
                                            value={flight.arrival_datetime}
                                        />
                                    </FormField>
                                    <FormField
                                        label="PNR"
                                        fieldRequirementsProps={{
                                            hint: 'Flight booking reference / PNR.',
                                        }}
                                    >
                                        <CopyableText value={flight.pnr} />
                                    </FormField>
                                    <FormField
                                        label="Remarks"
                                        className="md:col-span-3"
                                        fieldRequirementsProps={{
                                            hint: 'Any additional notes for this flight, e.g. meal arrangements or special instructions.',
                                            example: 'Dinner at Hotel (International Buffet Dinner Hours: 7.30 till 10.30 pm)',
                                        }}
                                        error={getError(
                                            `flights.${index}.remarks`,
                                        )}
                                    >
                                        <ProperInput
                                            value={flight.remarks ?? ''}
                                            disabled={processing || !canEdit}
                                            textarea
                                            onCommit={(value) =>
                                                updatePifFlightRemarks(index, value)
                                            }
                                            placeholder="Optional remarks"
                                        />
                                    </FormField>
                                </div>
                            ))}
                            {(data.flights ?? []).length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No flight data available.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Accommodation
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Hotel stays per city for this movement. Room counts and member category counts are derived from manifest room allocations. Add remarks per accommodation as needed.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(data.accommodations ?? []).map(
                                (accommodation, index) => (
                                    <div
                                        key={`pif-accommodation-${accommodation.id}`}
                                        className="space-y-3 rounded-lg border p-4"
                                    >
                                        {/* Hotel info row */}
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <FormField
                                                label="City"
                                                fieldRequirementsProps={{
                                                    hint: 'City or destination of this accommodation.',
                                                }}
                                            >
                                                <CopyableText
                                                    value={accommodation.location}
                                                />
                                            </FormField>
                                            <FormField
                                                label="Hotel Name"
                                                fieldRequirementsProps={{
                                                    hint: 'Name of the hotel for this stay.',
                                                }}
                                            >
                                                <CopyableText
                                                    value={accommodation.hotel_name}
                                                />
                                            </FormField>
                                            <FormField
                                                label="Nights"
                                                fieldRequirementsProps={{
                                                    hint: 'Total number of nights at this hotel.',
                                                }}
                                            >
                                                <CopyableText
                                                    value={accommodation.nights ?? 0}
                                                />
                                            </FormField>
                                        </div>
                                        {/* Dates row */}
                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <FormField
                                                label="Check In"
                                                fieldRequirementsProps={{
                                                    hint: 'Check-in date.',
                                                }}
                                            >
                                                <CopyableText
                                                    value={accommodation.check_in}
                                                />
                                            </FormField>
                                            <FormField
                                                label="Check Out"
                                                fieldRequirementsProps={{
                                                    hint: 'Check-out date.',
                                                }}
                                            >
                                                <CopyableText
                                                    value={accommodation.check_out}
                                                />
                                            </FormField>
                                        </div>
                                        {/* Room counts row */}
                                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                            <FormField
                                                label="Single"
                                                fieldRequirementsProps={{
                                                    hint: 'Number of single rooms allocated from manifest.',
                                                }}
                                            >
                                                <CopyableText
                                                    value={
                                                        accommodation.room_counts
                                                            ?.single ?? 0
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                label="Double (DBL)"
                                                fieldRequirementsProps={{
                                                    hint: 'Number of double rooms (2 pax per room).',
                                                }}
                                            >
                                                <CopyableText
                                                    value={
                                                        accommodation.room_counts
                                                            ?.double ?? 0
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                label="Triple (TRP)"
                                                fieldRequirementsProps={{
                                                    hint: 'Number of triple rooms (3 pax per room).',
                                                }}
                                            >
                                                <CopyableText
                                                    value={
                                                        accommodation.room_counts
                                                            ?.triple ?? 0
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                label="Quad"
                                                fieldRequirementsProps={{
                                                    hint: 'Number of quad rooms (4 pax per room).',
                                                }}
                                            >
                                                <CopyableText
                                                    value={
                                                        accommodation.room_counts
                                                            ?.quad ?? 0
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                        {/* Member category counts row */}
                                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                            <FormField
                                                label="Child With Bed"
                                                fieldRequirementsProps={{
                                                    hint: 'Number of children in this accommodation who occupy a bed (counted in room pricing plan).',
                                                }}
                                            >
                                                <CopyableText
                                                    value={
                                                        accommodation.room_counts
                                                            ?.child_with_bed ?? 0
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                label="Child No Bed"
                                                fieldRequirementsProps={{
                                                    hint: 'Number of children who share a bed with parents — not counted in room pricing plan.',
                                                }}
                                            >
                                                <CopyableText
                                                    value={
                                                        accommodation.room_counts
                                                            ?.child_no_bed ?? 0
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                label="Infant"
                                                fieldRequirementsProps={{
                                                    hint: 'Number of infants in this accommodation — not counted in room pricing plan.',
                                                }}
                                            >
                                                <CopyableText
                                                    value={
                                                        accommodation.room_counts
                                                            ?.infant ?? 0
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                        {/* Remarks */}
                                        <FormField
                                            label="Remarks"
                                            fieldRequirementsProps={{
                                                hint: 'Additional notes for this accommodation, e.g. meal type or special arrangements.',
                                                example: 'Breakfast included',
                                            }}
                                            error={getError(
                                                `accommodations.${index}.remarks`,
                                            )}
                                        >
                                            <ProperInput
                                                value={accommodation.remarks ?? ''}
                                                disabled={processing || !canEdit}
                                                textarea
                                                onCommit={(value) =>
                                                    updatePifAccommodationRemarks(
                                                        index,
                                                        value,
                                                    )
                                                }
                                                placeholder="Optional remarks"
                                            />
                                        </FormField>
                                    </div>
                                ),
                            )}
                            {(data.accommodations ?? []).length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No accommodation data available.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Rawdah Tasreeh
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Rawdah visit permit details for women and men passengers. Dates and pax counts are sourced from the package. Add remarks for any scheduling notes.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(data.rawdah_tasreehs ?? []).map((row, index) => (
                                <div
                                    key={`pif-rawdah-${row.id}`}
                                    className="space-y-3 rounded-lg border p-4"
                                >
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                        <FormField
                                            label="Date"
                                            fieldRequirementsProps={{
                                                hint: 'Scheduled date for the Rawdah visit.',
                                            }}
                                        >
                                            <CopyableText value={row.date} />
                                        </FormField>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                        <FormField
                                            label="Women Pax"
                                            fieldRequirementsProps={{
                                                hint: 'Number of female passengers for this Rawdah slot.',
                                            }}
                                        >
                                            <CopyableText
                                                value={row.women_passengers ?? 0}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Women Time"
                                            fieldRequirementsProps={{
                                                hint: 'Scheduled entry time for female passengers.',
                                            }}
                                        >
                                            <CopyableText value={row.women_time} />
                                        </FormField>
                                        <FormField
                                            label="Men Pax"
                                            fieldRequirementsProps={{
                                                hint: 'Number of male passengers for this Rawdah slot.',
                                            }}
                                        >
                                            <CopyableText
                                                value={row.men_passengers ?? 0}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Men Time"
                                            fieldRequirementsProps={{
                                                hint: 'Scheduled entry time for male passengers.',
                                            }}
                                        >
                                            <CopyableText value={row.men_time} />
                                        </FormField>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                        <FormField
                                            label="Total Pax"
                                            fieldRequirementsProps={{
                                                hint: 'Total passengers (women + men) for this slot.',
                                            }}
                                        >
                                            <CopyableText
                                                value={
                                                    (row.women_passengers ?? 0) +
                                                    (row.men_passengers ?? 0)
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    <FormField
                                        label="Remarks"
                                        fieldRequirementsProps={{
                                            hint: 'Any scheduling notes, blocked dates, or departure info.',
                                            example: 'Mazarat 10 Jan, DEP 7.30 AM',
                                        }}
                                        error={getError(
                                            `rawdah_tasreehs.${index}.remarks`,
                                        )}
                                    >
                                        <ProperInput
                                            value={row.remarks ?? ''}
                                            disabled={processing || !canEdit}
                                            textarea
                                            onCommit={(value) =>
                                                updatePifRawdahRemarks(index, value)
                                            }
                                            placeholder="Optional remarks"
                                        />
                                    </FormField>
                                </div>
                            ))}
                            {(data.rawdah_tasreehs ?? []).length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No rawdah tasreeh data available.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-0">
                            <CardTitle className="text-xl">
                                Transportation Plan
                            </CardTitle>
                            <p className="text-sm text-muted-foreground">
                                Ground transport itinerary for the movement, covering airport transfers, city tours, and inter-city travel. Add remarks for special instructions per leg.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {(data.transportation_plans ?? []).map((row, index) => (
                                <div
                                    key={`pif-transport-${row.id}`}
                                    className="grid grid-cols-1 gap-4 rounded-lg border p-4 md:grid-cols-3"
                                >
                                    <FormField
                                        label="From"
                                        fieldRequirementsProps={{
                                            hint: 'Departure point for this transport leg.',
                                            example: 'Jeddah Airport',
                                        }}
                                    >
                                        <CopyableText value={row.from} />
                                    </FormField>
                                    <FormField
                                        label="To"
                                        fieldRequirementsProps={{
                                            hint: 'Destination for this transport leg.',
                                            example: 'Sofitel Hotel',
                                        }}
                                    >
                                        <CopyableText value={row.to} />
                                    </FormField>
                                    <FormField
                                        label="Date"
                                        fieldRequirementsProps={{
                                            hint: 'Date of travel for this transport leg.',
                                        }}
                                    >
                                        <CopyableText value={row.travel_date} />
                                    </FormField>
                                    <FormField
                                        label="Time"
                                        fieldRequirementsProps={{
                                            hint: 'Scheduled departure or pickup time.',
                                            example: '07:30 AM',
                                        }}
                                    >
                                        <CopyableText value={row.travel_time} />
                                    </FormField>
                                    <FormField
                                        label="Remarks"
                                        className="md:col-span-2"
                                        fieldRequirementsProps={{
                                            hint: 'Any notes for this leg, e.g. pending confirmation, special stops, or vehicle type.',
                                            example: 'REHAL / MAKKAH HOTEL',
                                        }}
                                        error={getError(
                                            `transportation_plans.${index}.remarks`,
                                        )}
                                    >
                                        <ProperInput
                                            value={row.remarks ?? ''}
                                            disabled={processing || !canEdit}
                                            textarea
                                            onCommit={(value) =>
                                                updatePifTransportationRemarks(
                                                    index,
                                                    value,
                                                )
                                            }
                                            placeholder="Optional remarks"
                                        />
                                    </FormField>
                                </div>
                            ))}
                            {(data.transportation_plans ?? []).length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No transportation data available.
                                </p>
                            )}
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
                {canEdit && (
                    <Button type="submit" disabled={processing}>
                        {processing && (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        )}
                        {processing ? 'Saving...' : 'Save'}
                    </Button>
                )}
            </div>
        </form>
    );
}