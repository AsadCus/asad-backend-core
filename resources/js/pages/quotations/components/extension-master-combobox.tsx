import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { Check, Plus } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ProperInput } from '../../../components/proper-input';

export type ExtensionMasterComboboxOption = {
    id: number;
    name: string;
    type?: string;
    calculation_mode?: string | null;
    calculation_value?: string | number | null;
    is_active?: boolean;
};

interface ExtensionMasterComboboxProps {
    value: number | null;
    options: ExtensionMasterComboboxOption[];
    extensionType: 'discount' | 'tax' | 'additional' | 'item';
    triggerMode?: 'input' | 'button';
    triggerButtonLabel?: string;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    disabled?: boolean;
    placeholder?: string;
    clearLabel?: string;
    allowClear?: boolean;
    className?: string;
    onSelect: (option: ExtensionMasterComboboxOption) => void;
    onClear?: () => void;
    onOptionsChange?: (options: ExtensionMasterComboboxOption[]) => void;
}

function formatNumber(value: number): string {
    if (!Number.isFinite(value)) {
        return '0';
    }

    if (Math.floor(value) === value) {
        return String(value);
    }

    return value.toFixed(2).replace(/\.00$/, '');
}

function getOptionLabel(option: ExtensionMasterComboboxOption): string {
    const mode = String(option.calculation_mode ?? 'fixed');
    const amount = Math.abs(Number(option.calculation_value ?? 0));

    if (mode === 'percentage') {
        return `${option.name} ${formatNumber(amount)}%`;
    }

    return option.name;
}

