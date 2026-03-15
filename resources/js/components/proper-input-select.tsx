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
import {
    MultiSelect,
    type MultiSelectOption,
    type MultiSelectProps,
} from './multi-select';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from './ui/select';
import { Separator } from './ui/separator';

type ProperInputSelectMode = 'default' | 'classic' | 'multi';

interface ProperInputSelectCommonProps {
    options: OptionType[] | MultiSelectOption[];
    mode?: ProperInputSelectMode;
    id?: string;
    name?: string;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    truncate?: number;
    searchable?: boolean;
    autoCloseOnSelect?: boolean;
    showClearAction?: boolean;
    showCloseAction?: boolean;
    size?: 'compact' | 'default';

    // Multi mode support
    variant?: MultiSelectProps['variant'];
    animation?: MultiSelectProps['animation'];
    animationConfig?: MultiSelectProps['animationConfig'];
    maxCount?: MultiSelectProps['maxCount'];
    modalPopover?: MultiSelectProps['modalPopover'];
    hideSelectAll?: MultiSelectProps['hideSelectAll'];
    emptyIndicator?: MultiSelectProps['emptyIndicator'];
    autoSize?: MultiSelectProps['autoSize'];
    singleLine?: MultiSelectProps['singleLine'];
    popoverClassName?: MultiSelectProps['popoverClassName'];
    responsive?: MultiSelectProps['responsive'];
    minWidth?: MultiSelectProps['minWidth'];
    maxWidth?: MultiSelectProps['maxWidth'];
    deduplicateOptions?: MultiSelectProps['deduplicateOptions'];
    resetOnDefaultValueChange?: MultiSelectProps['resetOnDefaultValueChange'];
    closeOnSelect?: MultiSelectProps['closeOnSelect'];
}

interface ProperInputSelectSingleModeProps
    extends ProperInputSelectCommonProps {
    mode?: Exclude<ProperInputSelectMode, 'multi'>;
    value?: string | number;
    onValueChange: (value: string | number) => void;
}

interface ProperInputSelectMultiModeProps extends ProperInputSelectCommonProps {
    mode: 'multi';
    value?: string[];
    onValueChange: (value: string[]) => void;
}

type ProperInputSelectProps =
    | ProperInputSelectSingleModeProps
    | ProperInputSelectMultiModeProps;

