import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { ProperInputSelect } from '@/components/proper-input-select';
import { genderOptions } from '@/pages/customer/schema';
import { officialTypeOptions } from '@/pages/packages/schema';
import { parseDisplayDate } from '@/lib/utils';

export interface OfficialFieldsData {
    name: string;
    email: string;
    contact_number: string;
    type: string;
    nationality: string;
    passport_number: string;
    passport_issue_date: string;
    passport_expiry_date: string;
    passport_place_of_issue: string;
    gender: string;
    date_of_birth: string;
    place_of_birth: string;
}

interface OfficialFormFieldsProps {
    official: OfficialFieldsData;
    isView: boolean;
    processing: boolean;
    getError: (path: string) => string | undefined;
    onUpdate: (field: keyof OfficialFieldsData, value: string) => void;
}

export default function OfficialFormFields({
    official,
    isView,
    processing,
    getError,
    onUpdate,
}: OfficialFormFieldsProps) {
    const disabled = isView || processing;
    const passportIssueDate = official.passport_issue_date
        ? parseDisplayDate(official.passport_issue_date)
        : null;

    return (
        <div className="space-y-6">
            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Official Information</h3>
                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    <FormField
                        label="Type"
                        fieldRequirementsProps={{
                            required: true,
                            hint: 'Mutawif, Mutawifah, or Official',
                        }}
                        htmlFor="official_type"
                        error={getError('type')}
                    >
                        <ProperInputSelect
                            id="official_type"
                            options={officialTypeOptions}
                            value={official.type}
                            onValueChange={(v) => onUpdate('type', String(v))}
                            placeholder="Select type"
                            disabled={disabled}
                            searchable={false}
                        />
                    </FormField>

                    <FormField
                        label="Full Name"
                        fieldRequirementsProps={{
                            required: true,
                            hint: "Official's full name as per passport",
                        }}
                        htmlFor="official_name"
                        error={getError('name')}
                    >
                        <ProperInput
                            id="official_name"
                            value={official.name}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('name', v)}
                            placeholder="Enter full name"
                        />
                    </FormField>

                    <FormField
                        label="Email Address"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Optional. Officials cannot log in.',
                            format: 'email@example.com',
                        }}
                        htmlFor="official_email"
                        error={getError('email')}
                    >
                        <ProperInput
                            id="official_email"
                            value={official.email}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('email', v)}
                            placeholder="email@example.com"
                        />
                    </FormField>

                    <FormField
                        label="Contact Number"
                        fieldRequirementsProps={{
                            required: false,
                            hint: 'Phone number with country code',
                            format: '+65 8765 4321',
                        }}
                        htmlFor="official_contact"
                        error={getError('contact_number')}
                    >
                        <ProperInput
                            id="official_contact"
                            value={official.contact_number}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('contact_number', v)}
                            placeholder="+65 8765 4321"
                        />
                    </FormField>

                    <FormField
                        label="Gender"
                        htmlFor="official_gender"
                        error={getError('gender')}
                    >
                        <ProperInputSelect
                            id="official_gender"
                            options={genderOptions}
                            value={official.gender}
                            onValueChange={(v) => onUpdate('gender', String(v))}
                            placeholder="Select gender"
                            disabled={disabled}
                            searchable={false}
                        />
                    </FormField>

                    <FormField
                        label="Nationality"
                        fieldRequirementsProps={{
                            example: 'Singaporean, Malaysian, etc.',
                        }}
                        htmlFor="official_nationality"
                        error={getError('nationality')}
                    >
                        <ProperInput
                            id="official_nationality"
                            value={official.nationality}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('nationality', v)}
                            placeholder="e.g. Singaporean"
                        />
                    </FormField>

                    <FormField
                        label="Date of Birth"
                        htmlFor="official_dob"
                        error={getError('date_of_birth')}
                    >
                        <DatePickerField
                            id="official_dob"
                            value={official.date_of_birth}
                            disabled={disabled}
                            fromYear={1930}
                            toYear={new Date().getFullYear()}
                            onChange={(v) => onUpdate('date_of_birth', v)}
                        />
                    </FormField>

                    <FormField
                        label="Place of Birth"
                        htmlFor="official_place_of_birth"
                        error={getError('place_of_birth')}
                    >
                        <ProperInput
                            id="official_place_of_birth"
                            value={official.place_of_birth}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('place_of_birth', v)}
                            placeholder="Enter place of birth"
                        />
                    </FormField>
                </div>
            </div>

            <div className="space-y-4">
                <h3 className="text-xl font-semibold">Passport Information</h3>
                <div className="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
                    <FormField
                        label="Passport Number"
                        htmlFor="official_passport_number"
                        error={getError('passport_number')}
                    >
                        <ProperInput
                            id="official_passport_number"
                            value={official.passport_number}
                            disabled={disabled}
                            onCommit={(v) => onUpdate('passport_number', v)}
                            placeholder="Enter passport number"
                        />
                    </FormField>

                    <FormField
                        label="Place of Issue"
                        htmlFor="official_passport_place_of_issue"
                        error={getError('passport_place_of_issue')}
                    >
                        <ProperInput
                            id="official_passport_place_of_issue"
                            value={official.passport_place_of_issue}
                            disabled={disabled}
                            onCommit={(v) =>
                                onUpdate('passport_place_of_issue', v)
                            }
                            placeholder="e.g. Singapore"
                        />
                    </FormField>

                    <FormField
                        label="Issue Date"
                        htmlFor="official_passport_issue_date"
                        error={getError('passport_issue_date')}
                    >
                        <DatePickerField
                            id="official_passport_issue_date"
                            value={official.passport_issue_date || ''}
                            disabled={disabled}
                            fromYear={2000}
                            toYear={new Date().getFullYear()}
                            onChange={(v) => onUpdate('passport_issue_date', v)}
                        />
                    </FormField>

                    <FormField
                        label="Expiry Date"
                        htmlFor="official_passport_expiry_date"
                        error={getError('passport_expiry_date')}
                    >
                        <DatePickerField
                            id="official_passport_expiry_date"
                            value={official.passport_expiry_date || ''}
                            disabled={disabled}
                            fromYear={
                                passportIssueDate
                                    ? passportIssueDate.getFullYear()
                                    : new Date().getFullYear()
                            }
                            toYear={new Date().getFullYear() + 15}
                            onChange={(v) => onUpdate('passport_expiry_date', v)}
                        />
                    </FormField>
                </div>
            </div>
        </div>
    );
}
