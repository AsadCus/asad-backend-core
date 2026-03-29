import {
    CreatableCombobox,
    type CreatableComboboxOption,
} from '@/components/creatable-combobox';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { OptionType } from '@/types';
import * as React from 'react';

type CreatePayload = {
    id: number;
    name: string;
    value: string;
    is_active: boolean;
    is_default: boolean;
    sort_order: number;
};

interface PaymentMethodMasterComboboxProps {
    value?: string;
    options: OptionType[];
    onChange: (value: string) => void;
    onOptionsChange?: (options: OptionType[]) => void;
    disabled?: boolean;
    triggerId?: string;
    placeholder?: string;
    searchPlaceholder?: string;
    className?: string;
}

function toOptionType(option: CreatableComboboxOption): OptionType {
    return {
        label: option.label,
        value: option.value,
    };
}

function mapOptions(options: OptionType[]): CreatableComboboxOption[] {
    return options.map((option) => ({
        value: String(option.value),
        label: String(option.label),
    }));
}

export default function PaymentMethodMasterCombobox({
    value,
    options,
    onChange,
    onOptionsChange,
    disabled = false,
    triggerId,
    placeholder = 'Select payment method',
    searchPlaceholder = 'Search payment method',
    className,
}: PaymentMethodMasterComboboxProps) {
    const [comboboxOptions, setComboboxOptions] = React.useState<
        CreatableComboboxOption[]
    >(mapOptions(options));
    const [isCreateDialogOpen, setIsCreateDialogOpen] = React.useState(false);
    const [isCreating, setIsCreating] = React.useState(false);
    const [pendingCreateOption, setPendingCreateOption] =
        React.useState<CreatableComboboxOption | null>(null);
    const [newMethodName, setNewMethodName] = React.useState('');

    React.useEffect(() => {
        setComboboxOptions(mapOptions(options));
    }, [options]);

    const syncOptions = React.useCallback(
        (nextOptions: CreatableComboboxOption[]) => {
            setComboboxOptions(nextOptions);
            onOptionsChange?.(nextOptions.map(toOptionType));
        },
        [onOptionsChange],
    );

    const onCreateRequest = React.useCallback(
        ({ option }: { option: CreatableComboboxOption }) => {
            setPendingCreateOption(option);
            setNewMethodName(option.label);
            setIsCreateDialogOpen(true);
        },
        [],
    );

    const createPaymentMethod = React.useCallback(async () => {
        const name = newMethodName.trim();

        if (!name) {
            return;
        }

        setIsCreating(true);

        try {
            const csrfToken = (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement | null
            )?.content;

            const response = await fetch(
                '/product-services/payment-methods/quick-create',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        name,
                    }),
                },
            );

            if (!response.ok) {
                throw new Error('Failed to create payment method.');
            }

            const payload = (await response.json()) as CreatePayload;
            const createdOption: CreatableComboboxOption = {
                value: String(payload.value),
                label: String(payload.name),
            };

            const nextOptions = [
                ...comboboxOptions.filter(
                    (option) => option.value !== createdOption.value,
                ),
                createdOption,
            ];

            syncOptions(nextOptions);
            onChange(createdOption.value);
            setIsCreateDialogOpen(false);
            setPendingCreateOption(null);
            setNewMethodName('');
        } catch {
            const fallbackOption = pendingCreateOption;

            if (fallbackOption) {
                const nextOptions = [
                    ...comboboxOptions.filter(
                        (option) => option.value !== fallbackOption.value,
                    ),
                    fallbackOption,
                ];

                syncOptions(nextOptions);
                onChange(fallbackOption.value);
            }

            setIsCreateDialogOpen(false);
        } finally {
            setIsCreating(false);
        }
    }, [
        comboboxOptions,
        newMethodName,
        onChange,
        pendingCreateOption,
        syncOptions,
    ]);

    return (
        <>
            <CreatableCombobox
                options={comboboxOptions}
                triggerId={triggerId}
                value={value}
                disabled={disabled}
                placeholder={placeholder}
                searchPlaceholder={searchPlaceholder}
                className={className}
                onChange={onChange}
                onCreateRequest={onCreateRequest}
            />

            <Dialog
                open={isCreateDialogOpen}
                onOpenChange={setIsCreateDialogOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create Payment Method</DialogTitle>
                        <DialogDescription>
                            Add a new payment method and use it immediately.
                        </DialogDescription>
                    </DialogHeader>

                    <FormField label="Payment Method Name">
                        <Input
                            value={newMethodName}
                            onChange={(event) =>
                                setNewMethodName(event.target.value)
                            }
                            placeholder="Enter payment method name"
                        />
                    </FormField>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsCreateDialogOpen(false)}
                            disabled={isCreating}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            onClick={createPaymentMethod}
                            disabled={isCreating || !newMethodName.trim()}
                        >
                            {isCreating ? 'Creating...' : 'Create'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
