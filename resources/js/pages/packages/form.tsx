import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { FormProgressHeader } from '@/components/form-progress-header';
import { FormSection } from '@/components/form-section';
import ModelNumberInput from '@/components/model-number-input';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Accordion } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { isBeforeToday, parseDisplayDate } from '@/lib/utils';
import { navigateToSection } from '@/lib/navigation-helper';
import { store, update } from '@/routes/packages';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { AlertCircle, Loader2, Plus, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { genderOptions } from '../customer/schema';
import {
    infantAndChildPriceLabels,
    officialTypeOptions,
    packageMealPlanOptions,
    packageMealTimeOptions,
    packageTrainTicketTypeOptions,
    packageVisaTypeOptions,
    sharingPlanPriceLabels,
    type AccommodationSchema,
    type FlightSchema,
    type OfficialSchema,
    type PackageSchema,
    type RawdahTasreehSchema,
    type TrainTicketSchema,
    type TransportationPlanSchema,
} from './schema';
import { packageValidationSchema } from './validation';

const defaultFlights: FlightSchema[] = [
    {
        from: '',
        to: '',
        description: 'Departure',
        airline: '',
        pnr: '',
        departure_datetime: '',
        arrival_datetime: '',
        remarks: '',
    },
    {
        from: '',
        to: '',
        description: 'Return',
        airline: '',
        pnr: '',
        departure_datetime: '',
        arrival_datetime: '',
        remarks: '',
    },
];

const defaultTrainTickets: TrainTicketSchema[] = [
    {
        from: '',
        to: '',
        travel_date: '',
        travel_time: '',
        remarks: '',
    },
    {
        from: '',
        to: '',
        travel_date: '',
        travel_time: '',
        remarks: '',
    },
];

const defaultOfficials: OfficialSchema[] = [
    {
        type: 'mutawif',
        name: '',
        hotel: '',
        hotel_map: {},
        contact_number: '',
        nationality: '',
        passport_number: '',
        gender: '',
        date_of_birth: '',
        passport_issue_date: '',
        passport_expiry_date: '',
        passport_place_of_issue: '',
        place_of_birth: '',
    },
    {
        type: 'mutawifah',
        name: '',
        hotel: '',
        hotel_map: {},
        contact_number: '',
        nationality: '',
        passport_number: '',
        gender: '',
        date_of_birth: '',
        passport_issue_date: '',
        passport_expiry_date: '',
        passport_place_of_issue: '',
        place_of_birth: '',
    },
    {
        type: 'official',
        name: '',
        hotel: '',
        hotel_map: {},
        contact_number: '',
        nationality: '',
        passport_number: '',
        gender: '',
        date_of_birth: '',
        passport_issue_date: '',
        passport_expiry_date: '',
        passport_place_of_issue: '',
        place_of_birth: '',
    },
];

const officialTypeLabels = Object.fromEntries(
    officialTypeOptions.map((option) => [option.value, option.label]),
) as Record<string, string>;

interface PackageFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: PackageSchema;
    prefillData?: Partial<PackageSchema>;
    countries?: OptionType[];
    lockCountrySelection?: boolean;
    onCancel?: () => void;
    onSuccess?: (data: PackageSchema) => void;
}

