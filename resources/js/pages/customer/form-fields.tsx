import { BooleanSelect } from '@/components/boolean-select';
import { DatePickerField } from '@/components/date-picker';
import { DocumentField } from '@/components/document-field';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import {
    genderOptions,
    maritalStatusOptions,
    type CustomerMemberSchema,
} from './schema';

interface MemberFormFieldsProps {
    member: CustomerMemberSchema;
    index: number;
    isView: boolean;
    processing: boolean;
    getError: (path: string) => string | undefined;
    onUpdate: (
        field: keyof CustomerMemberSchema,
        value: string | boolean | File | null,
    ) => void;
}

const DOCUMENT_FIELDS = [
    {
        fileKey: 'passport_file' as const,
        pathKey: 'passport_path' as const,
        label: 'Passport Copy',
        accept: '.jpg,.jpeg,.png,.pdf',
        hint: 'Upload a scan or photo of the passport bio-data page',
    },
    {
        fileKey: 'photo_file' as const,
        pathKey: 'photo_path' as const,
        label: 'Photo',
        accept: '.jpg,.jpeg,.png',
        hint: 'Upload a photo',
    },
] as const;

export default function MemberFormFields({
    member,
    index,
    isView,
    processing,
    getError,
    onUpdate,
}: MemberFormFieldsProps) {
    const disabled = isView || processing;
    const prefix = `members.${index}`;

    return (
        <div className="space-y-6">
            {/* Personal Information */}
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Personal Information</h3>
                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    <FormField
                        label="Full Name"
                        fieldRequirementsProps={{
                            required: true,
                            hint: "Member's full name as per passport or IC",
                            example: 'Ahmad bin Abdullah',
                        }}
                        htmlFor={`${prefix}.name`}
                        error={getError(`${prefix}.name`)}
                    >
                        <ProperInput
                            id={`${prefix}.name`}
                            value={member.name}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('name', v)}
                            placeholder="Enter full name"
                        />
                    </FormField>

                    <FormField
                        label="Email Address"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Valid email address for communication',
                            format: 'example@domain.com',
                        }}
                        htmlFor={`${prefix}.email`}
                        error={getError(`${prefix}.email`)}
                    >
                        <ProperInput
                            id={`${prefix}.email`}
                            value={member.email}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('email', v)}
                            placeholder="email@example.com"
                        />
                    </FormField>

                    <FormField
                        label="Contact Number"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Phone number with country code',
                            format: '+60 12-345 6789',
                        }}
                        htmlFor={`${prefix}.contact_number`}
                        error={getError(`${prefix}.contact_number`)}
                    >
                        <ProperInput
                            id={`${prefix}.contact_number`}
                            value={member.contact_number}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('contact_number', v)}
                            placeholder="+60 12 345 6789"
                        />
                    </FormField>

                    <FormField
                        label="NRIC Number"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'National Registration Identity Card number',
                            format: '123456-12-1234',
                        }}
                        htmlFor={`${prefix}.nric_number`}
                        error={getError(`${prefix}.nric_number`)}
                    >
                        <ProperInput
                            id={`${prefix}.nric_number`}
                            value={member.nric_number}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('nric_number', v)}
                            placeholder="NRIC number"
                        />
                    </FormField>

                    <FormField
                        label="Gender"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Select gender as per official documents',
                        }}
                        htmlFor={`${prefix}.gender`}
                        error={getError(`${prefix}.gender`)}
                    >
                        <ProperInputSelect
                            options={genderOptions}
                            value={member.gender}
                            onValueChange={(v) => onUpdate('gender', String(v))}
                            placeholder="Select gender"
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Marital Status"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Current marital status',
                        }}
                        htmlFor={`${prefix}.marital_status`}
                        error={getError(`${prefix}.marital_status`)}
                    >
                        <ProperInputSelect
                            options={maritalStatusOptions}
                            value={member.marital_status}
                            onValueChange={(v) =>
                                onUpdate('marital_status', String(v))
                            }
                            placeholder="Select marital status"
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Date of Birth"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Birth date as per passport or IC',
                        }}
                        htmlFor={`${prefix}.date_of_birth`}
                        error={getError(`${prefix}.date_of_birth`)}
                    >
                        <DatePickerField
                            id={`${prefix}.date_of_birth`}
                            value={member.date_of_birth}
                            disabled={disabled}
                            fromYear={1930}
                            toYear={new Date().getFullYear()}
                            onChange={(v) => onUpdate('date_of_birth', v)}
                        />
                    </FormField>

                    <FormField
                        label="Place of Birth"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'City or country where you were born',
                            example: 'Kuala Lumpur, Malaysia',
                        }}
                        htmlFor={`${prefix}.place_of_birth`}
                        error={getError(`${prefix}.place_of_birth`)}
                    >
                        <ProperInput
                            id={`${prefix}.place_of_birth`}
                            value={member.place_of_birth}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('place_of_birth', v)}
                            placeholder="Enter place of birth"
                        />
                    </FormField>

                    <FormField
                        label="Nationality"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Citizenship as per passport',
                            example: 'Malaysian',
                        }}
                        htmlFor={`${prefix}.nationality`}
                        error={getError(`${prefix}.nationality`)}
                    >
                        <ProperInput
                            id={`${prefix}.nationality`}
                            value={member.nationality}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('nationality', v)}
                            placeholder="e.g. Malaysian"
                        />
                    </FormField>

                    <FormField
                        label="Residential Address"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Full address including street, city, and postal code',
                            example: '123, Jalan Sultan, 50000 Kuala Lumpur',
                        }}
                        htmlFor={`${prefix}.address`}
                        error={getError(`${prefix}.address`)}
                        className="md:col-span-2"
                    >
                        <ProperInput
                            id={`${prefix}.address`}
                            value={member.address}
                            disabled={disabled}
                            textarea
                            onCommit={(v) => onUpdate('address', v)}
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
                            required: true,
                            hint: 'Passport number exactly as shown on passport',
                            format: 'A12345678',
                        }}
                        htmlFor={`${prefix}.passport_number`}
                        error={getError(`${prefix}.passport_number`)}
                    >
                        <ProperInput
                            id={`${prefix}.passport_number`}
                            value={member.passport_number}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('passport_number', v)}
                            placeholder="Passport number"
                        />
                    </FormField>

                    <FormField
                        label="Place of Issue"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'City or country where passport was issued',
                            example: 'Kuala Lumpur / Malaysia',
                        }}
                        htmlFor={`${prefix}.passport_place_of_issue`}
                        error={getError(`${prefix}.passport_place_of_issue`)}
                    >
                        <ProperInput
                            id={`${prefix}.passport_place_of_issue`}
                            value={member.passport_place_of_issue}
                            disabled={disabled}
                            onCommit={(v) =>
                                onUpdate('passport_place_of_issue', v)
                            }
                            placeholder="e.g. Kuala Lumpur"
                        />
                    </FormField>

                    <FormField
                        label="Issue Date"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Date when passport was issued',
                        }}
                        htmlFor={`${prefix}.passport_issue_date`}
                        error={getError(`${prefix}.passport_issue_date`)}
                    >
                        <DatePickerField
                            id={`${prefix}.passport_issue_date`}
                            value={member.passport_issue_date}
                            disabled={disabled}
                            fromYear={2000}
                            toYear={new Date().getFullYear()}
                            onChange={(v) => onUpdate('passport_issue_date', v)}
                        />
                    </FormField>

                    <FormField
                        label="Expiry Date"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Passport must be valid for at least 6 months from travel date',
                        }}
                        htmlFor={`${prefix}.passport_expiry_date`}
                        error={getError(`${prefix}.passport_expiry_date`)}
                    >
                        <DatePickerField
                            id={`${prefix}.passport_expiry_date`}
                            value={member.passport_expiry_date}
                            disabled={disabled}
                            fromYear={new Date().getFullYear()}
                            toYear={new Date().getFullYear() + 15}
                            onChange={(v) =>
                                onUpdate('passport_expiry_date', v)
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
                        htmlFor={`${prefix}.first_time_umrah`}
                    >
                        <BooleanSelect
                            id={`${prefix}.first_time_umrah`}
                            value={!!member.first_time_umrah}
                            onChange={(v) => onUpdate('first_time_umrah', v)}
                            disabled={disabled}
                        />
                    </FormField>

                    <FormField
                        label="Has chronic disease"
                        fieldRequirementsProps={{
                            hint: 'Indicate if you have any chronic health conditions',
                        }}
                        htmlFor={`${prefix}.has_chronic_disease`}
                    >
                        <BooleanSelect
                            id={`${prefix}.has_chronic_disease`}
                            value={!!member.has_chronic_disease}
                            onChange={(v) => {
                                onUpdate('has_chronic_disease', v);
                            }}
                            disabled={disabled}
                        />
                    </FormField>

                    {member.has_chronic_disease === true && (
                        <FormField
                            label="Disease Details"
                            fieldRequirementsProps={{
                                hint: 'Provide details about the chronic disease or health condition',
                                example: 'Diabetes, Hypertension, Asthma, etc.',
                            }}
                            htmlFor={`${prefix}.chronic_disease_details`}
                            error={getError(
                                `${prefix}.chronic_disease_details`,
                            )}
                            className="md:col-span-2"
                        >
                            <ProperInput
                                id={`${prefix}.chronic_disease_details`}
                                value={member.chronic_disease_details ?? ''}
                                disabled={disabled}
                                textarea
                                onCommit={(v) =>
                                    onUpdate('chronic_disease_details', v)
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
                            fileValue={member[doc.fileKey] as File | undefined}
                            existingPath={
                                (member[doc.pathKey] as string | null) ??
                                undefined
                            }
                            isView={isView}
                            disabled={disabled}
                            error={getError(`${prefix}.${doc.fileKey}`)}
                            onSelect={(file) => onUpdate(doc.fileKey, file)}
                            onClear={() => {
                                onUpdate(doc.fileKey, null);
                                onUpdate(doc.pathKey, null);
                            }}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
