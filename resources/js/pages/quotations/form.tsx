import { FormProgressHeader } from '@/components/form-progress-header';
import { Accordion } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { navigateToSection } from '@/lib/navigation-helper';
import { formatDateForDisplay } from '@/lib/utils';
import { getForShow as getCustomerForShow } from '@/routes/customer';
import { getForShow as getMaidForShow } from '@/routes/maid';
import { OptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { nanoid } from 'nanoid';
import { useCallback, useEffect, useRef, useState } from 'react';
import { MaidSchema } from '../maid/schema';
import { UserSchema } from '../masters/users/schema';
import { NoteSchema } from '../notes/schema';
import QuotationDetailSection from './components/quotation-detail-section';
import QuotationInformationSection from './components/quotation-information-section';
import QuotationPreviewModal from './components/quotation-preview-modal';
import StatusSection from './components/status-section';
import { useQuotationSectionStatus } from './hooks/use-quotation-section-status';
import { QuotationItemSchema, quotationItemsSchema } from './items/schema';
import { QuotationSchema, quotationSchema } from './schema';

interface QuotationFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: QuotationSchema;
    paymentPlans?: OptionType[];
    paymentMethods?: OptionType[];
    statuses?: OptionType[];
    maids?: OptionType[];
    customers?: OptionType[];
    quotationItems?: QuotationItemSchema[];
    quotationNotes?: NoteSchema[];
    prefilledMaidId?: string;
    prefilledMaidData?: MaidSchema;
    prefilledCustomerId?: string;
    prefilledCustomerData?: UserSchema;
    handoverDate?: string;
    onCancel?: () => void;
}

