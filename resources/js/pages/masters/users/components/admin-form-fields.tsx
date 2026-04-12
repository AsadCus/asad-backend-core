import { FormField } from '@/components/form-field';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Input } from '@/components/ui/input';
import { OptionType } from '@/types';
import { UserSchema } from '../schema';

interface AdminFormFieldsProps {
    data: Pick<UserSchema, 'name' | 'email' | 'contact' | 'scope_ids'>;
    errors: Partial<Record<keyof UserSchema, string>>;
    scopeMode?: 'country' | 'branch';
    scopeOptions?: OptionType[];
    isView: boolean;
    onChange: (
        field: 'name' | 'email' | 'contact' | 'scope_ids',
        value: string | string[],
    ) => void;
}

export function AdminFormFields({
    data,
    errors,
    scopeMode = 'country',
    scopeOptions = [],
    isView,
    onChange,
}: AdminFormFieldsProps) {
    return (
        <div className="space-y-4">
            <h3 className="text-xl font-semibold">Profile</h3>
            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
                <FormField
                    label="Name"
                    fieldRequirementsProps={{ required: true }}
                    htmlFor="name"
                    error={errors.name}
                >
                    <Input
                        type="text"
                        id="name"
                        value={data.name}
                        onChange={(event) =>
                            onChange('name', event.target.value)
                        }
                        placeholder="Name"
                        disabled={isView}
                    />
                </FormField>

                <FormField
                    label="Email"
                    fieldRequirementsProps={{
                        required: true,
                        format: 'example@domain.com',
                    }}
                    htmlFor="email"
                    error={errors.email}
                >
                    <Input
                        type="email"
                        id="email"
                        value={data.email}
                        onChange={(event) =>
                            onChange('email', event.target.value)
                        }
                        placeholder="Email"
                        disabled={isView}
                    />
                </FormField>

                <FormField
                    label="Contact"
                    fieldRequirementsProps={{
                        hint: 'Phone number for primary contact',
                    }}
                    htmlFor="contact"
                    error={errors.contact}
                >
                    <Input
                        type="text"
                        id="contact"
                        value={data.contact}
                        onChange={(event) =>
                            onChange('contact', event.target.value)
                        }
                        placeholder="Contact"
                        disabled={isView}
                    />
                </FormField>

                {scopeOptions.length > 0 && (
                    <FormField
                        label={
                            scopeMode === 'branch' ? 'Branches' : 'Countries'
                        }
                        fieldRequirementsProps={{ required: true }}
                        error={errors.scope_ids}
                    >
                        <ProperInputSelect
                            mode="multi"
                            disabled={isView}
                            options={scopeOptions}
                            value={data.scope_ids ?? []}
                            onValueChange={(value) =>
                                onChange('scope_ids', value)
                            }
                            placeholder={
                                scopeMode === 'branch'
                                    ? 'Select branches'
                                    : 'Select countries'
                            }
                        />
                    </FormField>
                )}
            </div>
        </div>
    );
}
