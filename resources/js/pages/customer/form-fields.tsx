import { BooleanSelect } from '@/components/boolean-select';
import { DatePickerField } from '@/components/date-picker';
import { DocumentField } from '@/components/document-field';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import TextLink from '@/components/text-link';
import { parseDisplayDate } from '@/lib/utils';
import {
    genderOptions,
    maritalStatusOptions,
    type CustomerSchema,
} from './schema';

interface CustomerFormFieldsProps {
    customer: CustomerSchema;
    index?: number;
    fieldPrefix?: string;
    useGeneratedDocumentName?: boolean;
    isView: boolean;
    processing: boolean;
    showUseMainAddressButton?: boolean;
    onUseMainAddress?: () => void;
    getError: (path: string) => string | undefined;
    onUpdateCustomer: (
        field: keyof CustomerSchema,
        value: string | boolean | File | null,
    ) => void;
}

const DOCUMENT_FIELDS = [
    {
        fileKey: 'passport_file' as const,
        nameKey: 'passport_file_name' as const,
        removedKey: 'passport_file_removed' as const,
        documentKey: 'passport_document' as const,
        label: 'Passport',
        accept: '.jpg,.jpeg,.png,.pdf',
        acceptedFileTypesLabel: 'JPG, JPEG, PNG, PDF',
        maxFileSizeKb: 5120,
        hint: 'Upload passport attachment. Accepted: JPG, JPEG, PNG, PDF. Max 5MB.',
    },
    {
        fileKey: 'photo_file' as const,
        nameKey: 'photo_file_name' as const,
        removedKey: 'photo_file_removed' as const,
        documentKey: 'photo_document' as const,
        label: 'Photo',
        accept: '.jpg,.jpeg,.png',
        acceptedFileTypesLabel: 'JPG, JPEG, PNG',
        maxFileSizeKb: 5120,
        hint: 'Upload photo attachment. Accepted: JPG, JPEG, PNG. Max 5MB.',
    },
] as const;