export function QuotationForm({
    mode,
    initialData,
    paymentPlans = [],
    paymentMethods = [],
    statuses = [],
    maids = [],
    customers = [],
    quotationItems = [],
    quotationNotes = [],
    prefilledMaidId,
    prefilledMaidData,
    prefilledCustomerId,
    prefilledCustomerData,
    handoverDate,
    onCancel,
}: QuotationFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const initialNotes: NoteSchema[] = (
        initialData?.notes?.length ? initialData.notes : quotationNotes
    ).map((note) => ({
        ...note,
        _key: note.id ? `id-${note.id}` : nanoid(),
        model: 'quotation',
    }));

    const today = formatDateForDisplay(new Date());

    const initialFormState: QuotationSchema = {
        id: undefined,
        quotation_number: '',
        quotation_date: today,
        expiry_date: today,
        customer_id: undefined,
        customer_name: '',
        nric_number: '',
        customer_contact: '',
        customer_address: '',
        customer_email: null,
        maid_id: undefined,
        description: '',
        passport_number: '',
        commencement_date: '',
        monthly_salary: '',
        loan_duration: '',
        cost_of_maid: '',
        rest_day_of_the_week: ['Weekend'],
        rest_days_per_month: '',
        compensation_off_in_lieu: '',
        payment_plan: 'full',
        payment_method: 'transfer',
        status: 'draft',
        reason: '',
        items: [],
        model: 'quotation',
        notes: [],
    };

    const defaultData: QuotationSchema = {
        ...(initialData ?? initialFormState),
        notes: initialNotes,
        ...(prefilledMaidId && prefilledMaidData
            ? {
                  maid_id: Number.parseInt(prefilledMaidId, 10),
                  passport_number: prefilledMaidData.passport_number || '',
                  monthly_salary: prefilledMaidData.monthly_salary || '',
                  loan_duration: prefilledMaidData.remaining_loan || '',
                  compensation_off_in_lieu:
                      prefilledMaidData.monthly_salary &&
                      Number.parseFloat(prefilledMaidData.monthly_salary) > 0
                          ? Number.parseFloat(
                                prefilledMaidData.monthly_salary,
                            ) / 20
                          : '',
                  rest_days_per_month:
                      prefilledMaidData.rest_days_per_month || '',
                  commencement_date: handoverDate
                      ? formatDateForDisplay(handoverDate)
                      : '',
              }
            : {}),
        ...(prefilledCustomerId && prefilledCustomerData
            ? {
                  customer_id: Number.parseInt(prefilledCustomerId, 10),
                  customer_name: prefilledCustomerData.name || '',
                  customer_contact: prefilledCustomerData.contact || '',
                  customer_address: prefilledCustomerData.address || '',
                  customer_email: prefilledCustomerData.email || '',
              }
            : {}),
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
    } = useForm<QuotationSchema>(defaultData);

    const sectionErrors = errors as Partial<
        Record<keyof QuotationSchema, string>
    >;
    const [openSections, setOpenSections] = useState<string[]>([
        'customer_and_quotation_information',
    ]);
    const { sections, getQuotationSectionStatus } = useQuotationSectionStatus({
        data,
        errors: sectionErrors,
    });
    const [selectedCustomerData, setSelectedCustomerData] =
        useState<UserSchema | null>(null);
    const [selectedMaidData, setSelectedMaidData] = useState<MaidSchema | null>(
        null,
    );

    // customer
    const getCustomerDetail = async (id: number) => {
        try {
            const response = await fetch(getCustomerForShow(id).url);
            if (!response) throw new Error('Network error');
            const customer = await response.json();
            setSelectedCustomerData(customer);
        } catch (err) {
            console.error('Failed to fetch customer details:', err);
        }
    };

    useEffect(() => {
        if (selectedCustomerData) {
            setData((prev) => ({
                ...prev,
                // nric_number: selectedCustomerData.nric_number,
                customer_name: selectedCustomerData.name,
                customer_contact: selectedCustomerData.contact,
                customer_address: selectedCustomerData.address,
                customer_email: selectedCustomerData.email,
            }));
        }
    }, [selectedCustomerData, setData]);

    // Initialize prefilled data
    useEffect(() => {
        if (prefilledMaidData && prefilledMaidId && isCreate) {
            setSelectedMaidData(prefilledMaidData);
        }
        if (prefilledCustomerData && prefilledCustomerId && isCreate) {
            setSelectedCustomerData(prefilledCustomerData);
        }
    }, [
        prefilledMaidData,
        prefilledMaidId,
        prefilledCustomerData,
        prefilledCustomerId,
        isCreate,
    ]);
    const getMaidDetail = async (id: number) => {
        try {
            const response = await fetch(getMaidForShow(id).url);
            if (!response) throw new Error('Network error');
            const maid = await response.json();
            setSelectedMaidData(maid);

            if (maid.monthly_salary) {
                const salary = Number.parseFloat(maid.monthly_salary);
                if (!Number.isNaN(salary) && salary > 0) {
                    const compensation = salary / 20;
                    setData('compensation_off_in_lieu', compensation);
                }
            }
        } catch (err) {
            console.error('Failed to fetch maid details:', err);
        }
    };

    useEffect(() => {
        if (selectedMaidData) {
            setData((prev) => ({
                ...prev,
                passport_number: selectedMaidData.passport_number,
                monthly_salary: selectedMaidData.monthly_salary,
                loan_duration: selectedMaidData.remaining_loan,
                rest_days_per_month: selectedMaidData.rest_days_per_month,
            }));
        }
    }, [selectedMaidData, setData]);

    useEffect(() => {
        if (!data.monthly_salary || !data.loan_duration) return;

        const salary = Number(data.monthly_salary);
        const loan = Number(data.loan_duration);

        if (Number.isNaN(salary) || Number.isNaN(loan)) return;

        setData((prev) => ({
            ...prev,
            cost_of_maid: String(salary * loan),
        }));
    }, [data.monthly_salary, data.loan_duration, setData]);

    // items
    const initializedRef = useRef(false);

    useEffect(() => {
        if (initializedRef.current) return;

        if (data.items?.length) {
            const updateItems = data.items.map((item) => {
                const key = item._key || (item.id ? `id-${item.id}` : nanoid());
                return {
                    ...item,
                    _key: key,
                };
            });
            setData('items', updateItems);
        } else if (quotationItems?.length) {
            const initialItems = quotationItems.map((item) => ({
                ...item,
                _key: item.id ? `id-${item.id}` : nanoid(),
            }));

            setData('items', initialItems);
        }

        initializedRef.current = true;
    }, [data.items, quotationItems, setData]);

    // validation
    function validateClientSide() {
        clearErrors();
        let valid = true;

        const quotationResult = quotationSchema.safeParse(data);

        if (!quotationResult.success) {
            quotationResult.error.issues.forEach((issue) => {
                const key = issue.path.join('.') as keyof QuotationSchema;
                setError(key, issue.message);
            });
            valid = false;
        }

        const itemsResult = quotationItemsSchema.safeParse({
            items: data.items ?? [],
        });

        if (!itemsResult.success) {
            itemsResult.error.issues.forEach((issue) => {
                const path = issue.path.join('.');
                setError(path as unknown as keyof typeof errors, issue.message);
            });
            valid = false;
        }

        return valid;
    }

    // action
    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!validateClientSide()) return;

        const url = '/quotation';

        if (isCreate) {
            post(url, {
                onError: (errors) => setError(errors),
            });
        } else if (isEdit) {
            put(`${url}/${data.id}`, {
                onError: (errors) => setError(errors),
            });
        }
    }

    // err
    function formatError(path: string, message: string) {
        const parts = path.split('.');

        if (parts[0] === 'items' && parts.length >= 3) {
            const index = Number(parts[1]) + 1;
            const field = parts[2];
            const fieldLabelMap: Record<string, string> = {
                description: 'Description',
                rate: 'Cost',
                sort_order: 'Sort Order',
            };

            return `Item #${index} ${fieldLabelMap[field] ?? field} ${message.replace(/^The\s.+?\s/, '')}`;
        }

        return message;
    }

    const renderError = (path: string) => {
        const errorMap = errors as Record<string, string | undefined>;
        const message = errorMap[path];

        if (!message) return null;

        return (
            <p className="mt-1 text-sm text-red-500">
                {formatError(path, message)}
            </p>
        );
    };

    // misc
    const handleSectionClick = useCallback(
        (sectionId: string) => {
            navigateToSection(sectionId, setOpenSections);
        },
        [setOpenSections],
    );

    const handleReset = () => {
        reset();
    };

    return (
        <div className="mx-auto w-full">
            {/* Progress Header */}
            {mode !== 'view' && (
                <FormProgressHeader
                    title="Quotation"
                    sections={sections}
                    onSectionClick={handleSectionClick}
                />
            )}

            {/* Quotation Number Box */}
            {data.quotation_number && (
                <div className="mb-2 rounded-lg border border-primary/20 bg-primary/5 p-4">
                    <p className="text-base text-muted-foreground">
                        Quotation No.
                    </p>
                    <p className="text-2xl font-bold text-primary">
                        {data.quotation_number}
                    </p>
                </div>
            )}

            <form onSubmit={submit} className="space-y-6 py-2">
                <Accordion
                    type="multiple"
                    value={openSections}
                    onValueChange={setOpenSections}
                    className="mb-8 space-y-4"
                >
                    <QuotationInformationSection
                        data={data}
                        isView={isView}
                        setData={setData}
                        renderError={renderError}
                        customers={customers}
                        getCustomerDetail={getCustomerDetail}
                        maids={maids}
                        getMaidDetail={getMaidDetail}
                        status={getQuotationSectionStatus(
                            'customer_and_quotation_information',
                        )}
                    />

                    <QuotationDetailSection
                        data={data}
                        isView={isView}
                        setData={setData}
                        renderError={renderError}
                        onChange={(nextItems) => setData('items', nextItems)}
                        items={data.items ?? []}
                        quotationNotes={data.notes}
                        paymentPlans={paymentPlans}
                        paymentMethods={paymentMethods}
                        status={getQuotationSectionStatus(
                            'maid_and_quotation_details',
                        )}
                    />

                    <StatusSection
                        data={data}
                        mode={mode}
                        initialStatus={initialData?.status ?? null}
                        isView={isView}
                        setData={setData}
                        renderError={renderError}
                        statuses={statuses}
                        status={getQuotationSectionStatus('status')}
                    />
                </Accordion>

                {/* Buttons */}
                <div className="flex justify-end gap-2">
                    <div className="flex justify-end gap-2">
                        <QuotationPreviewModal
                            data={data}
                            items={data.items ?? []}
                        />
                    </div>

                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                        >
                            Back
                        </Button>
                    )}
                    {!isView && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleReset}
                                disabled={processing}
                            >
                                Reset
                            </Button>
                            <Button
                                type="submit"
                                className="min-w-[140px]"
                                disabled={processing}
                            >
                                {isEdit ? 'Update' : 'Create'}
                            </Button>
                        </>
                    )}
                </div>
            </form>
        </div>
    );
}