export function ProperInputSelect(
    props: ProperInputSelectMultiModeProps,
): React.JSX.Element;
export function ProperInputSelect(
    props: ProperInputSelectSingleModeProps,
): React.JSX.Element;
export function ProperInputSelect({
    options,
    value = '',
    onValueChange,
    mode = 'default',
    id,
    name,
    placeholder = 'Select...',
    disabled = false,
    className,
    truncate = 100,
    searchable = true,
    autoCloseOnSelect = true,
    showClearAction = true,
    showCloseAction = true,
    size = 'default',
    variant,
    animation,
    animationConfig,
    maxCount,
    modalPopover,
    hideSelectAll,
    emptyIndicator,
    autoSize,
    singleLine,
    popoverClassName,
    responsive,
    minWidth,
    maxWidth,
    deduplicateOptions,
    resetOnDefaultValueChange,
    closeOnSelect,
}: ProperInputSelectProps) {
    const [isPopoverOpen, setIsPopoverOpen] = React.useState(false);
    const triggerRef = React.useRef<HTMLButtonElement | null>(null);
    const [portalContainer, setPortalContainer] =
        React.useState<HTMLElement | null>(null);

    React.useEffect(() => {
        const container = triggerRef.current?.closest(
            '[data-slot="dialog-content"]',
        );
        setPortalContainer((container as HTMLElement | null) ?? null);
    }, [isPopoverOpen]);

    const singleOptions = React.useMemo<OptionType[]>(() => {
        return options.map((option) => ({
            value: String(option.value),
            label: option.label,
        }));
    }, [options]);

    const multiOptions = React.useMemo<MultiSelectOption[]>(() => {
        return options.map((option) => {
            const maybeMultiOption = option as MultiSelectOption;

            return {
                ...maybeMultiOption,
                value: String(option.value),
                label: option.label,
            };
        });
    }, [options]);

    const selectedValue = Array.isArray(value) ? '' : (value ?? '');
    const selected = singleOptions.find(
        (option) => String(option.value) === String(selectedValue),
    );

    const singleModeOnValueChange =
        onValueChange as ProperInputSelectSingleModeProps['onValueChange'];
    const multiModeOnValueChange =
        onValueChange as ProperInputSelectMultiModeProps['onValueChange'];

    const onOptionSelect = (optionValue: string) => {
        const matchedOption = singleOptions.find(
            (option) => option.value === optionValue,
        );

        singleModeOnValueChange(matchedOption?.value ?? optionValue);

        if (autoCloseOnSelect) {
            setIsPopoverOpen(false);
        }
    };

    const onClearAllOptions = () => {
        singleModeOnValueChange('');
    };

    const truncateLabel = (label: string, maxLength: number): string => {
        return label.length > maxLength
            ? label.slice(0, maxLength) + '…'
            : label;
    };

    const displayLabel = selected
        ? truncateLabel(selected.label, truncate)
        : placeholder;

    if (mode === 'multi') {
        const selectedValues = Array.isArray(value)
            ? value.map((item) => String(item))
            : [];

        return (
            <MultiSelect
                id={id}
                name={name}
                options={multiOptions}
                defaultValue={selectedValues}
                onValueChange={multiModeOnValueChange}
                placeholder={placeholder}
                disabled={disabled}
                className={className}
                variant={variant}
                animation={animation}
                animationConfig={animationConfig}
                maxCount={maxCount}
                modalPopover={modalPopover}
                hideSelectAll={hideSelectAll}
                searchable={searchable}
                emptyIndicator={emptyIndicator}
                autoSize={autoSize}
                singleLine={singleLine}
                popoverClassName={popoverClassName}
                responsive={responsive}
                minWidth={minWidth}
                maxWidth={maxWidth}
                deduplicateOptions={deduplicateOptions}
                resetOnDefaultValueChange={resetOnDefaultValueChange}
                closeOnSelect={closeOnSelect}
            />
        );
    }

    if (mode === 'classic') {
        const selectedClassicValue = String(selectedValue || '');

        return (
            <>
                <Select
                    value={selectedClassicValue}
                    onValueChange={onOptionSelect}
                    disabled={disabled}
                >
                    <SelectTrigger id={id} className={className}>
                        <SelectValue placeholder={placeholder} />
                    </SelectTrigger>
                    <SelectContent>
                        {singleOptions.map((option) => (
                            <SelectItem
                                key={option.value}
                                value={String(option.value)}
                            >
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {name && (
                    <input
                        type="hidden"
                        name={name}
                        value={selectedClassicValue}
                        disabled={disabled}
                    />
                )}
            </>
        );
    }

    return (
        <>
            <Popover open={isPopoverOpen} onOpenChange={setIsPopoverOpen}>
                <PopoverTrigger asChild>
                    <button
                        ref={triggerRef}
                        id={id}
                        type="button"
                        onClick={() => setIsPopoverOpen((prev) => !prev)}
                        role="combobox"
                        disabled={disabled}
                        aria-expanded={isPopoverOpen}
                        data-placeholder={!selected ? '' : undefined}
                        className={cn(
                            "flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-base whitespace-nowrap shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 data-[placeholder]:text-muted-foreground dark:bg-input/30 dark:hover:bg-input/50 dark:aria-invalid:ring-destructive/40 [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [&_svg:not([class*='text-'])]:text-muted-foreground",
                            size === 'compact'
                                ? 'h-6 px-2 py-1 text-base sm:h-7'
                                : '',
                            className,
                        )}
                    >
                        {selected ? (
                            <div className="flex w-full items-center gap-2">
                                <span className="line-clamp-1 flex-1 text-left">
                                    {displayLabel}
                                </span>
                                <div className="flex items-center gap-1">
                                    {selectedValue && showClearAction && (
                                        <>
                                            <X
                                                className="size-4 cursor-pointer text-muted-foreground"
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
                                    <ChevronDown className="size-4 opacity-50" />
                                </div>
                            </div>
                        ) : (
                            <div className="flex w-full items-center justify-between gap-2">
                                <span className="line-clamp-1 text-left text-muted-foreground">
                                    {placeholder}
                                </span>
                                <ChevronDown className="size-4 opacity-50" />
                            </div>
                        )}
                    </button>
                </PopoverTrigger>
                <PopoverContent
                    portalContainer={portalContainer}
                    className={cn(
                        'relative z-50 max-h-96 w-[var(--radix-popover-trigger-width)] overflow-hidden rounded-md border bg-popover p-0 text-popover-foreground shadow-md',
                        className,
                    )}
                    align="start"
                >
                    <Command>
                        {searchable && <CommandInput placeholder="Search..." />}
                        <CommandList className="max-h-96">
                            <CommandGroup className="max-h-76 overflow-y-auto p-1">
                                {singleOptions.map((option) => {
                                    const isSelected =
                                        String(selectedValue) ===
                                        String(option.value);
                                    return (
                                        <CommandItem
                                            key={option.value}
                                            onSelect={() =>
                                                onOptionSelect(option.value)
                                            }
                                            className="relative flex w-full cursor-default items-center gap-2 rounded-sm py-1.5 pr-8 pl-2 text-base outline-hidden select-none focus:bg-accent focus:text-accent-foreground"
                                        >
                                            <span className="line-clamp-1">
                                                {option.label}
                                            </span>
                                            <span
                                                className={cn(
                                                    'absolute right-2 flex size-3.5 items-center justify-center',
                                                    !isSelected && 'invisible',
                                                )}
                                            >
                                                <CheckIcon className="size-4" />
                                            </span>
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                            {(showClearAction || showCloseAction) && (
                                <>
                                    <CommandSeparator />
                                    <CommandGroup>
                                        <div className="flex items-center justify-between">
                                            {selectedValue &&
                                                showClearAction && (
                                                    <>
                                                        <CommandItem
                                                            onSelect={
                                                                onClearAllOptions
                                                            }
                                                            className="flex-1 cursor-pointer justify-center"
                                                        >
                                                            Clear
                                                        </CommandItem>
                                                        {showCloseAction && (
                                                            <Separator
                                                                orientation="vertical"
                                                                className="mx-2 flex h-full min-h-6"
                                                            />
                                                        )}
                                                    </>
                                                )}
                                            {showCloseAction && (
                                                <CommandItem
                                                    onSelect={() =>
                                                        setIsPopoverOpen(false)
                                                    }
                                                    className="max-w-full flex-1 cursor-pointer justify-center"
                                                >
                                                    Close
                                                </CommandItem>
                                            )}
                                        </div>
                                    </CommandGroup>
                                </>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            {name && (
                <input
                    type="hidden"
                    name={name}
                    value={String(selectedValue || '')}
                    disabled={disabled}
                />
            )}
        </>
    );
}