export default function CustomerFormFields({
    customer,
    index,
    fieldPrefix,
    useGeneratedDocumentName = false,
    isView,
    processing,
    showUseMainAddressButton = false,
    onUseMainAddress,
    getError,
    onUpdateCustomer,
}: CustomerFormFieldsProps) {
    const disabled = isView || processing;
    const prefix =
        fieldPrefix ?? (typeof index === 'number' ? `members.${index}` : '');
    const fieldPath = (field: keyof CustomerSchema): string => {
        return prefix ? `${prefix}.${field}` : String(field);
    };

    const passportIssueDate = customer.passport_issue_date
        ? parseDisplayDate(customer.passport_issue_date)
        : null;

    const buildGeneratedFileName = (fieldLabel: string): string => {
        const safeName = (customer.name ?? '').trim() || 'Customer';

        return `${fieldLabel} ${safeName}`;
    };

    return (
        <div className="space-y-6">
            {/* Personal Information */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">
                    Personal Information
                    {customer.customer_number && (
                        <>
                            {' '}
                            <span className="text-muted-foreground">
                                (Customer No.{' '}
                                <span className="text-primary">
                                    {customer.customer_number}
                                </span>
                                )
                            </span>
                        </>
                    )}
                </h3>
                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    <FormField
                        label="Full Name"
                        fieldRequirementsProps={{
                            required: true,
                            hint: "Customer's full name as per passport or IC",
                            example: 'Ahmad bin Abdullah',
                        }}
                        htmlFor={fieldPath('name')}
                        error={getError(fieldPath('name'))}
                    >
                        <ProperInput
                            id={fieldPath('name')}
                            value={customer.name}
                            disabled={disabled}
                            onCommit={(v) => onUpdateCustomer('name', v)}
                            placeholder="Enter full name"
                        />
                    </FormField>

                    <FormField
                        label="Email Address"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Valid email address for communication',
                            format: 'email@example.com',
                        }}
                        htmlFor={fieldPath('email')}
                        error={getError(fieldPath('email'))}
                    >
                        <ProperInput
                            id={fieldPath('email')}
                            value={customer.email}
                            disabled={disabled}
                            onCommit={(v) => onUpdateCustomer('email', v)}
                            placeholder="email@example.com"
                        />
                    </FormField>

                    <FormField
                        label="Contact Number"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Phone number with country code',
                            format: '+65 8765 4321',
                        }}
                        htmlFor={fieldPath('contact_number')}
                        error={getError(fieldPath('contact_number'))}
                    >
                        <ProperInput
                            id={fieldPath('contact_number')}
                            value={customer.contact_number}
                            disabled={disabled}
                            onCommit={(v) =>
                                onUpdateCustomer('contact_number', v)
                            }
                            placeholder="+65 8765 4321"
                        />
                    </FormField>

                    <FormField
                        label="NRIC Number"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'National Registration Identity Card number',
                            format: '123456-12-1234',
                        }}
                        htmlFor={fieldPath('nric_number')}
                        error={getError(fieldPath('nric_number'))}
                    >
                        <ProperInput
                            id={fieldPath('nric_number')}
                            value={customer.nric_number}
                            disabled={disabled}
                            onCommit={(v) => onUpdateCustomer('nric_number', v)}
                            placeholder="NRIC number"
                        />
                    </FormField>

                    <FormField
                        label="Gender"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Select gender as per official documents',
                        }}
                        htmlFor={fieldPath('gender')}
                        error={getError(fieldPath('gender'))}
                    >
                        <ProperInputSelect
                            options={genderOptions}
                            value={customer.gender}
                            onValueChange={(v) =>
                                onUpdateCustomer('gender', String(v))
                            }
                            placeholder="Select gender"
                            disabled={disabled}
                            searchable={false}
                        />
                    </FormField>

                    <FormField
                        label="Marital Status"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Current marital status',
                        }}
                        htmlFor={fieldPath('marital_status')}
                        error={getError(fieldPath('marital_status'))}
                    >
                        <ProperInputSelect
                            options={maritalStatusOptions}
                            value={customer.marital_status}
                            onValueChange={(v) =>
                                onUpdateCustomer('marital_status', String(v))
                            }
                            placeholder="Select marital status"
                            disabled={disabled}
                            searchable={false}
                        />
                    </FormField>

                    <FormField
                        label="Date of Birth"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Birth date as per passport or IC',
                        }}
                        htmlFor={fieldPath('date_of_birth')}
                        error={getError(fieldPath('date_of_birth'))}
                    >
                        <DatePickerField
                            id={fieldPath('date_of_birth')}
                            value={customer.date_of_birth}
                            disabled={disabled}
                            fromYear={1930}
                            toYear={new Date().getFullYear()}
                            onChange={(v) =>
                                onUpdateCustomer('date_of_birth', v)
                            }
                        />
                    </FormField>

                    <FormField
                        label="Place of Birth"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'City or country where you were born',
                            example: 'Singapore',
                        }}
                        htmlFor={fieldPath('place_of_birth')}
                        error={getError(fieldPath('place_of_birth'))}
                    >
                        <ProperInput
                            id={fieldPath('place_of_birth')}
                            value={customer.place_of_birth}
                            disabled={disabled}
                            onCommit={(v) =>
                                onUpdateCustomer('place_of_birth', v)
                            }
                            placeholder="Enter place of birth"
                        />
                    </FormField>

                    <FormField
                        label="Nationality"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Citizenship as per passport',
                            example: 'Singaporean, Malaysian, etc.',
                        }}
                        htmlFor={fieldPath('nationality')}
                        error={getError(fieldPath('nationality'))}
                    >
                        <ProperInput
                            id={fieldPath('nationality')}
                            value={customer.nationality}
                            disabled={disabled}
                            onCommit={(v) => onUpdateCustomer('nationality', v)}
                            placeholder="e.g. Singaporean, Malaysian, etc."
                        />
                    </FormField>

                    <FormField
                        label="Residential Address"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Full address including street, city, and postal code',
                            example:
                                '111 Orchard Road #10-05, Orchard Towers, Singapore 238858',
                        }}
                        labelAction={
                            !disabled &&
                            showUseMainAddressButton &&
                            onUseMainAddress ? (
                                <TextLink
                                    as="button"
                                    type="button"
                                    onClick={onUseMainAddress}
                                    disabled={disabled}
                                >
                                    Use Main Address
                                </TextLink>
                            ) : null
                        }
                        htmlFor={fieldPath('address')}
                        error={getError(fieldPath('address'))}
                        className="md:col-span-2"
                    >
                        <ProperInput
                            id={fieldPath('address')}
                            value={customer.address}
                            disabled={disabled}
                            textarea
                            onCommit={(v) => onUpdateCustomer('address', v)}
                            placeholder="Full address including postal code"
                        />
                    </FormField>
                </div>
            </div>

            {/* Passport Information */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Passport Information</h3>
                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    <FormField
                        label="Passport Number"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Passport number exactly as shown on passport',
                        }}
                        htmlFor={fieldPath('passport_number')}
                        error={getError(fieldPath('passport_number'))}
                    >
                        <ProperInput
                            id={fieldPath('passport_number')}
                            value={customer.passport_number}
                            disabled={disabled}
                            onCommit={(v) =>
                                onUpdateCustomer('passport_number', v)
                            }
                            placeholder="Enter passport number"
                        />
                    </FormField>

                    <FormField
                        label="Place of Issue"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'City or country where passport was issued',
                            example: 'Singapore',
                        }}
                        htmlFor={fieldPath('passport_place_of_issue')}
                        error={getError(fieldPath('passport_place_of_issue'))}
                    >
                        <ProperInput
                            id={fieldPath('passport_place_of_issue')}
                            value={customer.passport_place_of_issue}
                            disabled={disabled}
                            onCommit={(v) =>
                                onUpdateCustomer('passport_place_of_issue', v)
                            }
                            placeholder="e.g. Singapore"
                        />
                    </FormField>

                    <FormField
                        label="Issue Date"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Date when passport was issued',
                        }}
                        htmlFor={fieldPath('passport_issue_date')}
                        error={getError(fieldPath('passport_issue_date'))}
                    >
                        <DatePickerField
                            id={fieldPath('passport_issue_date')}
                            value={customer.passport_issue_date || ''}
                            disabled={disabled}
                            fromYear={2000}
                            toYear={new Date().getFullYear()}
                            onChange={(v) =>
                                onUpdateCustomer('passport_issue_date', v)
                            }
                        />
                    </FormField>

                    <FormField
                        label="Expiry Date"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Passport must be valid for at least 6 months from travel date',
                        }}
                        htmlFor={fieldPath('passport_expiry_date')}
                        error={getError(fieldPath('passport_expiry_date'))}
                    >
                        <DatePickerField
                            id={fieldPath('passport_expiry_date')}
                            value={customer.passport_expiry_date || ''}
                            disabled={disabled}
                            fromYear={
                                passportIssueDate
                                    ? passportIssueDate.getFullYear()
                                    : new Date().getFullYear()
                            }
                            toYear={new Date().getFullYear() + 15}
                            disabledDates={(date) => {
                                if (!passportIssueDate) {
                                    return false;
                                }

                                const compareDate = new Date(date);
                                compareDate.setHours(0, 0, 0, 0);

                                const minDate = new Date(passportIssueDate);
                                minDate.setHours(0, 0, 0, 0);

                                return compareDate < minDate;
                            }}
                            onChange={(v) =>
                                onUpdateCustomer('passport_expiry_date', v)
                            }
                        />
                    </FormField>
                </div>
            </div>

            {/* Travel & Health */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Travel & Health</h3>
                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    <FormField
                        label="First time performing Umrah"
                        fieldRequirementsProps={{
                            hint: 'Indicate if this is your first Umrah pilgrimage',
                        }}
                        htmlFor={fieldPath('first_time_umrah')}
                    >
                        <BooleanSelect
                            id={fieldPath('first_time_umrah')}
                            value={!!customer.first_time_umrah}
                            onChange={(v) =>
                                onUpdateCustomer('first_time_umrah', v)
                            }
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Has chronic disease"
                        fieldRequirementsProps={{
                            hint: 'Indicate if you have any chronic health conditions',
                        }}
                        htmlFor={fieldPath('has_chronic_disease')}
                    >
                        <BooleanSelect
                            id={fieldPath('has_chronic_disease')}
                            value={!!customer.has_chronic_disease}
                            onChange={(v) => {
                                onUpdateCustomer('has_chronic_disease', v);
                            }}
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Using wheelchair"
                        fieldRequirementsProps={{
                            hint: 'Indicate whether wheelchair assistance is needed',
                        }}
                        htmlFor={fieldPath('is_using_wheelchair')}
                    >
                        <BooleanSelect
                            id={fieldPath('is_using_wheelchair')}
                            value={!!customer.is_using_wheelchair}
                            onChange={(v) => {
                                onUpdateCustomer('is_using_wheelchair', v);
                            }}
                            disabled={disabled}
                        />
                    </FormField>

                    {customer.has_chronic_disease === true && (
                        <FormField
                            label="Disease Details"
                            fieldRequirementsProps={{
                                hint: 'Provide details about the chronic disease or health condition',
                                example: 'Diabetes, Hypertension, Asthma, etc.',
                            }}
                            htmlFor={fieldPath('chronic_disease_details')}
                            error={getError(
                                fieldPath('chronic_disease_details'),
                            )}
                            className="md:col-span-2"
                        >
                            <ProperInput
                                id={fieldPath('chronic_disease_details')}
                                value={customer.chronic_disease_details ?? ''}
                                disabled={disabled}
                                textarea
                                onCommit={(v) =>
                                    onUpdateCustomer(
                                        'chronic_disease_details',
                                        v,
                                    )
                                }
                                placeholder="Describe the chronic disease or health condition"
                            />
                        </FormField>
                    )}
                </div>
            </div>

            {/* Documents & Photos */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">
                    Documents &amp; Photos
                </h3>
                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    {DOCUMENT_FIELDS.map((doc) => (
                        <DocumentField
                            key={doc.fileKey}
                            label={doc.label}
                            hint={doc.hint}
                            accept={doc.accept}
                            acceptedFileTypesLabel={doc.acceptedFileTypesLabel}
                            maxFileSizeKb={doc.maxFileSizeKb}
                            fileValue={
                                customer[doc.fileKey] as File | undefined
                            }
                            existingPath={customer[doc.documentKey]?.file_path}
                            existingFileName={
                                customer[doc.documentKey]?.file_name
                            }
                            useFileNameInput
                            fileNameValue={
                                (customer[doc.nameKey] as string | null) ?? null
                            }
                            isView={isView}
                            disabled={disabled}
                            error={getError(fieldPath(doc.fileKey))}
                            onSelect={(file) => {
                                onUpdateCustomer(doc.fileKey, file);
                                onUpdateCustomer(doc.removedKey, false);

                                if (useGeneratedDocumentName) {
                                    onUpdateCustomer(
                                        doc.nameKey,
                                        buildGeneratedFileName(doc.label),
                                    );
                                } else if (!customer[doc.nameKey]) {
                                    onUpdateCustomer(
                                        doc.nameKey,
                                        buildGeneratedFileName(doc.label),
                                    );
                                }
                            }}
                            onFileNameChange={(fileName) =>
                                onUpdateCustomer(doc.nameKey, fileName)
                            }
                            onClear={() => {
                                const hasExistingDocument = Boolean(
                                    customer[doc.documentKey],
                                );

                                onUpdateCustomer(doc.fileKey, null);
                                onUpdateCustomer(doc.nameKey, null);

                                if (hasExistingDocument) {
                                    onUpdateCustomer(doc.removedKey, true);
                                    onUpdateCustomer(doc.documentKey, null);
                                } else {
                                    onUpdateCustomer(doc.removedKey, false);
                                }
                            }}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