export default function ExtensionMasterCombobox({
    value,
    options,
    extensionType,
    triggerMode = 'input',
    triggerButtonLabel,
    open,
    onOpenChange,
    disabled = false,
    placeholder = 'Select option',
    clearLabel = 'No selection',
    allowClear = false,
    className,
    onSelect,
    onClear,
    onOptionsChange,
}: ExtensionMasterComboboxProps) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const [searchValue, setSearchValue] = useState('');
    const [openCreateDialog, setOpenCreateDialog] = useState(false);
    const [newName, setNewName] = useState('');
    const [newCalculationMode, setNewCalculationMode] = useState<
        'fixed' | 'percentage'
    >('fixed');
    const [newItemType, setNewItemType] = useState<'tax' | 'discount'>('tax');
    const [newCalculationValue, setNewCalculationValue] = useState('');
    const [localOptions, setLocalOptions] = useState(options);

    const createType =
        extensionType === 'additional'
            ? 'other'
            : extensionType === 'item'
              ? newItemType
              : extensionType;

    const extensionTypeLabel =
        extensionType === 'additional'
            ? 'additional charges'
            : extensionType === 'item'
              ? 'item'
              : extensionType;

    useEffect(() => {
        setLocalOptions(options);
    }, [options]);

    const filteredOptions = useMemo(() => {
        const visibleOptions = localOptions
            .filter((option) => option.is_active !== false)
            .filter((option) => {
                const optionType = String(option.type ?? extensionType)
                    .trim()
                    .toLowerCase();

                if (extensionType === 'additional') {
                    return ['credit_card', 'other'].includes(optionType);
                }

                if (extensionType === 'item') {
                    return ['tax', 'discount'].includes(optionType);
                }

                return optionType === extensionType;
            })
            .filter((option) => {
                if (!searchValue.trim()) {
                    return true;
                }

                return getOptionLabel(option)
                    .toLowerCase()
                    .includes(searchValue.toLowerCase());
            });

        const uniqueOptions = new Map<string, ExtensionMasterComboboxOption>();

        visibleOptions.forEach((option) => {
            const optionId = Number(option.id ?? 0);
            const optionType = String(option.type ?? extensionType)
                .trim()
                .toLowerCase();
            const optionMode = String(option.calculation_mode ?? 'fixed')
                .trim()
                .toLowerCase();
            const optionValue = Number(option.calculation_value ?? 0);
            const optionName = String(option.name ?? '')
                .trim()
                .toLowerCase();
            const optionLabel = getOptionLabel(option).trim().toLowerCase();

            const key =
                extensionType === 'item'
                    ? [optionType, optionLabel].join('|')
                    : optionId > 0
                      ? `id:${optionId}`
                      : [optionName, optionType, optionMode, optionValue].join(
                            '|',
                        );

            if (!uniqueOptions.has(key)) {
                uniqueOptions.set(key, option);
            }
        });

        return Array.from(uniqueOptions.values());
    }, [extensionType, localOptions, searchValue]);

    const selectedOption = useMemo(() => {
        const selectedId = Number(value ?? 0);

        if (!Number.isFinite(selectedId) || selectedId <= 0) {
            return null;
        }

        return (
            localOptions.find((option) => Number(option.id) === selectedId) ??
            null
        );
    }, [localOptions, value]);

    const selectedText = selectedOption
        ? getOptionLabel(selectedOption)
        : placeholder;
    const popoverAlign = triggerMode === 'button' ? 'start' : 'end';

    const isPopoverOpen = open ?? uncontrolledOpen;

    const setPopoverOpen = (nextOpen: boolean) => {
        if (open === undefined) {
            setUncontrolledOpen(nextOpen);
        }

        onOpenChange?.(nextOpen);
    };

    const handleCreate = () => {
        if (!newName.trim()) {
            toast.error('Name is required.');

            return;
        }

        const createdOption: ExtensionMasterComboboxOption = {
            id: -Date.now(),
            name: newName.trim(),
            type: createType,
            calculation_mode: newCalculationMode,
            calculation_value: Number(newCalculationValue || 0),
            is_active: true,
        };

        const nextOptions = [...localOptions, createdOption];
        setLocalOptions(nextOptions);
        onOptionsChange?.(nextOptions);
        onSelect(createdOption);

        setOpenCreateDialog(false);
        setPopoverOpen(false);
        setSearchValue('');
        setNewName('');
        setNewCalculationMode('fixed');
        setNewCalculationValue('');
        toast.success('Extension created.');
    };

    if (disabled) {
        if (triggerMode === 'button') {
            return (
                <Button
                    type="button"
                    variant="link"
                    className={cn('h-auto p-0', className)}
                    disabled
                >
                    {triggerButtonLabel ?? selectedText}
                </Button>
            );
        }

        return (
            <ProperInput
                value={selectedOption ? getOptionLabel(selectedOption) : ''}
                disabled
                // size="compact"
                className={className}
                onCommit={() => {}}
            />
        );
    }

    return (
        <>
            <Popover open={isPopoverOpen} onOpenChange={setPopoverOpen}>
                <PopoverTrigger asChild>
                    {triggerMode === 'button' ? (
                        <Button
                            type="button"
                            variant="link"
                            className={cn('h-auto p-0', className)}
                            onClick={() => setPopoverOpen(true)}
                        >
                            {triggerButtonLabel ?? selectedText}
                        </Button>
                    ) : (
                        <div
                            onClick={(event) => {
                                event.preventDefault();
                                setPopoverOpen(true);
                            }}
                            onMouseDown={(event) => {
                                event.preventDefault();
                            }}
                        >
                            <ProperInput
                                value={selectedText}
                                disabled={disabled}
                                // size="compact"
                                className={className}
                                placeholder={placeholder}
                                onCommit={() => setPopoverOpen(true)}
                            />
                        </div>
                    )}
                </PopoverTrigger>
                <PopoverContent className="w-fit p-0" align={popoverAlign}>
                    <Command shouldFilter={false}>
                        <CommandInput
                            placeholder={`Search ${extensionTypeLabel}...`}
                            value={searchValue}
                            onValueChange={setSearchValue}
                        />
                        <CommandList>
                            <CommandEmpty>
                                <div className="py-6 text-center text-base text-muted-foreground">
                                    No matching {extensionTypeLabel} options.
                                </div>
                            </CommandEmpty>

                            <CommandGroup heading="Available Options">
                                {allowClear && (
                                    <CommandItem
                                        value={`clear-${extensionType}`}
                                        onSelect={() => {
                                            onClear?.();
                                            setPopoverOpen(false);
                                        }}
                                        className="cursor-pointer"
                                    >
                                        <div className="flex w-full items-center justify-between gap-2">
                                            <span>{clearLabel}</span>
                                            <Check
                                                className={cn(
                                                    'h-4 w-4',
                                                    value == null
                                                        ? 'opacity-100'
                                                        : 'opacity-0',
                                                )}
                                            />
                                        </div>
                                    </CommandItem>
                                )}

                                {filteredOptions.map((option) => {
                                    const optionLabel = getOptionLabel(option);

                                    return (
                                        <CommandItem
                                            key={option.id}
                                            value={`option-${option.id}-${optionLabel}`}
                                            onSelect={() => {
                                                onSelect(option);
                                                setPopoverOpen(false);
                                                setSearchValue('');
                                            }}
                                            className="cursor-pointer"
                                        >
                                            <div className="flex w-full items-center justify-between gap-2">
                                                <span>{optionLabel}</span>
                                                <Check
                                                    className={cn(
                                                        'h-4 w-4',
                                                        Number(value) ===
                                                            Number(option.id)
                                                            ? 'opacity-100'
                                                            : 'opacity-0',
                                                    )}
                                                />
                                            </div>
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>

                            <Separator className="my-1" />
                            <CommandGroup>
                                <CommandItem
                                    value={`create-${extensionType}-${searchValue || 'new'}`}
                                    onSelect={() => {
                                        setNewName(searchValue || '');
                                        setNewCalculationMode('fixed');
                                        setNewItemType('tax');
                                        setNewCalculationValue('');
                                        setOpenCreateDialog(true);
                                    }}
                                    className="cursor-pointer"
                                >
                                    <div className="flex items-center gap-2">
                                        <Plus className="h-4 w-4 text-primary" />
                                        <span>
                                            Create {extensionTypeLabel}{' '}
                                            extension
                                        </span>
                                    </div>
                                </CommandItem>
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            <Dialog
                open={openCreateDialog}
                onOpenChange={(nextOpen) => {
                    if (!isCreating) {
                        setOpenCreateDialog(nextOpen);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Create {extensionTypeLabel} extension
                        </DialogTitle>
                        <DialogDescription>
                            Add a {extensionTypeLabel} extension for this form.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        {extensionType === 'item' && (
                            <div className="space-y-1">
                                <Label htmlFor="new-item-type">Type</Label>
                                <Select
                                    value={newItemType}
                                    disabled={false}
                                    onValueChange={(value) =>
                                        setNewItemType(
                                            value as 'tax' | 'discount',
                                        )
                                    }
                                >
                                    <SelectTrigger id="new-item-type">
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="tax">Tax</SelectItem>
                                        <SelectItem value="discount">
                                            Discount
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <div className="space-y-1">
                            <Label htmlFor={`new-${extensionType}-name`}>
                                Name
                            </Label>
                            <Input
                                id={`new-${extensionType}-name`}
                                value={newName}
                                onChange={(event) =>
                                    setNewName(event.target.value)
                                }
                                placeholder="Enter name"
                                disabled={false}
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor={`new-${extensionType}-mode`}>
                                Calculation
                            </Label>
                            <Select
                                value={newCalculationMode}
                                disabled={false}
                                onValueChange={(value) =>
                                    setNewCalculationMode(
                                        value as typeof newCalculationMode,
                                    )
                                }
                            >
                                <SelectTrigger id={`new-${extensionType}-mode`}>
                                    <SelectValue placeholder="Select calculation mode" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fixed">
                                        Fixed Amount
                                    </SelectItem>
                                    <SelectItem value="percentage">
                                        Percentage
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor={`new-${extensionType}-value`}>
                                {newCalculationMode === 'percentage'
                                    ? 'Value (%)'
                                    : 'Value'}
                            </Label>
                            <Input
                                id={`new-${extensionType}-value`}
                                type="number"
                                step="any"
                                min="0"
                                value={newCalculationValue}
                                onChange={(event) =>
                                    setNewCalculationValue(event.target.value)
                                }
                                placeholder="0"
                                disabled={false}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={false}
                            onClick={() => setOpenCreateDialog(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={false}
                            onClick={handleCreate}
                        >
                            Create
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
