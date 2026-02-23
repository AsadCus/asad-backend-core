import { FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { UserSchema } from '../schema';
import { AdminFormFields } from './admin-form-fields';

interface SupplierFormFieldsProps {
    data: Pick<
        UserSchema,
        'name' | 'email' | 'contact' | 'company_name' | 'address'
    >;
    errors: Partial<Record<keyof UserSchema, string>>;
    isView: boolean;
    onChange: (
        field: 'name' | 'email' | 'contact' | 'company_name' | 'address',
        value: string,
    ) => void;
}

export function SupplierFormFields({
    data,
    errors,
    isView,
    onChange,
}: SupplierFormFieldsProps) {
    return (
        <div className="space-y-6">
            <AdminFormFields
                data={{
                    name: data.name,
                    email: data.email,
                    contact: data.contact,
                }}
                errors={errors}
                isView={isView}
                onChange={(field, value) => onChange(field, value)}
            />

            <div className="space-y-4">
                <h3 className="border-b py-2 text-xl font-semibold">
                    Supplier
                </h3>
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    <FormField
                        label="Company Name"
                        fieldRequirementsProps={{ required: true }}
                        htmlFor="company_name"
                        error={errors.company_name}
                    >
                        <Input
                            type="text"
                            id="company_name"
                            value={data.company_name}
                            onChange={(event) =>
                                onChange('company_name', event.target.value)
                            }
                            placeholder="Enter company name"
                            disabled={isView}
                        />
                    </FormField>

                    <FormField
                        label="Address"
                        fieldRequirementsProps={{ required: true }}
                        htmlFor="address"
                        error={errors.address}
                        className="md:col-span-2"
                    >
                        <Textarea
                            id="address"
                            value={
                                data.address
                                    ? data.address.replace(/<br>/g, '\n')
                                    : ''
                            }
                            onChange={(event) =>
                                onChange(
                                    'address',
                                    event.target.value.replace(/\n/g, '<br>'),
                                )
                            }
                            onKeyDown={(event) => {
                                if (event.key !== 'Enter') {
                                    return;
                                }

                                event.preventDefault();

                                const textarea = event.currentTarget;
                                const start = textarea.selectionStart;
                                const currentValue = textarea.value;
                                const insertText = event.shiftKey
                                    ? '<br>'
                                    : '\n';
                                const newValue =
                                    currentValue.substring(0, start) +
                                    insertText +
                                    currentValue.substring(start);

                                onChange(
                                    'address',
                                    newValue.replace(/\n/g, '<br>'),
                                );

                                setTimeout(() => {
                                    textarea.selectionStart =
                                        textarea.selectionEnd =
                                            start + insertText.length;
                                }, 0);
                            }}
                            placeholder="Enter address"
                            disabled={isView}
                        />
                    </FormField>
                </div>
            </div>
        </div>
    );
}
