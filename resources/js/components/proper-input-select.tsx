import {
    Command,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { OptionType } from '@/types';
import { CheckIcon, ChevronDown, X } from 'lucide-react';
import * as React from 'react';
import { Button } from './ui/button';
import { Separator } from './ui/separator';

interface ProperInputSelectProps {
    options: OptionType[];
    value?: string | number;
    onValueChange: (value: string | number) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    truncate?: number;
}

export function ProperInputSelect({
    options,
    value = '',
    onValueChange,
    placeholder = 'Select...',
    disabled = false,
    className,
    truncate = 100,
}: ProperInputSelectProps) {
    const [selectedValue, setSelectedValue] = React.useState<string | number>(
        value,
    );
    const [isPopoverOpen, setIsPopoverOpen] = React.useState(false);

    const selected = options.find(
        (option) => String(option.value) === String(value),
    );

    const onOptionSelect = (option: string) => {
        setSelectedValue(option);
        onValueChange?.(option);
        // setIsPopoverOpen(false);
    };

    const onClearAllOptions = () => {
        setSelectedValue('');
        onValueChange?.('');
        // setIsPopoverOpen(false);
    };

    const truncateLabel = (label: string, maxLength: number): string => {
        return label.length > maxLength
            ? label.slice(0, maxLength) + '…'
            : label;
    };

    const displayLabel = selected
        ? truncateLabel(selected.label, truncate)
        : placeholder;

    return (
        <Popover open={isPopoverOpen} onOpenChange={setIsPopoverOpen}>
            <PopoverTrigger asChild>
                <Button
                    onClick={() => setIsPopoverOpen((prev) => !prev)}
                    variant="outline"
                    role="combobox"
                    disabled={disabled}
                    aria-expanded={isPopoverOpen}
                    className={cn(
                        'w-full justify-between bg-transparent px-3 py-1 [&_svg]:pointer-events-auto',
                        !selected && 'text-muted-foreground',
                        className,
                    )}
                >
                    {selectedValue ? (
                        <div className="flex w-full items-center justify-between">
                            <div className="flex items-center text-foreground">
                                {displayLabel}
                            </div>
                            <div className="flex items-center justify-between">
                                {selectedValue && (
                                    <>
                                        <X
                                            className="cursor-pointer text-muted-foreground"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onClearAllOptions();
                                            }}
                                        />
                                        <Separator
                                            orientation="vertical"
                                            className="mx-1 flex h-full min-h-6"
                                        />
                                    </>
                                )}
                                <ChevronDown className="cursor-pointer text-muted-foreground" />
                            </div>
                        </div>
                    ) : (
                        <div className="mx-auto flex w-full items-center justify-between">
                            <span className="text-base text-muted-foreground">
                                {placeholder}
                            </span>
                            <ChevronDown className="cursor-pointer text-muted-foreground" />
                        </div>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className={cn(
                    'w-full max-w-[var(--radix-popover-trigger-width)] p-0',
                    className,
                )}
                align="start"
            >
                <Command>
                    <CommandInput placeholder="Search..." />
                    <CommandList className="max-h-[unset] overflow-y-hidden">
                        <CommandGroup className="max-h-[16rem] overflow-y-auto">
                            {options.map((option) => {
                                const isSelected =
                                    selectedValue === option.value;
                                return (
                                    <CommandItem
                                        key={option.value}
                                        onSelect={() =>
                                            onOptionSelect(option.value)
                                        }
                                        className="cursor-pointer"
                                    >
                                        <div
                                            className={cn(
                                                'mr-1 flex h-4 w-4 items-center justify-center',
                                                isSelected
                                                    ? 'text-primary'
                                                    : 'invisible',
                                            )}
                                        >
                                            <CheckIcon className="h-4 w-4" />
                                        </div>
                                        <span>{option.label}</span>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                        <CommandSeparator />
                        <CommandGroup>
                            <div className="flex items-center justify-between">
                                {selectedValue && (
                                    <>
                                        <CommandItem
                                            onSelect={onClearAllOptions}
                                            className="flex-1 cursor-pointer justify-center"
                                        >
                                            Clear
                                        </CommandItem>
                                        <Separator
                                            orientation="vertical"
                                            className="mx-2 flex h-full min-h-6"
                                        />
                                    </>
                                )}
                                <CommandItem
                                    onSelect={() => setIsPopoverOpen(false)}
                                    className="max-w-full flex-1 cursor-pointer justify-center"
                                >
                                    Close
                                </CommandItem>
                            </div>
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
