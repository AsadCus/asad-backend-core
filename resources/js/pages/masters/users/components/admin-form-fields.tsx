import { FormField } from '@/components/form-field';
import { ProperInputSelect } from '@/components/proper-input-select';
import { Input } from '@/components/ui/input';
import { OptionType } from '@/types';
import { UserSchema } from '../schema';

interface AdminFormFieldsProps {
    data: Pick<UserSchema, 'name' | 'email' | 'contact' | 'branch_id'>;
    errors: Partial<Record<keyof UserSchema, string>>;
    branches: OptionType[];
    isView: boolean;
    onChange: (
        field: 'name' | 'email' | 'contact' | 'branch_id',
        value: string,
    ) => void;
}

export function AdminFormFields({
    data,
    errors,
    branches,
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

                <FormField
                    label="Branch"
                    fieldRequirementsProps={{ required: true }}
                    error={errors.branch_id}
                >
                    <ProperInputSelect
                        disabled={isView}
                        options={branches}
                        value={data.branch_id}
                        onValueChange={(value) =>
                            onChange('branch_id', String(value))
                        }
                        placeholder="Select branch"
                    />
                </FormField>
            </div>
        </div>
    );
}