export default function PackageForm({
    mode,
    initialData,
    prefillData,
    countries = [],
    lockCountrySelection = false,
    onCancel,
    onSuccess,
}: PackageFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';
    const hasPersistedPackageLocation =
        String(initialData?.country_id ?? '').trim().length > 0;

    const defaultData: PackageSchema = initialData || {
        package_number: '',
        package_number_format_id: null,
        name: '',
        status: 'open',
        country_id: '',
        price_single: null,
        price_double: null,
        price_triple: null,
        price_quad: null,
        child_with_bed_price: null,
        child_no_bed_price: null,
        infant_price: null,
        departure_date: '',
        return_date: '',
        total_seats: null,
        seats_left: null,
        occupied_seats: 0,
        visa_type: '',
        vehicle_type: '',
        vehicle_driver_name: '',
        vehicle_driver_contact_number: '',
        ticket_type: '',
        train_description: '',
        included: '',
        not_included: '',
        offer: '',
        remarks: '',
        accommodations: [],
        flights: [...defaultFlights],
        train_tickets: [],
        transportation_plans: [],
        rawdah_tasreehs: [],
        rawdah_member_counts: {
            total: 0,
            women: 0,
            men: 0,
        },
        officials: [...defaultOfficials],
        ...prefillData,
    };

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        setError,
        clearErrors,
    } = useForm<PackageSchema>(defaultData);

    const [openSections, setOpenSections] = useState<string[]>(['package-information']);

    const lastAppliedPrefillRef = useRef<string | null>(null);
    const previousDepartureDateRef = useRef<string>('');
    const hasInitializedDepartureDateSyncRef = useRef<boolean>(false);
    const errorBannerRef = useRef<HTMLDivElement | null>(null);

    const getInputIdFromPath = useCallback((path: string): string => {
        return path.replace(/\./g, '_');
    }, []);

    const toFieldLabel = useCallback((path: string): string => {
        const flightMatch = path.match(/^flights\.(\d+)\.(.+)$/);
        if (flightMatch) {
            const field = flightMatch[2].replace(/_/g, ' ');

            return `Flight ${Number(flightMatch[1]) + 1} - ${field}`;
        }

        const accommodationMatch = path.match(/^accommodations\.(\d+)\.(.+)$/);
        if (accommodationMatch) {
            const field = accommodationMatch[2].replace(/_/g, ' ');

            return `Accommodation ${Number(accommodationMatch[1]) + 1} - ${field}`;
        }

        const officialMatch = path.match(/^officials\.(\d+)\.(.+)$/);
        if (officialMatch) {
            const field = officialMatch[2].replace(/_/g, ' ');

            return `Official ${Number(officialMatch[1]) + 1} - ${field}`;
        }

        return path.replace(/_/g, ' ');
    }, []);

    const scrollToErrorBanner = useCallback((): void => {
        errorBannerRef.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }, []);

    const focusErrorField = useCallback(
        (path: string): void => {
            if (
                path === 'package_number' ||
                path === 'package_number_format_id'
            ) {
                const packageNumberInput = document.querySelector<HTMLElement>(
                    'input[placeholder="Enter model number"]',
                );

                if (packageNumberInput) {
                    packageNumberInput.focus();
                    packageNumberInput.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                    });
                }

                return;
            }

            const elementId = getInputIdFromPath(path);
            const target =
                document.getElementById(elementId) ??
                document.querySelector<HTMLElement>(`[name="${path}"]`);

            if (!target) {
                return;
            }

            target.focus();
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
        },
        [getInputIdFromPath],
    );

    const errorEntries = useMemo(() => {
        return Object.entries(errors as Record<string, string>);
    }, [errors]);

    const hasErrorsForPrefixes = useCallback(
        (prefixes: string[]) => {
            return errorEntries.some(([path]) =>
                prefixes.some(
                    (prefix) =>
                        path === prefix || path.startsWith(`${prefix}.`),
                ),
            );
        },
        [errorEntries],
    );

    const sectionStatuses = useMemo(() => {
        const hasPackageInfo = Boolean(data.name) || Boolean(data.status);
        const hasPricing = [
            data.price_single,
            data.price_double,
            data.price_triple,
            data.price_quad,
            data.child_with_bed_price,
            data.child_no_bed_price,
            data.infant_price,
        ].some((v) => v !== null && v !== undefined);
        const hasFlights = (data.flights ?? []).some(
            (f) => f.from || f.to || f.airline,
        );
        const hasTransportation = (data.transportation_plans ?? []).length > 0;
        const hasVisa = Boolean(data.visa_type);
        const hasVehicle =
            Boolean(data.vehicle_type) ||
            Boolean(data.vehicle_driver_name) ||
            Boolean(data.vehicle_driver_contact_number);
        const hasTrainTickets =
            Boolean(data.ticket_type) || Boolean(data.train_description);
        const hasAccommodations = (data.accommodations ?? []).length > 0;
        const hasRawdah = (data.rawdah_tasreehs ?? []).length > 0;
        const hasOfficials = (data.officials ?? []).length > 0;
        const hasInclusions =
            Boolean(data.included) ||
            Boolean(data.not_included) ||
            Boolean(data.offer);
        const hasRemarks = Boolean(data.remarks);

        return {
            packageInformation: hasErrorsForPrefixes([
                'package_number',
                'name',
                'status',
                'country_id',
                'departure_date',
                'return_date',
                'total_seats',
            ])
                ? 'error'
                : hasPackageInfo
                  ? 'complete'
                  : 'incomplete',
            pricing: hasErrorsForPrefixes([
                'price_single',
                'price_double',
                'price_triple',
                'price_quad',
                'child_with_bed_price',
                'child_no_bed_price',
                'infant_price',
            ])
                ? 'error'
                : hasPricing
                  ? 'complete'
                  : 'incomplete',
            flightDetails: hasErrorsForPrefixes(['flights'])
                ? 'error'
                : hasFlights
                  ? 'complete'
                  : 'incomplete',
            transportationPlan: hasErrorsForPrefixes(['transportation_plans'])
                ? 'error'
                : hasTransportation
                  ? 'complete'
                  : 'incomplete',
            visa: hasErrorsForPrefixes(['visa_type'])
                ? 'error'
                : hasVisa
                  ? 'complete'
                  : 'incomplete',
            vehicle: hasErrorsForPrefixes([
                'vehicle_type',
                'vehicle_driver_name',
                'vehicle_driver_contact_number',
            ])
                ? 'error'
                : hasVehicle
                  ? 'complete'
                  : 'incomplete',
            trainTicketDetails: hasErrorsForPrefixes([
                'ticket_type',
                'train_description',
                'train_tickets',
            ])
                ? 'error'
                : hasTrainTickets
                  ? 'complete'
                  : 'incomplete',
            accommodations: hasErrorsForPrefixes(['accommodations'])
                ? 'error'
                : hasAccommodations
                  ? 'complete'
                  : 'incomplete',
            rawdahTasreeh: hasErrorsForPrefixes(['rawdah_tasreehs'])
                ? 'error'
                : hasRawdah
                  ? 'complete'
                  : 'incomplete',
            officials: hasErrorsForPrefixes(['officials'])
                ? 'error'
                : hasOfficials
                  ? 'complete'
                  : 'incomplete',
            packageInclusions: hasErrorsForPrefixes([
                'included',
                'not_included',
                'offer',
            ])
                ? 'error'
                : hasInclusions
                  ? 'complete'
                  : 'incomplete',
            remarks: hasErrorsForPrefixes(['remarks'])
                ? 'error'
                : hasRemarks
                  ? 'complete'
                  : 'incomplete',
        } as const;
    }, [data, hasErrorsForPrefixes]);

    const sections = useMemo(
        () => [
            {
                id: 'package-information',
                title: 'Package Information',
                status: sectionStatuses.packageInformation,
            },
            {
                id: 'pricing',
                title: 'Pricing',
                status: sectionStatuses.pricing,
            },
            {
                id: 'flight-details',
                title: 'Flight Details',
                status: sectionStatuses.flightDetails,
            },
            {
                id: 'transportation-plan',
                title: 'Transportation Plan',
                status: sectionStatuses.transportationPlan,
            },
            {
                id: 'visa',
                title: 'Visa',
                status: sectionStatuses.visa,
            },
            {
                id: 'vehicle',
                title: 'Vehicle',
                status: sectionStatuses.vehicle,
            },
            {
                id: 'train-ticket-details',
                title: 'Train Ticket Details',
                status: sectionStatuses.trainTicketDetails,
            },
            {
                id: 'accommodations',
                title: 'Accommodations',
                status: sectionStatuses.accommodations,
            },
            {
                id: 'rawdah-tasreeh',
                title: 'Rawdah Tasreeh',
                status: sectionStatuses.rawdahTasreeh,
            },
            {
                id: 'officials',
                title: 'Officials',
                status: sectionStatuses.officials,
            },
            {
                id: 'package-inclusions',
                title: 'Package Inclusions',
                status: sectionStatuses.packageInclusions,
            },
            {
                id: 'remarks',
                title: 'Remarks',
                status: sectionStatuses.remarks,
            },
        ],
        [sectionStatuses],
    );

    const handleSectionClick = useCallback(
        (sectionId: string) => {
            navigateToSection(sectionId, setOpenSections);
        },
        [],
    );

    const errorSummaryItems = useMemo(() => {
        return Object.entries(errors)
            .filter(
                ([, message]) =>
                    typeof message === 'string' && message.trim().length > 0,
            )
            .map(([path, message]) => ({
                path,
                message: String(message),
                label: toFieldLabel(path),
            }));
    }, [errors, toFieldLabel]);

    useEffect(() => {
        if (errorSummaryItems.length > 0 && !isView) {
            scrollToErrorBanner();
            const errorSectionIds = sections
                .filter((s) => s.status === 'error')
                .map((s) => s.id);
            if (errorSectionIds.length > 0) {
                setOpenSections((prev) => [
                    ...new Set([...prev, ...errorSectionIds]),
                ]);
            }
        }
    }, [errorSummaryItems.length, isView, scrollToErrorBanner, sections]);

    useEffect(() => {
        if (!isCreate || !prefillData) {
            return;
        }

        const prefillSignature = JSON.stringify(prefillData);

        if (lastAppliedPrefillRef.current === prefillSignature) {
            return;
        }

        setData((currentData) => ({
            ...currentData,
            ...prefillData,
        }));
        lastAppliedPrefillRef.current = prefillSignature;
    }, [isCreate, prefillData, setData]);

    useEffect(() => {
        const ticketType = data.ticket_type;

        if (ticketType !== 'one_way' && ticketType !== 'two_way') {
            if ((data.train_tickets ?? []).length > 0) {
                setData('train_tickets', []);
            }

            return;
        }

        const desiredCount = ticketType === 'two_way' ? 2 : 1;

        setData((currentData) => {
            const currentTickets = currentData.train_tickets ?? [];

            if (currentTickets.length === desiredCount) {
                return currentData;
            }

            const departureDate = String(
                currentData.departure_date ?? '',
            ).trim();
            const defaultTravelDate =
                departureDate.length > 0 ? departureDate : '';

            const ensureTicketDate = (
                ticket: TrainTicketSchema,
            ): TrainTicketSchema => {
                if (String(ticket.travel_date ?? '').trim().length > 0) {
                    return ticket;
                }

                return {
                    ...ticket,
                    travel_date: defaultTravelDate,
                };
            };

            const nextTickets =
                desiredCount === 1
                    ? [
                          ensureTicketDate(
                              currentTickets[0] ?? defaultTrainTickets[0],
                          ),
                      ]
                    : [
                          ensureTicketDate(
                              currentTickets[0] ?? defaultTrainTickets[0],
                          ),
                          ensureTicketDate(
                              currentTickets[1] ?? defaultTrainTickets[1],
                          ),
                      ];

            return {
                ...currentData,
                train_tickets: nextTickets,
            };
        });
    }, [data.ticket_type, data.train_tickets, setData]);

    useEffect(() => {
        const departureDate = String(data.departure_date ?? '').trim();
        const previousDepartureDate = previousDepartureDateRef.current;

        if (!hasInitializedDepartureDateSyncRef.current) {
            previousDepartureDateRef.current = departureDate;
            hasInitializedDepartureDateSyncRef.current = true;

            return;
        }

        if (
            departureDate.length === 0 ||
            departureDate === previousDepartureDate
        ) {
            previousDepartureDateRef.current = departureDate;

            return;
        }

        setData((currentData) => {
            const nextFlights = (currentData.flights ?? []).map((flight) => ({
                ...flight,
                departure_datetime: departureDate,
                arrival_datetime: departureDate,
            }));

            const nextTransportationPlans = (
                currentData.transportation_plans ?? []
            ).map((plan) => ({
                ...plan,
                travel_date: departureDate,
            }));

            const nextTrainTickets = (currentData.train_tickets ?? []).map(
                (ticket) => ({
                    ...ticket,
                    travel_date: departureDate,
                }),
            );

            const nextAccommodations = (currentData.accommodations ?? []).map(
                (accommodation) => ({
                    ...accommodation,
                    check_in: departureDate,
                    check_out: departureDate,
                }),
            );

            const nextRawdahTasreehs = (currentData.rawdah_tasreehs ?? []).map(
                (tasreeh) => ({
                    ...tasreeh,
                    date: departureDate,
                }),
            );

            return {
                ...currentData,
                return_date: departureDate,
                flights: nextFlights,
                transportation_plans: nextTransportationPlans,
                train_tickets: nextTrainTickets,
                accommodations: nextAccommodations,
                rawdah_tasreehs: nextRawdahTasreehs,
            };
        });

        previousDepartureDateRef.current = departureDate;
    }, [data.departure_date, setData]);

    const rawdahWomenPassengers = Number(data.rawdah_member_counts?.women ?? 0);
    const rawdahMenPassengers = Number(data.rawdah_member_counts?.men ?? 0);
    const rawdahTotalPassengers = Number(data.rawdah_member_counts?.total ?? 0);

    function validateClientSide(): boolean {
        clearErrors();
        let valid = true;

        const result = packageValidationSchema.safeParse(data);
        const firstErrorPath = !result.success
            ? result.error.issues[0]?.path.join('.')
            : null;

        if (!result.success) {
            result.error.issues.forEach((issue) => {
                const key = issue.path.join('.');
                if (typeof key === 'string') {
                    setError(key as keyof PackageSchema, issue.message);
                }
            });
            valid = false;

            scrollToErrorBanner();

            if (firstErrorPath) {
                focusErrorField(firstErrorPath);
            }
        }

        return valid;
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!validateClientSide()) return;

        // Dialog mode: pass data back without submitting to server
        if (onSuccess) {
            onSuccess(data);
            return;
        }

        if (isCreate) {
            post(store().url, {
                onError: (serverErrors) => {
                    setError(serverErrors);
                    scrollToErrorBanner();

                    const firstErrorPath = Object.keys(serverErrors)[0];
                    if (firstErrorPath) {
                        focusErrorField(firstErrorPath);
                    }
                },
            });
        } else if (isEdit) {
            put(update(data.id!).url, {
                onError: (serverErrors) => {
                    setError(serverErrors);
                    scrollToErrorBanner();

                    const firstErrorPath = Object.keys(serverErrors)[0];
                    if (firstErrorPath) {
                        focusErrorField(firstErrorPath);
                    }
                },
            });
        }
    }

    const getError = (fieldName: string): string | undefined => {
        return (errors as Record<string, string>)[fieldName];
    };

    const addAccommodation = () => {
        const current = data.accommodations || [];
        const departureDate = String(data.departure_date ?? '').trim();
        const defaultDate = departureDate.length > 0 ? departureDate : '';

        setData('accommodations', [
            ...current,
            {
                location: '',
                hotel_name: '',
                type_of_meal: '',
                first_meal: '',
                last_meal: '',
                ic: '',
                ic_contact_number: '',
                remarks: '',
                check_in: defaultDate,
                check_out: defaultDate,
            },
        ]);
    };

    const removeAccommodation = (index: number) => {
        const current = data.accommodations || [];
        setData(
            'accommodations',
            current.filter((_, i) => i !== index),
        );
    };

    const updateAccommodation = (
        index: number,
        field: keyof AccommodationSchema,
        value: string | number,
    ) => {
        setData((currentData) => {
            const nextAccommodations = [...(currentData.accommodations || [])];
            nextAccommodations[index] = {
                ...nextAccommodations[index],
                [field]: value,
            };

            if (field !== 'hotel_name') {
                return {
                    ...currentData,
                    accommodations: nextAccommodations,
                };
            }

            const changedAccommodation = nextAccommodations[index];
            if (!changedAccommodation) {
                return {
                    ...currentData,
                    accommodations: nextAccommodations,
                };
            }

            const target = resolveAccommodationTarget(
                changedAccommodation,
                index,
            );
            if (target.hotelName.length === 0) {
                return {
                    ...currentData,
                    accommodations: nextAccommodations,
                };
            }

            const updatedOfficials = [...(currentData.officials || [])].map(
                (official) => {
                    const existingHotelMap = normalizeHotelMap(official);
                    const hasValueForTarget = [target.locationKey, target.idKey]
                        .filter((key): key is string => Boolean(key))
                        .some(
                            (key) =>
                                String(existingHotelMap[key] ?? '').trim()
                                    .length > 0,
                        );

                    if (hasValueForTarget) {
                        return official;
                    }

                    const nextHotelMap = {
                        ...existingHotelMap,
                        [target.locationKey]: target.hotelName,
                    };

                    const primaryHotel =
                        Object.values(nextHotelMap).find((hotel) => {
                            return String(hotel).trim().length > 0;
                        }) ?? '';

                    return {
                        ...official,
                        hotel: primaryHotel || null,
                        hotel_map: nextHotelMap,
                    };
                },
            );

            return {
                ...currentData,
                accommodations: nextAccommodations,
                officials: updatedOfficials,
            };
        });
    };

    // --- Flight helpers ---
    const addFlight = () => {
        const current = data.flights || [];
        const departureDate = String(data.departure_date ?? '').trim();
        const defaultDate = departureDate.length > 0 ? departureDate : '';

        setData('flights', [
            ...current,
            {
                from: '',
                to: '',
                description: '',
                airline: '',
                pnr: '',
                departure_datetime: defaultDate,
                arrival_datetime: defaultDate,
                remarks: '',
            },
        ]);
    };

    const removeFlight = (index: number) => {
        const current = data.flights || [];
        setData(
            'flights',
            current.filter((_, i) => i !== index),
        );
    };

    const updateFlight = (
        index: number,
        field: keyof FlightSchema,
        value: string | number | null,
    ) => {
        const current = [...(data.flights || [])];
        current[index] = { ...current[index], [field]: value };
        setData('flights', current);
    };

    // --- Train ticket helpers ---
    const updateTrainTicket = (
        index: number,
        field: keyof TrainTicketSchema,
        value: string | number | null,
    ) => {
        const current = [...(data.train_tickets || [])];
        current[index] = { ...current[index], [field]: value };
        setData('train_tickets', current);
    };

    // --- Transportation plan helpers ---
    const addTransportationPlan = () => {
        const current = data.transportation_plans || [];
        const departureDate = String(data.departure_date ?? '').trim();
        const defaultDate = departureDate.length > 0 ? departureDate : '';

        setData('transportation_plans', [
            ...current,
            {
                from: '',
                to: '',
                travel_date: defaultDate,
                travel_time: '',
                remarks: '',
            },
        ]);
    };

    const removeTransportationPlan = (index: number) => {
        const current = data.transportation_plans || [];
        setData(
            'transportation_plans',
            current.filter((_, i) => i !== index),
        );
    };

    const updateTransportationPlan = (
        index: number,
        field: keyof TransportationPlanSchema,
        value: string | number | null,
    ) => {
        const current = [...(data.transportation_plans || [])];
        current[index] = { ...current[index], [field]: value };
        setData('transportation_plans', current);
    };

    // --- Rawdah tasreeh helpers ---
    const addRawdahTasreeh = () => {
        const current = data.rawdah_tasreehs || [];
        const departureDate = String(data.departure_date ?? '').trim();
        const defaultDate = departureDate.length > 0 ? departureDate : '';

        setData('rawdah_tasreehs', [
            ...current,
            {
                date: defaultDate,
                women_passengers: rawdahWomenPassengers,
                women_time: '',
                men_passengers: rawdahMenPassengers,
                men_time: '',
                remarks: '',
            },
        ]);
    };

    const removeRawdahTasreeh = (index: number) => {
        const current = data.rawdah_tasreehs || [];
        setData(
            'rawdah_tasreehs',
            current.filter((_, i) => i !== index),
        );
    };

    const updateRawdahTasreeh = (
        index: number,
        field: keyof RawdahTasreehSchema,
        value: string | number | null,
    ) => {
        const current = [...(data.rawdah_tasreehs || [])];
        current[index] = { ...current[index], [field]: value };
        setData('rawdah_tasreehs', current);
    };

    // --- Official helpers ---
    const addOfficial = () => {
        const current = data.officials || [];
        const initialHotelMap = buildInitialOfficialHotelMap(
            data.accommodations || [],
        );
        const primaryHotel =
            Object.values(initialHotelMap).find((hotel) => {
                return String(hotel).trim().length > 0;
            }) ?? '';

        setData('officials', [
            ...current,
            {
                type: '',
                name: '',
                hotel: primaryHotel || null,
                hotel_map: initialHotelMap,
                contact_number: '',
                nationality: '',
                passport_number: '',
                gender: '',
                date_of_birth: '',
                passport_issue_date: '',
                passport_expiry_date: '',
                passport_place_of_issue: '',
                place_of_birth: '',
            },
        ]);
    };

    const removeOfficial = (index: number) => {
        const current = data.officials || [];
        setData(
            'officials',
            current.filter((_, i) => i !== index),
        );
    };

    const updateOfficial = (
        index: number,
        field: keyof OfficialSchema,
        value: string | number | null | Record<string, string>,
    ) => {
        const current = [...(data.officials || [])];
        current[index] = { ...current[index], [field]: value };
        setData('officials', current);
    };

    const resolveAccommodationTarget = useCallback(
        (accommodation: AccommodationSchema, index: number) => {
            const normalizedLocation = String(
                accommodation.location ?? '',
            ).trim();
            const locationKey = normalizedLocation
                ? `location:${normalizedLocation.toLowerCase()}`
                : `location:index-${index}`;
            const idKey =
                accommodation.id !== undefined && accommodation.id !== null
                    ? String(accommodation.id)
                    : null;
            const hotelName = String(accommodation.hotel_name ?? '').trim();

            return {
                locationKey,
                idKey,
                hotelName,
            };
        },
        [],
    );

    const buildInitialOfficialHotelMap = useCallback(
        (accommodations: AccommodationSchema[]): Record<string, string> => {
            return accommodations.reduce<Record<string, string>>(
                (carry, accommodation, index) => {
                    const target = resolveAccommodationTarget(
                        accommodation,
                        index,
                    );

                    if (target.hotelName.length === 0) {
                        return carry;
                    }

                    carry[target.locationKey] = target.hotelName;

                    return carry;
                },
                {},
            );
        },
        [resolveAccommodationTarget],
    );

    const accommodationHotelTargets = useMemo(() => {
        return (data.accommodations || []).map((accommodation, index) => {
            const normalizedLocation = String(
                accommodation.location ?? '',
            ).trim();
            const target = resolveAccommodationTarget(accommodation, index);

            return {
                label:
                    normalizedLocation.length > 0
                        ? normalizedLocation
                        : `Accommodation ${index + 1}`,
                hotelName: target.hotelName,
                primaryKey: target.locationKey,
                matchingKeys: target.idKey
                    ? [target.locationKey, target.idKey]
                    : [target.locationKey],
            };
        });
    }, [data.accommodations, resolveAccommodationTarget]);

    const normalizeHotelMap = useCallback(
        (official: OfficialSchema): Record<string, string> => {
            const rawMap = official.hotel_map;
            if (!rawMap || typeof rawMap !== 'object') {
                return {};
            }

            return Object.entries(rawMap).reduce<Record<string, string>>(
                (carry, [key, value]) => {
                    const normalizedKey = String(key).trim();
                    const normalizedValue = String(value ?? '').trim();

                    if (
                        normalizedKey.length === 0 ||
                        normalizedValue.length === 0
                    ) {
                        return carry;
                    }

                    carry[normalizedKey] = normalizedValue;

                    return carry;
                },
                {},
            );
        },
        [],
    );

    const getOfficialHotelByTarget = useCallback(
        (official: OfficialSchema, matchingKeys: string[]): string => {
            const hotelMap = normalizeHotelMap(official);
            const matchedKey = matchingKeys.find((key) => {
                return String(key).trim().length > 0 && !!hotelMap[key];
            });

            if (matchedKey) {
                return hotelMap[matchedKey] ?? '';
            }

            return '';
        },
        [normalizeHotelMap],
    );

    const updateOfficialHotelByTarget = useCallback(
        (officialIndex: number, targetKey: string, nextHotelValue: string) => {
            setData((currentData) => {
                const currentOfficials = [...(currentData.officials || [])];
                const targetOfficial = currentOfficials[officialIndex];

                if (!targetOfficial) {
                    return currentData;
                }

                const existingHotelMap = normalizeHotelMap(targetOfficial);
                const normalizedTargetKey = String(targetKey).trim();
                const normalizedHotelValue = String(nextHotelValue).trim();
                const nextHotelMap = { ...existingHotelMap };

                if (normalizedHotelValue.length === 0) {
                    delete nextHotelMap[normalizedTargetKey];
                } else {
                    nextHotelMap[normalizedTargetKey] = normalizedHotelValue;
                }

                const primaryHotel =
                    Object.values(nextHotelMap).find((hotel) => {
                        return String(hotel).trim().length > 0;
                    }) ?? '';

                currentOfficials[officialIndex] = {
                    ...targetOfficial,
                    hotel: primaryHotel || null,
                    hotel_map: nextHotelMap,
                };

                return {
                    ...currentData,
                    officials: currentOfficials,
                };
            });
        },
        [normalizeHotelMap, setData],
    );

    const toStartOfDay = (value?: string | null): Date | null => {
        const parsed = parseDisplayDate(value);

        if (!parsed) {
            return null;
        }

        const normalized = new Date(parsed);
        normalized.setHours(0, 0, 0, 0);

        return normalized;
    };

    const disabledReturnDates = (date: Date): boolean => {
        if (isBeforeToday(date)) {
            return true;
        }

        const departureDate = toStartOfDay(data.departure_date);
        if (!departureDate) {
            return false;
        }

        const candidateDate = new Date(date);
        candidateDate.setHours(0, 0, 0, 0);

        return candidateDate < departureDate;
    };

    const occupiedSeats = Number(data.occupied_seats ?? 0);
    const visaTypeOptionValues = useMemo(() => {
        return new Set(
            packageVisaTypeOptions.map((option) =>
                String(option.value).toLowerCase(),
            ),
        );
    }, []);
    const normalizedVisaType = String(data.visa_type ?? '').trim();
    const isVisaTypeCustom =
        normalizedVisaType.length > 0 &&
        !visaTypeOptionValues.has(normalizedVisaType.toLowerCase());
    const visaTypeSelectValue = isVisaTypeCustom
        ? '__custom__'
        : normalizedVisaType;

    return (
        <div className="mx-auto w-full">
            <form onSubmit={submit} className="space-y-4">
                {/* Error Alert */}
                {errorSummaryItems.length > 0 && !isView && (
                    <div ref={errorBannerRef}>
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>
                                <div className="space-y-2">
                                    <p>
                                        Please fix the errors below and try
                                        again.
                                    </p>
                                    <ul className="list-disc space-y-1 pl-5">
                                        {errorSummaryItems.map((item) => (
                                            <li
                                                key={`${item.path}:${item.message}`}
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        focusErrorField(
                                                            item.path,
                                                        )
                                                    }
                                                    className="text-left underline underline-offset-2"
                                                >
                                                    {item.label}: {item.message}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </AlertDescription>
                        </Alert>
                    </div>
                )}

                {!isView && (
                    <FormProgressHeader
                        title="Package"
                        sections={sections}
                        onSectionClick={handleSectionClick}
                    />
                )}

                <Accordion
                    type="multiple"
                    value={openSections}
                    onValueChange={setOpenSections}
                    className="space-y-4"
                >
                {/* Package Information */}
                <FormSection
                    value="package-information"
                    title="Package Information"
                    description="Define the package identity and status."
                    status={sectionStatuses.packageInformation}
                >
                    <div className="space-y-6">
                        <div
                            className={`grid grid-cols-1 items-start gap-4 md:grid-cols-4`}
                        >
                            <ModelNumberInput
                                modelKey="package"
                                label="Package Number"
                                value={data.package_number ?? ''}
                                formatId={data.package_number_format_id ?? null}
                                onValueChange={(nextValue) =>
                                    setData('package_number', nextValue)
                                }
                                onFormatIdChange={(nextFormatId) =>
                                    setData(
                                        'package_number_format_id',
                                        nextFormatId,
                                    )
                                }
                                disabled={processing || isView}
                                error={getError('package_number')}
                            />

                            {/* Package Name */}
                            <FormField
                                label="Package Name"
                                htmlFor="name"
                                fieldRequirementsProps={{
                                    required: true,
                                    hint: 'Enter the name of the package',
                                }}
                                error={getError('name')}
                            >
                                <ProperInput
                                    id="name"
                                    value={data.name ?? ''}
                                    disabled={isView || processing}
                                    onCommit={(v) => setData('name', v)}
                                    placeholder="Enter package name"
                                />
                            </FormField>

                            {/* Status */}
                            <FormField
                                label="Status"
                                htmlFor="status"
                                fieldRequirementsProps={{
                                    required: true,
                                    hint: 'Select package status',
                                }}
                                error={getError('status')}
                            >
                                <Select
                                    disabled={isView || processing}
                                    value={data.status}
                                    onValueChange={(value) =>
                                        setData('status', value)
                                    }
                                >
                                    <SelectTrigger id="status">
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="open">
                                            Open
                                        </SelectItem>
                                        <SelectItem value="full" disabled>
                                            Full (Auto)
                                        </SelectItem>
                                        <SelectItem value="closed">
                                            Closed
                                        </SelectItem>
                                        <SelectItem value="completed" disabled>
                                            Completed (Auto)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                label="Package Location"
                                fieldRequirementsProps={{
                                    hint: 'Location scope for this package',
                                }}
                                error={getError('country_id')}
                            >
                                <ProperInputSelect
                                    disabled={
                                        isView ||
                                        processing ||
                                        lockCountrySelection ||
                                        (isEdit && hasPersistedPackageLocation)
                                    }
                                    options={countries}
                                    value={data.country_id ?? ''}
                                    onValueChange={(value) =>
                                        setData('country_id', String(value))
                                    }
                                    placeholder="Select package location"
                                />
                            </FormField>
                        </div>

                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                            <FormField
                                label="Departure Date"
                                fieldRequirementsProps={{
                                    hint: 'Select departure date',
                                }}
                                error={getError('departure_date')}
                            >
                                <DatePickerField
                                    id="departure_date"
                                    value={data.departure_date || ''}
                                    fromYear={new Date().getFullYear()}
                                    toYear={new Date().getFullYear() + 5}
                                    disabled={isView || processing}
                                    onChange={(v) =>
                                        setData('departure_date', v)
                                    }
                                />
                            </FormField>
                            <FormField
                                label="Return Date"
                                fieldRequirementsProps={{
                                    hint: 'Select return date',
                                }}
                                error={getError('return_date')}
                            >
                                <DatePickerField
                                    id="return_date"
                                    value={data.return_date || ''}
                                    fromYear={new Date().getFullYear()}
                                    toYear={new Date().getFullYear() + 5}
                                    disabled={isView || processing}
                                    disabledDates={disabledReturnDates}
                                    onChange={(v) => setData('return_date', v)}
                                />
                            </FormField>
                            <FormField
                                label="Total Seats"
                                htmlFor="total_seats"
                                fieldRequirementsProps={{
                                    required: true,
                                    hint: `Active paid members: ${occupiedSeats} (officials excluded)`,
                                }}
                                error={getError('total_seats')}
                            >
                                <ProperInput
                                    id="total_seats"
                                    type="number"
                                    value={data.total_seats ?? ''}
                                    disabled={isView || processing}
                                    onCommit={(v) => {
                                        const val = v ? parseInt(v) : null;
                                        const seatsLeft =
                                            val === null
                                                ? null
                                                : Math.max(
                                                      0,
                                                      val - occupiedSeats,
                                                  );

                                        setData((prev) => ({
                                            ...prev,
                                            total_seats: val,
                                            seats_left: seatsLeft,
                                        }));
                                    }}
                                    inputProps={{ min: '1' }}
                                    placeholder="Enter total seats"
                                />
                            </FormField>
                            <FormField
                                label="Seats Left"
                                htmlFor="seats_left"
                                fieldRequirementsProps={{
                                    hint: 'Auto-filled from total seats',
                                }}
                                error={getError('seats_left')}
                            >
                                <Input
                                    id="seats_left"
                                    type="number"
                                    value={data.seats_left ?? ''}
                                    disabled={true}
                                    className="bg-muted"
                                />
                            </FormField>
                        </div>
                    </div>
                </FormSection>

                {/* Pricing */}
                <FormSection
                    value="pricing"
                    title="Pricing"
                    description="Set occupancy and child/infant pricing."
                    status={sectionStatuses.pricing}
                    required={false}
                >
                    <div className="space-y-6">
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                            {sharingPlanPriceLabels.map(({ key, label }) => (
                                <FormField
                                    key={key}
                                    label={label}
                                    htmlFor={key}
                                    fieldRequirementsProps={{
                                        hint: 'Enter price amount',
                                    }}
                                    error={getError(key)}
                                >
                                    <ProperInput
                                        id={key}
                                        type="number"
                                        value={
                                            data[
                                                key as keyof PackageSchema
                                            ] as number
                                        }
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                key as keyof PackageSchema,
                                                parseFloat(v),
                                            )
                                        }
                                        inputProps={{
                                            min: '0',
                                            step: '0.01',
                                        }}
                                        placeholder="0"
                                    />
                                </FormField>
                            ))}
                        </div>

                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                            {infantAndChildPriceLabels.map(({ key, label }) => (
                                <FormField
                                    key={key}
                                    label={label}
                                    htmlFor={key}
                                    fieldRequirementsProps={{
                                        hint: 'Enter price amount',
                                    }}
                                    error={getError(key)}
                                >
                                    <ProperInput
                                        id={key}
                                        type="number"
                                        value={
                                            data[
                                                key as keyof PackageSchema
                                            ] as number
                                        }
                                        disabled={isView || processing}
                                        onCommit={(v) =>
                                            setData(
                                                key as keyof PackageSchema,
                                                parseFloat(v),
                                            )
                                        }
                                        inputProps={{
                                            min: '0',
                                            step: '0.01',
                                        }}
                                        placeholder="0"
                                    />
                                </FormField>
                            ))}
                        </div>
                    </div>
                </FormSection>

                {/* Flight Details */}
                <FormSection
                    value="flight-details"
                    title="Flight Details"
                    description="Add flight information for this package."
                    status={sectionStatuses.flightDetails}
                    required={false}
                >
                        {(data.flights || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No flights added yet. Click "Add Flight" to add
                                flight details.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.flights || []).map((flight, index) => (
                                    <div
                                        key={index}
                                        className="space-y-4 rounded-lg border p-4"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-semibold">
                                                Flight {index + 1}
                                                {flight.description
                                                    ? ` — ${flight.description}`
                                                    : ''}
                                            </span>
                                            {!isView && (
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() =>
                                                        removeFlight(index)
                                                    }
                                                    disabled={processing}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                                            <FormField
                                                label="Description"
                                                fieldRequirementsProps={{
                                                    hint: 'e.g., Departure, Return, Transit',
                                                }}
                                                error={getError(
                                                    `flights.${index}.description`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={
                                                        flight.description ?? ''
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'description',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="e.g., Departure"
                                                />
                                            </FormField>
                                            <FormField
                                                label="From"
                                                htmlFor={`flights_${index}_from`}
                                                fieldRequirementsProps={{
                                                    required: true,
                                                    hint: 'Departure location',
                                                }}
                                                error={getError(
                                                    `flights.${index}.from`,
                                                )}
                                            >
                                                <ProperInput
                                                    id={`flights_${index}_from`}
                                                    value={flight.from ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'from',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="e.g., KLIA"
                                                />
                                            </FormField>
                                            <FormField
                                                label="To"
                                                htmlFor={`flights_${index}_to`}
                                                fieldRequirementsProps={{
                                                    required: true,
                                                    hint: 'Arrival location',
                                                }}
                                                error={getError(
                                                    `flights.${index}.to`,
                                                )}
                                            >
                                                <ProperInput
                                                    id={`flights_${index}_to`}
                                                    value={flight.to ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'to',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="e.g., Jeddah"
                                                />
                                            </FormField>
                                        </div>
                                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                                            <FormField
                                                label="Airline"
                                                fieldRequirementsProps={{
                                                    hint: 'Enter airline name',
                                                }}
                                                error={getError(
                                                    `flights.${index}.airline`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={flight.airline ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'airline',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="e.g., Saudi Airlines"
                                                />
                                            </FormField>
                                            <FormField
                                                label="PNR"
                                                fieldRequirementsProps={{
                                                    hint: 'Passenger Name Record',
                                                }}
                                                error={getError(
                                                    `flights.${index}.pnr`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={flight.pnr ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    onCommit={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'pnr',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="Enter PNR"
                                                />
                                            </FormField>
                                            <FormField
                                                label="Departure"
                                                fieldRequirementsProps={{
                                                    hint: 'Departure date & time',
                                                }}
                                                error={getError(
                                                    `flights.${index}.departure_datetime`,
                                                )}
                                            >
                                                <DatePickerField
                                                    id={`flight_departure_${index}`}
                                                    key={`flight_departure_${index}`}
                                                    value={
                                                        flight.departure_datetime ||
                                                        ''
                                                    }
                                                    fromYear={new Date().getFullYear()}
                                                    toYear={
                                                        new Date().getFullYear() +
                                                        5
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    useTime
                                                    onChange={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'departure_datetime',
                                                            v || null,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                            <FormField
                                                label="Arrival"
                                                fieldRequirementsProps={{
                                                    hint: 'Arrival date & time',
                                                }}
                                                error={getError(
                                                    `flights.${index}.arrival_datetime`,
                                                )}
                                            >
                                                <DatePickerField
                                                    id={`flight_arrival_${index}`}
                                                    key={`flight_arrival_${index}`}
                                                    value={
                                                        flight.arrival_datetime ||
                                                        ''
                                                    }
                                                    fromYear={new Date().getFullYear()}
                                                    toYear={
                                                        new Date().getFullYear() +
                                                        5
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    useTime
                                                    onChange={(v) =>
                                                        updateFlight(
                                                            index,
                                                            'arrival_datetime',
                                                            v || null,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                        <FormField
                                            label="Remarks"
                                            error={getError(
                                                `flights.${index}.remarks`,
                                            )}
                                        >
                                            <ProperInput
                                                value={flight.remarks ?? ''}
                                                disabled={isView || processing}
                                                textarea
                                                onCommit={(v) =>
                                                    updateFlight(
                                                        index,
                                                        'remarks',
                                                        v || null,
                                                    )
                                                }
                                                placeholder="Optional remarks"
                                            />
                                        </FormField>
                                    </div>
                                ))}
                            </div>
                        )}
                    {!isView && (
                        <Button
                            type="button"
                            variant="default"
                            className="mt-4 w-full sm:w-auto"
                            onClick={addFlight}
                            disabled={processing}
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            Add Flight
                        </Button>
                    )}
                </FormSection>

                {/* Transportation Plan */}
                <FormSection
                    value="transportation-plan"
                    title="Transportation Plan"
                    description="Add transportation arrangements for this package."
                    status={sectionStatuses.transportationPlan}
                    required={false}
                >
                        {(data.transportation_plans || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No transportation plans added yet. Click "Add
                                Transportation" to add details.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.transportation_plans || []).map(
                                    (plan, index) => (
                                        <div
                                            key={index}
                                            className="space-y-4 rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold">
                                                    Transportation {index + 1}
                                                </span>
                                                {!isView && (
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeTransportationPlan(
                                                                index,
                                                            )
                                                        }
                                                        disabled={processing}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="From"
                                                    fieldRequirementsProps={{
                                                        hint: 'Departure location',
                                                    }}
                                                    error={getError(
                                                        `transportation_plans.${index}.from`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={plan.from ?? ''}
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTransportationPlan(
                                                                index,
                                                                'from',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Airport"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="To"
                                                    fieldRequirementsProps={{
                                                        hint: 'Arrival location',
                                                    }}
                                                    error={getError(
                                                        `transportation_plans.${index}.to`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={plan.to ?? ''}
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTransportationPlan(
                                                                index,
                                                                'to',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Hotel"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Date"
                                                    fieldRequirementsProps={{
                                                        hint: 'Travel date',
                                                    }}
                                                    error={getError(
                                                        `transportation_plans.${index}.travel_date`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`transport_date_${index}`}
                                                        key={`transport_date_${index}`}
                                                        value={
                                                            plan.travel_date ||
                                                            ''
                                                        }
                                                        fromYear={new Date().getFullYear()}
                                                        toYear={
                                                            new Date().getFullYear() +
                                                            5
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onChange={(v) =>
                                                            updateTransportationPlan(
                                                                index,
                                                                'travel_date',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Time"
                                                    fieldRequirementsProps={{
                                                        hint: 'Travel time',
                                                    }}
                                                    error={getError(
                                                        `transportation_plans.${index}.travel_time`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        type="time"
                                                        timeFormat={24}
                                                        value={
                                                            plan.travel_time ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTransportationPlan(
                                                                index,
                                                                'travel_time',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>
                                            <FormField
                                                label="Remarks"
                                                fieldRequirementsProps={{
                                                    hint: 'Optional notes',
                                                }}
                                                error={getError(
                                                    `transportation_plans.${index}.remarks`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={plan.remarks ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    textarea
                                                    onCommit={(v) =>
                                                        updateTransportationPlan(
                                                            index,
                                                            'remarks',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="Optional remarks"
                                                />
                                            </FormField>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    {!isView && (
                        <Button
                            type="button"
                            variant="default"
                            className="mt-4 w-full sm:w-auto"
                            onClick={addTransportationPlan}
                            disabled={processing}
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            Add Transportation
                        </Button>
                    )}
                </FormSection>

                {/* Visa */}
                <FormSection
                    value="visa"
                    title="Visa"
                    description="Capture visa details for this package."
                    status={sectionStatuses.visa}
                    required={false}
                >
                    <div className="space-y-6">
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-1">
                            <FormField
                                label="Visa Type"
                                htmlFor="visa_type"
                                fieldRequirementsProps={{
                                    hint: 'Select visa type or enter a custom value',
                                }}
                                error={getError('visa_type')}
                            >
                                <div className="space-y-3">
                                    <ProperInputSelect
                                        id="visa_type"
                                        options={packageVisaTypeOptions}
                                        value={visaTypeSelectValue}
                                        disabled={isView || processing}
                                        onValueChange={(v) => {
                                            const nextValue = String(v || '');
                                            setData(
                                                'visa_type',
                                                nextValue.length > 0
                                                    ? nextValue
                                                    : null,
                                            );
                                        }}
                                        placeholder="Select visa type"
                                    />
                                    {visaTypeSelectValue === '__custom__' && (
                                        <ProperInput
                                            id="visa_type_custom"
                                            value={
                                                isVisaTypeCustom
                                                    ? normalizedVisaType
                                                    : ''
                                            }
                                            disabled={isView || processing}
                                            onCommit={(v) =>
                                                setData('visa_type', v || null)
                                            }
                                            placeholder="Enter custom visa type"
                                        />
                                    )}
                                </div>
                            </FormField>
                        </div>
                    </div>
                </FormSection>

                {/* Vehicle */}
                <FormSection
                    value="vehicle"
                    title="Vehicle"
                    description="Capture vehicle details for this package."
                    status={sectionStatuses.vehicle}
                    required={false}
                >
                    <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                        <FormField
                            label="Vehicle Type"
                            htmlFor="vehicle_type"
                            fieldRequirementsProps={{
                                hint: 'Enter vehicle type',
                            }}
                            error={getError('vehicle_type')}
                        >
                            <ProperInput
                                id="vehicle_type"
                                value={data.vehicle_type || ''}
                                disabled={isView || processing}
                                onCommit={(v) =>
                                    setData('vehicle_type', v || null)
                                }
                                placeholder="e.g., Bus 45 Seater"
                            />
                        </FormField>
                        <FormField
                            label="Driver Name"
                            htmlFor="vehicle_driver_name"
                            fieldRequirementsProps={{
                                hint: 'Enter driver name',
                            }}
                            error={getError('vehicle_driver_name')}
                        >
                            <ProperInput
                                id="vehicle_driver_name"
                                value={data.vehicle_driver_name || ''}
                                disabled={isView || processing}
                                onCommit={(v) =>
                                    setData(
                                        'vehicle_driver_name',
                                        v || null,
                                    )
                                }
                                placeholder="Enter driver name"
                            />
                        </FormField>
                        <FormField
                            label="Driver Contact Number"
                            htmlFor="vehicle_driver_contact_number"
                            fieldRequirementsProps={{
                                hint: 'Enter driver contact number',
                            }}
                            error={getError(
                                'vehicle_driver_contact_number',
                            )}
                        >
                            <ProperInput
                                id="vehicle_driver_contact_number"
                                value={
                                    data.vehicle_driver_contact_number || ''
                                }
                                disabled={isView || processing}
                                onCommit={(v) =>
                                    setData(
                                        'vehicle_driver_contact_number',
                                        v || null,
                                    )
                                }
                                placeholder="Enter contact number"
                            />
                        </FormField>
                    </div>
                </FormSection>

                {/* Train Ticket Details */}
                <FormSection
                    value="train-ticket-details"
                    title="Train Ticket Details"
                    description="Provide train ticket itinerary details."
                    status={sectionStatuses.trainTicketDetails}
                    required={false}
                >
                    <div className="space-y-6">
                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-1">
                            <FormField
                                label="Train Description"
                                htmlFor="train_description"
                                fieldRequirementsProps={{
                                    hint: 'General notes shown for train operations',
                                }}
                                error={getError('train_description')}
                            >
                                <ProperInput
                                    id="train_description"
                                    value={data.train_description || ''}
                                    disabled={isView || processing}
                                    textarea
                                    onCommit={(v) =>
                                        setData('train_description', v || null)
                                    }
                                    placeholder="Enter train operation description"
                                />
                            </FormField>
                            <FormField
                                label="Train Ticket Type"
                                htmlFor="ticket_type"
                                fieldRequirementsProps={{
                                    hint: 'Select a train ticket type to add itinerary details.',
                                }}
                                error={getError('ticket_type')}
                            >
                                <ProperInputSelect
                                    options={packageTrainTicketTypeOptions}
                                    value={data.ticket_type || ''}
                                    disabled={isView || processing}
                                    onValueChange={(v) =>
                                        setData('ticket_type', String(v || ''))
                                    }
                                    placeholder="Select ticket type"
                                />
                            </FormField>
                        </div>

                        {(data.train_tickets || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                Select a train ticket type to add itinerary
                                details.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.train_tickets || []).map(
                                    (ticket, index) => (
                                        <div
                                            key={index}
                                            className="space-y-4 rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold">
                                                    Train Trip {index + 1}
                                                </span>
                                            </div>
                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="From"
                                                    fieldRequirementsProps={{
                                                        hint: 'Departure location',
                                                    }}
                                                    error={getError(
                                                        `train_tickets.${index}.from`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            ticket.from ?? ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTrainTicket(
                                                                index,
                                                                'from',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Makkah"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="To"
                                                    fieldRequirementsProps={{
                                                        hint: 'Arrival location',
                                                    }}
                                                    error={getError(
                                                        `train_tickets.${index}.to`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={ticket.to ?? ''}
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTrainTicket(
                                                                index,
                                                                'to',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Madinah"
                                                    />
                                                </FormField>
                                            </div>
                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="Date"
                                                    fieldRequirementsProps={{
                                                        hint: 'Travel date',
                                                    }}
                                                    error={getError(
                                                        `train_tickets.${index}.travel_date`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`train_date_${index}`}
                                                        key={`train_date_${index}`}
                                                        value={
                                                            ticket.travel_date ||
                                                            ''
                                                        }
                                                        fromYear={new Date().getFullYear()}
                                                        toYear={
                                                            new Date().getFullYear() +
                                                            5
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onChange={(v) =>
                                                            updateTrainTicket(
                                                                index,
                                                                'travel_date',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Time"
                                                    fieldRequirementsProps={{
                                                        hint: 'Travel time',
                                                    }}
                                                    error={getError(
                                                        `train_tickets.${index}.travel_time`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        type="time"
                                                        timeFormat={24}
                                                        value={
                                                            ticket.travel_time ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateTrainTicket(
                                                                index,
                                                                'travel_time',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>
                                            <FormField
                                                label="Remarks"
                                                fieldRequirementsProps={{
                                                    hint: 'Optional notes',
                                                }}
                                                error={getError(
                                                    `train_tickets.${index}.remarks`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={ticket.remarks ?? ''}
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    textarea
                                                    onCommit={(v) =>
                                                        updateTrainTicket(
                                                            index,
                                                            'remarks',
                                                            v || null,
                                                        )
                                                    }
                                                    placeholder="Optional remarks"
                                                />
                                            </FormField>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    </div>
                </FormSection>

                {/* Accommodations */}
                <FormSection
                    value="accommodations"
                    title="Accommodations"
                    description="Add accommodation entries for each location."
                    status={sectionStatuses.accommodations}
                    required={false}
                >
                        {(data.accommodations || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No accommodations added yet. Click "Add
                                Accommodation" to add places like Makkah,
                                Madinah, Taif, etc.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.accommodations || []).map(
                                    (accommodation, index) => (
                                        <div
                                            key={index}
                                            className="space-y-4 rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold">
                                                    Accommodation {index + 1}
                                                    {accommodation.location
                                                        ? ` - ${accommodation.location}`
                                                        : ''}
                                                </span>
                                                {!isView && (
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeAccommodation(
                                                                index,
                                                            )
                                                        }
                                                        disabled={processing}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="Location"
                                                    htmlFor={`accommodations_${index}_location`}
                                                    fieldRequirementsProps={{
                                                        required: true,
                                                        hint: 'Enter location',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.location`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        id={`accommodations_${index}_location`}
                                                        value={
                                                            accommodation.location ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'location',
                                                                v,
                                                            )
                                                        }
                                                        placeholder="e.g., Makkah"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Hotel Name"
                                                    htmlFor={`accommodations_${index}_hotel_name`}
                                                    fieldRequirementsProps={{
                                                        required: true,
                                                        hint: 'Enter hotel name',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.hotel_name`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        id={`accommodations_${index}_hotel_name`}
                                                        value={
                                                            accommodation.hotel_name ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'hotel_name',
                                                                v,
                                                            )
                                                        }
                                                        placeholder="Hotel name"
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                                                <FormField
                                                    label="Meal Type"
                                                    fieldRequirementsProps={{
                                                        hint: 'Enter meal type',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.type_of_meal`,
                                                    )}
                                                >
                                                    <ProperInputSelect
                                                        options={
                                                            packageMealPlanOptions
                                                        }
                                                        value={
                                                            accommodation.type_of_meal ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onValueChange={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'type_of_meal',
                                                                String(v || ''),
                                                            )
                                                        }
                                                        placeholder="Select meal plan"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="First Meal"
                                                    fieldRequirementsProps={{
                                                        hint: 'Select first meal',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.first_meal`,
                                                    )}
                                                >
                                                    <ProperInputSelect
                                                        options={
                                                            packageMealTimeOptions
                                                        }
                                                        value={
                                                            accommodation.first_meal ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onValueChange={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'first_meal',
                                                                String(v || ''),
                                                            )
                                                        }
                                                        placeholder="Select first meal"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Last Meal"
                                                    fieldRequirementsProps={{
                                                        hint: 'Select last meal',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.last_meal`,
                                                    )}
                                                >
                                                    <ProperInputSelect
                                                        options={
                                                            packageMealTimeOptions
                                                        }
                                                        value={
                                                            accommodation.last_meal ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onValueChange={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'last_meal',
                                                                String(v || ''),
                                                            )
                                                        }
                                                        placeholder="Select last meal"
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="In Charge"
                                                    fieldRequirementsProps={{
                                                        hint: 'Free-text in charge reference',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.ic`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            accommodation.ic ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'ic',
                                                                v,
                                                            )
                                                        }
                                                        placeholder="Enter IC"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="IC Contact Number"
                                                    fieldRequirementsProps={{
                                                        hint: 'Contact number for this in charge',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.ic_contact_number`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            accommodation.ic_contact_number ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'ic_contact_number',
                                                                v,
                                                            )
                                                        }
                                                        placeholder="Enter IC contact number"
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                <FormField
                                                    label="Check In"
                                                    fieldRequirementsProps={{
                                                        hint: 'Select check-in date',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.check_in`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`acc_check_in_${index}`}
                                                        key={`acc_check_in_${index}`}
                                                        value={
                                                            accommodation.check_in ||
                                                            ''
                                                        }
                                                        fromYear={new Date().getFullYear()}
                                                        toYear={
                                                            new Date().getFullYear() +
                                                            5
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        disabledDates={
                                                            isBeforeToday
                                                        }
                                                        onChange={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'check_in',
                                                                v,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Check Out"
                                                    fieldRequirementsProps={{
                                                        hint: 'Select check-out date',
                                                    }}
                                                    error={getError(
                                                        `accommodations.${index}.check_out`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`acc_check_out_${index}`}
                                                        key={`acc_check_out_${index}`}
                                                        value={
                                                            accommodation.check_out ||
                                                            ''
                                                        }
                                                        fromYear={new Date().getFullYear()}
                                                        toYear={
                                                            new Date().getFullYear() +
                                                            5
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        disabledDates={(
                                                            date,
                                                        ) => {
                                                            if (
                                                                isBeforeToday(
                                                                    date,
                                                                )
                                                            ) {
                                                                return true;
                                                            }

                                                            const checkInDate =
                                                                toStartOfDay(
                                                                    accommodation.check_in,
                                                                );

                                                            if (!checkInDate) {
                                                                return false;
                                                            }

                                                            const candidateDate =
                                                                new Date(date);
                                                            candidateDate.setHours(
                                                                0,
                                                                0,
                                                                0,
                                                                0,
                                                            );

                                                            return (
                                                                candidateDate <=
                                                                checkInDate
                                                            );
                                                        }}
                                                        onChange={(v) =>
                                                            updateAccommodation(
                                                                index,
                                                                'check_out',
                                                                v,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                            </div>

                                            <FormField
                                                label="Remarks"
                                                error={getError(
                                                    `accommodations.${index}.remarks`,
                                                )}
                                            >
                                                <ProperInput
                                                    value={
                                                        accommodation.remarks ??
                                                        ''
                                                    }
                                                    disabled={
                                                        isView || processing
                                                    }
                                                    textarea
                                                    onCommit={(v) =>
                                                        updateAccommodation(
                                                            index,
                                                            'remarks',
                                                            v,
                                                        )
                                                    }
                                                    placeholder="Optional remarks"
                                                />
                                            </FormField>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    {!isView && (
                        <Button
                            type="button"
                            variant="default"
                            className="mt-4 w-full sm:w-auto"
                            onClick={addAccommodation}
                            disabled={processing}
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            Add Accommodation
                        </Button>
                    )}
                </FormSection>

                {/* Rawdah Tasreeh */}
                <FormSection
                    value="rawdah-tasreeh"
                    title="Rawdah Tasreeh"
                    description="Add Rawdah tasreeh schedules and passenger counts."
                    status={sectionStatuses.rawdahTasreeh}
                    required={false}
                >
                        <div className="mb-4 grid grid-cols-1 gap-4 rounded-lg border border-dashed p-4 md:grid-cols-3">
                            <FormField
                                label="Women (Member Info)"
                                fieldRequirementsProps={{
                                    hint: 'Current women passenger count from manifest members for comparison.',
                                }}
                            >
                                <Input
                                    type="number"
                                    value={rawdahWomenPassengers}
                                    disabled={true}
                                    className="bg-muted"
                                />
                            </FormField>
                            <FormField
                                label="Men (Member Info)"
                                fieldRequirementsProps={{
                                    hint: 'Current men passenger count from manifest members for comparison.',
                                }}
                            >
                                <Input
                                    type="number"
                                    value={rawdahMenPassengers}
                                    disabled={true}
                                    className="bg-muted"
                                />
                            </FormField>
                            <FormField
                                label="Total (Member Info)"
                                fieldRequirementsProps={{
                                    hint: 'Current total passenger count from manifest members for comparison.',
                                }}
                            >
                                <Input
                                    type="number"
                                    value={rawdahTotalPassengers}
                                    disabled={true}
                                    className="bg-muted"
                                />
                            </FormField>
                        </div>

                        {(data.rawdah_tasreehs || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No Rawdah tasreeh entries yet. Click "Add
                                Rawdah" to add schedules.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.rawdah_tasreehs || []).map(
                                    (tasreeh, index) => {
                                        const womenCount = Number(
                                            tasreeh.women_passengers ?? 0,
                                        );
                                        const menCount = Number(
                                            tasreeh.men_passengers ?? 0,
                                        );
                                        const totalCount =
                                            womenCount + menCount;

                                        return (
                                            <div
                                                key={index}
                                                className="space-y-4 rounded-lg border p-4"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <span className="font-semibold">
                                                        Rawdah {index + 1}
                                                    </span>
                                                    {!isView && (
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() =>
                                                                removeRawdahTasreeh(
                                                                    index,
                                                                )
                                                            }
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>

                                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <div className="space-y-4">
                                                        <FormField
                                                            label="Date"
                                                            fieldRequirementsProps={{
                                                                hint: 'Tasreeh date',
                                                            }}
                                                            error={getError(
                                                                `rawdah_tasreehs.${index}.date`,
                                                            )}
                                                        >
                                                            <DatePickerField
                                                                id={`rawdah_date_${index}`}
                                                                key={`rawdah_date_${index}`}
                                                                value={
                                                                    tasreeh.date ||
                                                                    ''
                                                                }
                                                                fromYear={new Date().getFullYear()}
                                                                toYear={
                                                                    new Date().getFullYear() +
                                                                    5
                                                                }
                                                                disabled={
                                                                    isView ||
                                                                    processing
                                                                }
                                                                onChange={(v) =>
                                                                    updateRawdahTasreeh(
                                                                        index,
                                                                        'date',
                                                                        v ||
                                                                            null,
                                                                    )
                                                                }
                                                            />
                                                        </FormField>
                                                        <FormField
                                                            label="Women Time"
                                                            fieldRequirementsProps={{
                                                                hint: 'Women time slot',
                                                            }}
                                                            error={getError(
                                                                `rawdah_tasreehs.${index}.women_time`,
                                                            )}
                                                        >
                                                            <ProperInput
                                                                type="time"
                                                                timeFormat={24}
                                                                value={
                                                                    tasreeh.women_time ??
                                                                    ''
                                                                }
                                                                disabled={
                                                                    isView ||
                                                                    processing
                                                                }
                                                                onCommit={(v) =>
                                                                    updateRawdahTasreeh(
                                                                        index,
                                                                        'women_time',
                                                                        v ||
                                                                            null,
                                                                    )
                                                                }
                                                            />
                                                        </FormField>
                                                        <FormField
                                                            label="Men Time"
                                                            fieldRequirementsProps={{
                                                                hint: 'Men time slot',
                                                            }}
                                                            error={getError(
                                                                `rawdah_tasreehs.${index}.men_time`,
                                                            )}
                                                        >
                                                            <ProperInput
                                                                type="time"
                                                                timeFormat={24}
                                                                value={
                                                                    tasreeh.men_time ??
                                                                    ''
                                                                }
                                                                disabled={
                                                                    isView ||
                                                                    processing
                                                                }
                                                                onCommit={(v) =>
                                                                    updateRawdahTasreeh(
                                                                        index,
                                                                        'men_time',
                                                                        v ||
                                                                            null,
                                                                    )
                                                                }
                                                            />
                                                        </FormField>
                                                    </div>
                                                    <div className="space-y-4">
                                                        <FormField
                                                            label="Total Passengers"
                                                            fieldRequirementsProps={{
                                                                hint: 'Auto-calculated total',
                                                            }}
                                                        >
                                                            <Input
                                                                type="number"
                                                                value={
                                                                    totalCount
                                                                }
                                                                disabled={true}
                                                                className="bg-muted"
                                                            />
                                                        </FormField>
                                                        <FormField
                                                            label="Women Passengers"
                                                            fieldRequirementsProps={{
                                                                hint: 'Input women passenger count for this Rawdah slot.',
                                                            }}
                                                            error={getError(
                                                                `rawdah_tasreehs.${index}.women_passengers`,
                                                            )}
                                                        >
                                                            <ProperInput
                                                                type="number"
                                                                value={String(
                                                                    womenCount ===
                                                                        0
                                                                        ? ''
                                                                        : womenCount,
                                                                )}
                                                                disabled={
                                                                    isView ||
                                                                    processing
                                                                }
                                                                onCommit={(v) =>
                                                                    updateRawdahTasreeh(
                                                                        index,
                                                                        'women_passengers',
                                                                        v === ''
                                                                            ? 0
                                                                            : Number(
                                                                                  v,
                                                                              ),
                                                                    )
                                                                }
                                                                inputProps={{
                                                                    min: '0',
                                                                }}
                                                                placeholder="0"
                                                            />
                                                        </FormField>
                                                        <FormField
                                                            label="Men Passengers"
                                                            fieldRequirementsProps={{
                                                                hint: 'Input men passenger count for this Rawdah slot.',
                                                            }}
                                                            error={getError(
                                                                `rawdah_tasreehs.${index}.men_passengers`,
                                                            )}
                                                        >
                                                            <ProperInput
                                                                type="number"
                                                                value={String(
                                                                    menCount ===
                                                                        0
                                                                        ? ''
                                                                        : menCount,
                                                                )}
                                                                disabled={
                                                                    isView ||
                                                                    processing
                                                                }
                                                                onCommit={(v) =>
                                                                    updateRawdahTasreeh(
                                                                        index,
                                                                        'men_passengers',
                                                                        v === ''
                                                                            ? 0
                                                                            : Number(
                                                                                  v,
                                                                              ),
                                                                    )
                                                                }
                                                                inputProps={{
                                                                    min: '0',
                                                                }}
                                                                placeholder="0"
                                                            />
                                                        </FormField>
                                                    </div>
                                                </div>

                                                <FormField
                                                    label="Remarks"
                                                    fieldRequirementsProps={{
                                                        hint: 'Additional notes',
                                                    }}
                                                    error={getError(
                                                        `rawdah_tasreehs.${index}.remarks`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            tasreeh.remarks ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        textarea
                                                        onCommit={(v) =>
                                                            updateRawdahTasreeh(
                                                                index,
                                                                'remarks',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="Optional remarks"
                                                    />
                                                </FormField>
                                            </div>
                                        );
                                    },
                                )}
                            </div>
                        )}
                    {!isView && (
                        <Button
                            type="button"
                            variant="default"
                            className="mt-4 w-full sm:w-auto"
                            onClick={addRawdahTasreeh}
                            disabled={processing}
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            Add Rawdah Tasreeh
                        </Button>
                    )}
                </FormSection>

                {/* Officials */}
                <FormSection
                    value="officials"
                    title="Officials"
                    description="Assign officials for this package."
                    status={sectionStatuses.officials}
                    required={false}
                >
                        {(data.officials || []).length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                No officials added yet. Click "Add Official" to
                                assign officials.
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {(data.officials || []).map(
                                    (official, index) => (
                                        <div
                                            key={index}
                                            className="space-y-4 rounded-lg border p-4"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-semibold">
                                                    Official {index + 1}
                                                    {official.type
                                                        ? ` - ${officialTypeLabels[official.type] ?? official.type}`
                                                        : ''}
                                                </span>
                                                {!isView && (
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeOfficial(
                                                                index,
                                                            )
                                                        }
                                                        disabled={processing}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                                                <FormField
                                                    label="Type"
                                                    htmlFor={`officials_${index}_type`}
                                                    fieldRequirementsProps={{
                                                        required: true,
                                                        hint: 'Select official type',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.type`,
                                                    )}
                                                >
                                                    <ProperInputSelect
                                                        id={`officials_${index}_type`}
                                                        mode="classic"
                                                        value={
                                                            official.type || ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onValueChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'type',
                                                                v,
                                                            )
                                                        }
                                                        options={
                                                            officialTypeOptions
                                                        }
                                                        placeholder="Select type"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Name"
                                                    fieldRequirementsProps={{
                                                        required: true,
                                                        hint: 'Enter official name',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.name`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.name ?? ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'name',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="Enter name"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Contact Number"
                                                    fieldRequirementsProps={{
                                                        hint: 'Enter contact number',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.contact_number`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.contact_number ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'contact_number',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="Enter contact number"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Nationality"
                                                    fieldRequirementsProps={{
                                                        hint: 'Enter nationality',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.nationality`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.nationality ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'nationality',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Singaporean"
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                                                <FormField
                                                    label="Passport Number"
                                                    fieldRequirementsProps={{
                                                        hint: 'Enter passport number',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.passport_number`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.passport_number ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'passport_number',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="Enter passport number"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Gender"
                                                    htmlFor={`officials_${index}_gender`}
                                                    fieldRequirementsProps={{
                                                        hint: 'Select gender',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.gender`,
                                                    )}
                                                >
                                                    <ProperInputSelect
                                                        id={`officials_${index}_gender`}
                                                        mode="classic"
                                                        value={
                                                            official.gender ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onValueChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'gender',
                                                                v,
                                                            )
                                                        }
                                                        options={genderOptions}
                                                        placeholder="Select gender"
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Date of Birth"
                                                    fieldRequirementsProps={{
                                                        hint: 'Official date of birth',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.date_of_birth`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`official_dob_${index}`}
                                                        key={`official_dob_${index}`}
                                                        value={
                                                            official.date_of_birth ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        fromYear={
                                                            new Date().getFullYear() -
                                                            100
                                                        }
                                                        toYear={new Date().getFullYear()}
                                                        onChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'date_of_birth',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Place of Birth"
                                                    fieldRequirementsProps={{
                                                        hint: 'City of birth',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.place_of_birth`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.place_of_birth ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'place_of_birth',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Johor Bahru"
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                                                <FormField
                                                    label="Passport Issue Date"
                                                    fieldRequirementsProps={{
                                                        hint: 'Issue date in passport',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.passport_issue_date`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`official_issue_${index}`}
                                                        key={`official_issue_${index}`}
                                                        value={
                                                            official.passport_issue_date ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        fromYear={
                                                            new Date().getFullYear() -
                                                            10
                                                        }
                                                        toYear={
                                                            new Date().getFullYear() +
                                                            10
                                                        }
                                                        onChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'passport_issue_date',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Passport Expiry Date"
                                                    fieldRequirementsProps={{
                                                        hint: 'Expiry date in passport',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.passport_expiry_date`,
                                                    )}
                                                >
                                                    <DatePickerField
                                                        id={`official_expiry_${index}`}
                                                        key={`official_expiry_${index}`}
                                                        value={
                                                            official.passport_expiry_date ||
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        fromYear={
                                                            new Date().getFullYear() -
                                                            10
                                                        }
                                                        toYear={
                                                            new Date().getFullYear() +
                                                            10
                                                        }
                                                        onChange={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'passport_expiry_date',
                                                                v || null,
                                                            )
                                                        }
                                                    />
                                                </FormField>
                                                <FormField
                                                    label="Passport Place of Issue"
                                                    fieldRequirementsProps={{
                                                        hint: 'City or office of issue',
                                                    }}
                                                    error={getError(
                                                        `officials.${index}.passport_place_of_issue`,
                                                    )}
                                                >
                                                    <ProperInput
                                                        value={
                                                            official.passport_place_of_issue ??
                                                            ''
                                                        }
                                                        disabled={
                                                            isView || processing
                                                        }
                                                        onCommit={(v) =>
                                                            updateOfficial(
                                                                index,
                                                                'passport_place_of_issue',
                                                                v || null,
                                                            )
                                                        }
                                                        placeholder="e.g., Kuala Lumpur"
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                {accommodationHotelTargets.length ===
                                                0 ? (
                                                    <FormField
                                                        label="Hotel"
                                                        fieldRequirementsProps={{
                                                            hint: 'Hotel remark for official',
                                                        }}
                                                        error={getError(
                                                            `officials.${index}.hotel`,
                                                        )}
                                                    >
                                                        <ProperInput
                                                            value={
                                                                official.hotel ??
                                                                ''
                                                            }
                                                            disabled={
                                                                isView ||
                                                                processing
                                                            }
                                                            onCommit={(v) =>
                                                                updateOfficial(
                                                                    index,
                                                                    'hotel',
                                                                    v || null,
                                                                )
                                                            }
                                                            placeholder="Enter hotel"
                                                        />
                                                    </FormField>
                                                ) : (
                                                    <div className="space-y-3 rounded-md border border-dashed p-4 md:col-span-2">
                                                        <p className="text-base font-medium">
                                                            Hotel by Location
                                                        </p>
                                                        <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-2">
                                                            {accommodationHotelTargets.map(
                                                                (
                                                                    target,
                                                                    targetIndex,
                                                                ) => (
                                                                    <FormField
                                                                        key={`${target.primaryKey}-${targetIndex}`}
                                                                        label={`${target.label} Hotel`}
                                                                        fieldRequirementsProps={{
                                                                            hint: 'Official hotel for this accommodation location',
                                                                        }}
                                                                    >
                                                                        <ProperInput
                                                                            value={getOfficialHotelByTarget(
                                                                                official,
                                                                                target.matchingKeys,
                                                                            )}
                                                                            disabled={
                                                                                isView ||
                                                                                processing
                                                                            }
                                                                            onCommit={(
                                                                                v,
                                                                            ) =>
                                                                                updateOfficialHotelByTarget(
                                                                                    index,
                                                                                    target.primaryKey,
                                                                                    v,
                                                                                )
                                                                            }
                                                                            placeholder={`Enter hotel for ${target.label}`}
                                                                        />
                                                                    </FormField>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    {!isView && (
                        <Button
                            type="button"
                            variant="default"
                            className="mt-4 w-full sm:w-auto"
                            onClick={addOfficial}
                            disabled={processing}
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            Add Official
                        </Button>
                    )}
                </FormSection>

                {/* Package Inclusions */}
                <FormSection
                    value="package-inclusions"
                    title="Package Inclusions"
                    description="Describe included, excluded, and special offer details."
                    status={sectionStatuses.packageInclusions}
                    required={false}
                >
                    <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                        <FormField
                            label="Included"
                            htmlFor="included"
                            fieldRequirementsProps={{
                                hint: "List what's included in the package",
                            }}
                            error={getError('included')}
                        >
                            <ProperInput
                                id="included"
                                value={data.included || ''}
                                disabled={isView || processing}
                                textarea
                                onCommit={(e) =>
                                    setData('included', e || null)
                                }
                                placeholder="List included items (e.g., flights, hotels, meals, visa, transport...)"
                            />
                        </FormField>
                        <FormField
                            label="Not Included"
                            htmlFor="not_included"
                            fieldRequirementsProps={{
                                hint: "List what's not included",
                            }}
                            error={getError('not_included')}
                        >
                            <ProperInput
                                id="not_included"
                                value={data.not_included || ''}
                                disabled={isView || processing}
                                textarea
                                onCommit={(e) =>
                                    setData('not_included', e || null)
                                }
                                placeholder="List excluded items (e.g., personal expenses, tips...)"
                            />
                        </FormField>
                        <FormField
                            label="Offer"
                            htmlFor="offer"
                            fieldRequirementsProps={{
                                hint: 'Describe any special offers',
                            }}
                            error={getError('offer')}
                        >
                            <ProperInput
                                id="offer"
                                value={data.offer || ''}
                                disabled={isView || processing}
                                textarea
                                onCommit={(e) =>
                                    setData('offer', e || null)
                                }
                                placeholder="Describe any special offers or promotions..."
                            />
                        </FormField>
                    </div>
                </FormSection>

                {/* Remarks */}
                <FormSection
                    value="remarks"
                    title="Remarks"
                    description="Add any additional internal or operational notes."
                    status={sectionStatuses.remarks}
                    required={false}
                >
                    <FormField
                        label="Remarks"
                        htmlFor="remarks"
                        fieldRequirementsProps={{
                            hint: 'Enter any additional remarks or notes',
                        }}
                        error={getError('remarks')}
                    >
                        <ProperInput
                            id="remarks"
                            value={data.remarks || ''}
                            disabled={isView || processing}
                            textarea
                            onCommit={(v) => setData('remarks', v || null)}
                            placeholder="Additional remarks or notes"
                            rows={4}
                        />
                    </FormField>
                </FormSection>

                </Accordion>

                {/* Action Buttons */}
                <div className="flex justify-end gap-3">
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                            disabled={processing}
                        >
                            {onSuccess ? 'Cancel' : 'Back'}
                        </Button>
                    )}
                    {!isView && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => reset()}
                                disabled={processing}
                            >
                                Reset
                            </Button>
                            <Button
                                type="submit"
                                className="min-w-[140px]"
                                disabled={processing}
                            >
                                {processing && (
                                    <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                                )}
                                {processing
                                    ? isEdit
                                        ? 'Updating...'
                                        : onSuccess
                                          ? 'Creating...'
                                          : 'Creating...'
                                    : isEdit
                                      ? 'Update'
                                      : onSuccess
                                        ? 'Create & Continue'
                                        : 'Create'}
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
