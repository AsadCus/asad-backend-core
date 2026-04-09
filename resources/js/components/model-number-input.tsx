import { FormField } from '@/components/form-field';
import { ProperInput } from '@/components/proper-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    NumberingFormatPayload,
    NumberingFormatRecord,
    createFormat,
    deleteFormat,
    fetchFormats,
    fetchSimpleState,
    suggestNumber,
    updateFormat,
} from '@/lib/numbering-formats';
import { cn } from '@/lib/utils';
import { Check, Loader2, Plus, Settings2, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface NumberingFormatFormState {
    id: number | null;
    format: string;
    increment_padding: number;
    increment_start: number;
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
}

interface ModelNumberInputProps {
    modelKey: string;
    label: string;
    inputId?: string;
    mode?: 'simple' | 'format';
    simpleValueMode?: 'next' | 'latest';
    value?: string | null;
    formatId?: number | null;
    onValueChange: (value: string) => void;
    onFormatIdChange?: (formatId: number | null) => void;
    onSimpleLatestPersist?: (value: string) => Promise<void> | void;
    disabled?: boolean;
    error?: string;
    hint?: string;
    skipInitialAutofill?: boolean;
}

const DEFAULT_FORMAT_FORM: NumberingFormatFormState = {
    id: null,
    format: '%I%',
    increment_padding: 4,
    increment_start: 1,
    is_default: false,
    is_active: true,
    sort_order: 1,
};

const FORMAT_TOKENS = [
    {
        label: 'Transaction Date',
        value: '%DD%',
        hint: 'Day (01-31)',
    },
    {
        label: 'Transaction Month',
        value: '%MM%',
        hint: 'Month (01-12)',
    },
    {
        label: 'Transaction Year (YY)',
        value: '%YY%',
        hint: '2-digit year',
    },
    {
        label: 'Transaction Year (YYYY)',
        value: '%YYYY%',
        hint: '4-digit year',
    },
    {
        label: 'Increment (Next Number)',
        value: '%I%',
        hint: 'Auto sequence number',
    },
] as const;

const hasTemplateToken = (value: string): boolean =>
    /%(DD|MM|YY|YYYY|I)%/.test(value);

const toFormatString = (format?: NumberingFormatRecord | null): string => {
    if (!format) {
        return DEFAULT_FORMAT_FORM.format;
    }

    const candidate = format.name.trim();

    if (hasTemplateToken(candidate)) {
        return candidate;
    }

    return DEFAULT_FORMAT_FORM.format;
};

const toFormState = (
    format?: NumberingFormatRecord | null,
): NumberingFormatFormState => {
    if (!format) {
        return { ...DEFAULT_FORMAT_FORM };
    }

    return {
        id: format.id,
        format: toFormatString(format),
        increment_padding: format.increment_padding,
        increment_start: format.increment_start,
        is_default: format.is_default,
        is_active: format.is_active,
        sort_order: format.sort_order,
    };
};

const toPayload = (
    state: NumberingFormatFormState,
    modelKey: string,
    incrementScope: 'format' | 'model',
): NumberingFormatPayload => ({
    model_key: modelKey,
    name: state.format.trim(),
    increment_padding: Math.max(1, Number(state.increment_padding || 1)),
    increment_start: Math.max(1, Number(state.increment_start || 1)),
    increment_scope: incrementScope,
    is_default: state.is_default,
    is_active: state.is_active,
    sort_order: Math.max(1, Number(state.sort_order || 1)),
});

const buildFormatPreview = (
    template: string,
    incrementStart: number,
    incrementPadding: number,
): string => {
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const yearFull = String(today.getFullYear());
    const yearShort = yearFull.slice(-2);
    const increment = String(Math.max(1, incrementStart)).padStart(
        Math.max(1, incrementPadding),
        '0',
    );

    return (template.trim() || '%I%')
        .replace(/%DD%/g, day)
        .replace(/%MM%/g, month)
        .replace(/%YYYY%/g, yearFull)
        .replace(/%YY%/g, yearShort)
        .replace(/%I%/g, increment);
};

const normalizeScope = (
    formats: NumberingFormatRecord[],
): 'format' | 'model' => {
    if (formats.length === 0) {
        return 'model';
    }

    return formats.every((format) => format.increment_scope === 'model')
        ? 'model'
        : 'format';
};

const hasTrailingNumber = (value: string): boolean => /\d$/.test(value);

export default function ModelNumberInput({
    modelKey,
    label,
    inputId,
    mode = 'simple',
    simpleValueMode = 'next',
    value,
    formatId,
    onValueChange,
    onFormatIdChange = () => {},
    onSimpleLatestPersist,
    disabled = false,
    error,
    hint,
    skipInitialAutofill = false,
}: ModelNumberInputProps) {
    const [formats, setFormats] = useState<NumberingFormatRecord[]>([]);
    const [isSuggesting, setIsSuggesting] = useState(false);
    const [isLoadingFormats, setIsLoadingFormats] = useState(false);
    const [isSavingFormat, setIsSavingFormat] = useState(false);
    const [deletingFormatId, setDeletingFormatId] = useState<number | null>(
        null,
    );
    const [openDialog, setOpenDialog] = useState(false);
    const [openFormatSelector, setOpenFormatSelector] = useState(false);
    const [openTokenSelector, setOpenTokenSelector] = useState(false);
    const [dialogError, setDialogError] = useState<string | null>(null);
    const [inlineError, setInlineError] = useState<string | null>(null);
    const [editingFormat, setEditingFormat] =
        useState<NumberingFormatFormState>(DEFAULT_FORMAT_FORM);
    const [modelIncrementScope, setModelIncrementScope] = useState<
        'format' | 'model'
    >('model');
    const isSimpleMode = mode === 'simple';
    const onValueChangeRef = useRef(onValueChange);
    const onSimpleLatestPersistRef = useRef(onSimpleLatestPersist);

    useEffect(() => {
        onValueChangeRef.current = onValueChange;
    }, [onValueChange]);

    useEffect(() => {
        onSimpleLatestPersistRef.current = onSimpleLatestPersist;
    }, [onSimpleLatestPersist]);

    const isDialogBusy =
        isLoadingFormats || isSavingFormat || deletingFormatId !== null;
    const isComponentBusy = disabled || isLoadingFormats || isSuggesting;
    const resolvedError = error ?? inlineError ?? undefined;

    const selectedFormat = useMemo(
        () => formats.find((format) => format.id === formatId) ?? null,
        [formats, formatId],
    );

    const handleSuggest = useCallback(
        async (nextFormatId?: number | null): Promise<void> => {
            setIsSuggesting(true);
            setInlineError(null);

            try {
                const suggestion = await suggestNumber(
                    modelKey,
                    nextFormatId ?? formatId,
                    'format',
                );
                onValueChangeRef.current(suggestion.number);
                onFormatIdChange(suggestion.format_id ?? null);
            } catch (exception) {
                setInlineError(
                    exception instanceof Error
                        ? exception.message
                        : 'Unable to suggest the next model number.',
                );
            } finally {
                setIsSuggesting(false);
            }
        },
        [formatId, modelKey, onFormatIdChange],
    );

    const loadFormats = useCallback(async (): Promise<void> => {
        setIsLoadingFormats(true);
        setDialogError(null);
        setInlineError(null);

        try {
            const nextFormats = await fetchFormats(modelKey);
            setFormats(nextFormats);
            setModelIncrementScope(normalizeScope(nextFormats));

            const shouldAutofillNumber =
                !skipInitialAutofill &&
                !formatId &&
                (value ?? '').trim().length === 0;

            if (shouldAutofillNumber) {
                const preferredFormat =
                    nextFormats.find((format) => format.is_default) ??
                    nextFormats[0] ??
                    null;

                if (preferredFormat) {
                    onFormatIdChange(preferredFormat.id);

                    if (!disabled) {
                        await handleSuggest(preferredFormat.id);
                    }
                } else if (!disabled) {
                    await handleSuggest(null);
                }
            }
        } catch (exception) {
            const message =
                exception instanceof Error
                    ? exception.message
                    : 'Unable to load model number formats.';
            setDialogError(message);
            setInlineError(message);
        } finally {
            setIsLoadingFormats(false);
        }
    }, [
        disabled,
        formatId,
        handleSuggest,
        modelKey,
        onFormatIdChange,
        skipInitialAutofill,
        value,
    ]);

    useEffect(() => {
        if (isSimpleMode) {
            return;
        }

        void loadFormats();
    }, [isSimpleMode, loadFormats]);

    useEffect(() => {
        if (!isSimpleMode) {
            return;
        }

        setInlineError(null);

        if (simpleValueMode === 'latest') {
            void (async () => {
                try {
                    const simpleState = await fetchSimpleState(modelKey);
                    onValueChangeRef.current(simpleState.latest_number ?? '');
                } catch (exception) {
                    setInlineError(
                        exception instanceof Error
                            ? exception.message
                            : 'Unable to load latest model number.',
                    );
                }
            })();

            return;
        }

        if (disabled || (value ?? '').trim().length > 0) {
            return;
        }

        void (async () => {
            setIsSuggesting(true);

            try {
                const suggestion = await suggestNumber(
                    modelKey,
                    null,
                    'simple',
                );
                onValueChangeRef.current(suggestion.number ?? '');
            } catch (exception) {
                setInlineError(
                    exception instanceof Error
                        ? exception.message
                        : 'Unable to suggest the next model number.',
                );
            } finally {
                setIsSuggesting(false);
            }
        })();
    }, [disabled, isSimpleMode, modelKey, simpleValueMode, value]);

    const handleFormatChange = async (nextValue: string): Promise<void> => {
        setInlineError(null);

        const parsed = Number(nextValue);
        const nextFormatId = Number.isNaN(parsed) ? null : parsed;

        onFormatIdChange(nextFormatId);

        if (!disabled && nextFormatId) {
            await handleSuggest(nextFormatId);
        }
    };

    if (isSimpleMode) {
        const simpleHint =
            simpleValueMode === 'latest'
                ? 'Set latest number. Next creation will increment from the last numeric suffix.'
                : 'Auto-fills by incrementing the last numeric suffix from latest number.';

        return (
            <FormField
                label={label}
                error={resolvedError}
                fieldRequirementsProps={{
                    hint: hint ?? simpleHint,
                }}
            >
                <ProperInput
                    id={inputId}
                    value={value ?? ''}
                    onCommit={(nextValue) => {
                        const normalizedValue = String(nextValue ?? '');

                        onValueChange(normalizedValue);

                        const trimmed = normalizedValue.trim();
                        const validationMessage =
                            trimmed === '' || hasTrailingNumber(trimmed)
                                ? null
                                : 'The number must end with a numeric value.';

                        setInlineError(validationMessage);

                        if (
                            simpleValueMode === 'latest' &&
                            onSimpleLatestPersistRef.current &&
                            validationMessage === null
                        ) {
                            void Promise.resolve(
                                onSimpleLatestPersistRef.current(trimmed),
                            ).catch((exception) => {
                                setInlineError(
                                    exception instanceof Error
                                        ? exception.message
                                        : 'Unable to save latest model number.',
                                );
                            });
                        }
                    }}
                    disabled={disabled}
                    placeholder="Enter model number"
                />
            </FormField>
        );
    }

    const applyScopeForAllFormats = async (
        nextScope: 'format' | 'model',
        skipId?: number,
    ): Promise<void> => {
        const updates = formats
            .filter((format) => format.id !== skipId)
            .filter((format) => format.increment_scope !== nextScope)
            .map((format) =>
                updateFormat(
                    format.id,
                    toPayload(toFormState(format), modelKey, nextScope),
                ),
            );

        if (updates.length > 0) {
            await Promise.all(updates);
        }
    };

    const handleOpenDialog = (): void => {
        setDialogError(null);
        setInlineError(null);
        setEditingFormat(toFormState(selectedFormat));
        setOpenDialog(true);
    };

    const resetFormatForm = (): void => {
        setEditingFormat(toFormState(null));
        setDialogError(null);
        setInlineError(null);
    };

    const handleSaveFormat = async (): Promise<void> => {
        if (isDialogBusy) {
            return;
        }

        setDialogError(null);
        setInlineError(null);

        if (editingFormat.format.trim().length === 0) {
            setDialogError('Format is required.');

            return;
        }

        if (!editingFormat.format.includes('%I%')) {
            setDialogError('Format must include %I% for the increment value.');

            return;
        }

        setIsSavingFormat(true);

        try {
            const payload = toPayload(
                editingFormat,
                modelKey,
                modelIncrementScope,
            );

            let persisted: NumberingFormatRecord;
            if (editingFormat.id) {
                persisted = await updateFormat(editingFormat.id, payload);
            } else {
                persisted = await createFormat(payload);
            }

            await applyScopeForAllFormats(modelIncrementScope, persisted.id);
            await loadFormats();
            onFormatIdChange(persisted.id);
            setEditingFormat(toFormState(persisted));
            await handleSuggest(persisted.id);
        } catch (exception) {
            const message =
                exception instanceof Error
                    ? exception.message
                    : 'Unable to save number format.';
            setDialogError(message);
            setInlineError(message);
        } finally {
            setIsSavingFormat(false);
        }
    };

    const handleDeleteFormat = async (id: number): Promise<void> => {
        const confirmed = window.confirm(
            'Delete this format? Existing records keep their number, and active default will fall back automatically.',
        );

        if (!confirmed) {
            return;
        }

        if (isDialogBusy) {
            return;
        }

        setDialogError(null);
        setInlineError(null);
        setDeletingFormatId(id);

        try {
            await deleteFormat(id);
            await loadFormats();

            if (formatId === id) {
                onFormatIdChange(null);
                onValueChange('');
            }

            resetFormatForm();
        } catch (exception) {
            const message =
                exception instanceof Error
                    ? exception.message
                    : 'Unable to delete number format.';
            setDialogError(message);
            setInlineError(message);
        } finally {
            setDeletingFormatId(null);
        }
    };

    return (
        <>
            <FormField
                label={label}
                error={resolvedError}
                fieldRequirementsProps={{
                    hint:
                        hint ??
                        'Use format picker to auto-generate, then adjust manually if needed.',
                }}
            >
                <Popover
                    open={openFormatSelector}
                    onOpenChange={(nextOpen) => {
                        if (isComponentBusy) {
                            setOpenFormatSelector(false);

                            return;
                        }

                        setOpenFormatSelector(nextOpen);
                    }}
                >
                    <PopoverTrigger asChild>
                        <div className="relative">
                            <Input
                                id={inputId}
                                value={value ?? ''}
                                onChange={(event) => {
                                    if (!isComponentBusy) {
                                        onValueChange(event.target.value);
                                    }
                                }}
                                onClick={() => {
                                    if (!isComponentBusy) {
                                        setOpenFormatSelector(true);
                                    }
                                }}
                                onFocus={(event) => {
                                    if (isComponentBusy) {
                                        event.currentTarget.select();
                                    }
                                }}
                                readOnly={isComponentBusy}
                                placeholder="Enter model number"
                                className={cn(
                                    'pr-11',
                                    isComponentBusy &&
                                        'bg-muted/40 text-muted-foreground',
                                )}
                            />
                            {!disabled && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={(event) => {
                                        event.preventDefault();
                                        event.stopPropagation();
                                        handleOpenDialog();
                                    }}
                                    disabled={isComponentBusy}
                                    size="icon"
                                    className="absolute top-1/2 right-1 h-7 w-7 -translate-y-1/2"
                                >
                                    {isSuggesting ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Settings2 className="h-4 w-4" />
                                    )}
                                    <span className="sr-only">
                                        Configure model number formats
                                    </span>
                                </Button>
                            )}
                        </div>
                    </PopoverTrigger>
                    <PopoverContent className="w-fit p-0" align="start">
                        <Command>
                            <CommandInput placeholder="Search format..." />
                            <CommandList>
                                <CommandEmpty className="p-2">
                                    No formats available for this model.
                                </CommandEmpty>
                                <CommandGroup heading="Available formats">
                                    <CommandItem
                                        value="default-format-auto"
                                        disabled={isComponentBusy}
                                        onSelect={() => {
                                            void handleSuggest(null);
                                            setOpenFormatSelector(false);
                                        }}
                                    >
                                        <div className="flex flex-col">
                                            <span className="font-medium">
                                                Default format (Auto)
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                Use default/shared sequence and
                                                fill next number.
                                            </span>
                                        </div>
                                        <Check
                                            className={cn(
                                                'ml-auto h-4 w-4',
                                                selectedFormat?.is_default
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                    </CommandItem>

                                    <CommandItem
                                        value="no-format-selected"
                                        disabled={isComponentBusy}
                                        onSelect={() => {
                                            onFormatIdChange(null);
                                            setOpenFormatSelector(false);
                                        }}
                                    >
                                        No format selected
                                        <Check
                                            className={cn(
                                                'ml-auto h-4 w-4',
                                                !formatId
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                    </CommandItem>

                                    {formats.map((format) => {
                                        const formatValue =
                                            toFormatString(format);

                                        return (
                                            <CommandItem
                                                key={format.id}
                                                value={`${formatValue} ${format.id}`}
                                                disabled={isComponentBusy}
                                                onSelect={() => {
                                                    void handleFormatChange(
                                                        String(format.id),
                                                    );
                                                    setOpenFormatSelector(
                                                        false,
                                                    );
                                                }}
                                            >
                                                <div className="flex flex-col">
                                                    <span className="font-medium">
                                                        {formatValue}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {format.is_default
                                                            ? 'Default'
                                                            : 'Custom'}
                                                    </span>
                                                </div>
                                                <Check
                                                    className={cn(
                                                        'ml-auto h-4 w-4',
                                                        format.id === formatId
                                                            ? 'opacity-100'
                                                            : 'opacity-0',
                                                    )}
                                                />
                                            </CommandItem>
                                        );
                                    })}
                                </CommandGroup>

                                <div className="border-t p-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="w-full justify-start"
                                        disabled={isComponentBusy}
                                        onClick={() => {
                                            resetFormatForm();
                                            setOpenDialog(true);
                                            setOpenFormatSelector(false);
                                        }}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create format
                                    </Button>
                                </div>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </FormField>

            <Dialog open={openDialog} onOpenChange={setOpenDialog}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Model Number Formats</DialogTitle>
                        <DialogDescription>
                            Manage available formats for {label.toLowerCase()}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 md:grid-cols-[1.25fr_1fr]">
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <div className="text-base font-medium">
                                    Available Formats
                                </div>
                                <Button
                                    size={'sm'}
                                    type="button"
                                    disabled={isDialogBusy}
                                    onClick={resetFormatForm}
                                >
                                    New
                                </Button>
                            </div>

                            <div className="space-y-2">
                                {formats.length === 0 && (
                                    <div className="rounded-md border border-dashed p-3 text-sm text-muted-foreground">
                                        No formats yet. Create your first format
                                        on the right.
                                    </div>
                                )}

                                {formats.map((format) => (
                                    <div
                                        key={format.id}
                                        className={cn(
                                            'cursor-pointer rounded-md border p-3 transition-colors hover:bg-muted/40',
                                            editingFormat.id === format.id &&
                                                'border-primary bg-primary/5',
                                            isDialogBusy &&
                                                'pointer-events-none opacity-70',
                                        )}
                                        onClick={() => {
                                            if (isDialogBusy) {
                                                return;
                                            }

                                            setEditingFormat(
                                                toFormState(format),
                                            );
                                        }}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold">
                                                    {toFormatString(format)}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {format.is_default
                                                        ? 'Default format'
                                                        : 'Custom format'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Preview:{' '}
                                                    {buildFormatPreview(
                                                        toFormatString(format),
                                                        format.increment_start,
                                                        format.increment_padding,
                                                    )}
                                                </p>
                                            </div>

                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={isDialogBusy}
                                                    onClick={(event) => {
                                                        event.stopPropagation();
                                                        void handleDeleteFormat(
                                                            format.id,
                                                        );
                                                    }}
                                                >
                                                    {deletingFormatId ===
                                                    format.id ? (
                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                    ) : (
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-3 rounded-md border p-3">
                            <div className="flex items-center justify-between">
                                <h4 className="text-base font-semibold">
                                    {editingFormat.id
                                        ? 'Edit Format'
                                        : 'Create Format'}
                                </h4>
                            </div>

                            <FormField label="Increment Scope (All Formats)">
                                <Select
                                    value={modelIncrementScope}
                                    disabled={isDialogBusy}
                                    onValueChange={(nextValue) =>
                                        setModelIncrementScope(
                                            nextValue as 'format' | 'model',
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select scope" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="model">
                                            Shared model sequence
                                        </SelectItem>
                                        <SelectItem value="format">
                                            Separate per format sequence
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField label="Format" htmlFor="format_value">
                                <div className="flex gap-2">
                                    <Input
                                        id="format_value"
                                        value={editingFormat.format}
                                        disabled={isDialogBusy}
                                        onChange={(event) =>
                                            setEditingFormat((current) => ({
                                                ...current,
                                                format: event.target.value,
                                            }))
                                        }
                                        placeholder="KGT-%DD%-%MM%-%YY%-%I%"
                                    />
                                    <Popover
                                        open={openTokenSelector}
                                        onOpenChange={(nextOpen) => {
                                            if (isDialogBusy) {
                                                setOpenTokenSelector(false);

                                                return;
                                            }

                                            setOpenTokenSelector(nextOpen);
                                        }}
                                    >
                                        <PopoverTrigger asChild>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                disabled={isDialogBusy}
                                            >
                                                <Plus className="h-4 w-4" />
                                                <span className="sr-only">
                                                    Add format token
                                                </span>
                                            </Button>
                                        </PopoverTrigger>
                                        <PopoverContent
                                            className="w-72 p-1"
                                            align="end"
                                        >
                                            <div className="space-y-1">
                                                {FORMAT_TOKENS.map((token) => (
                                                    <Button
                                                        key={token.value}
                                                        type="button"
                                                        variant="ghost"
                                                        className="h-auto w-full justify-start py-2"
                                                        disabled={isDialogBusy}
                                                        onClick={() => {
                                                            setEditingFormat(
                                                                (current) => ({
                                                                    ...current,
                                                                    format: `${current.format}${token.value}`,
                                                                }),
                                                            );
                                                            setOpenTokenSelector(
                                                                false,
                                                            );
                                                        }}
                                                    >
                                                        <span className="flex flex-col items-start">
                                                            <span className="font-medium">
                                                                {token.label}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {token.hint} (
                                                                {token.value})
                                                            </span>
                                                        </span>
                                                    </Button>
                                                ))}
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                </div>
                            </FormField>

                            <div className="grid grid-cols-2 gap-2">
                                <FormField
                                    label="Increment Digits"
                                    htmlFor="format_padding"
                                >
                                    <Input
                                        id="format_padding"
                                        type="number"
                                        min={1}
                                        max={12}
                                        disabled={isDialogBusy}
                                        value={editingFormat.increment_padding}
                                        onChange={(event) =>
                                            setEditingFormat((current) => ({
                                                ...current,
                                                increment_padding: Number(
                                                    event.target.value || 1,
                                                ),
                                            }))
                                        }
                                    />
                                </FormField>

                                <FormField
                                    label="Start From"
                                    htmlFor="format_start"
                                >
                                    <Input
                                        id="format_start"
                                        type="number"
                                        min={1}
                                        disabled={isDialogBusy}
                                        value={editingFormat.increment_start}
                                        onChange={(event) =>
                                            setEditingFormat((current) => ({
                                                ...current,
                                                increment_start: Number(
                                                    event.target.value || 1,
                                                ),
                                            }))
                                        }
                                    />
                                </FormField>
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        disabled={isDialogBusy}
                                        checked={editingFormat.is_default}
                                        onCheckedChange={(checked) =>
                                            setEditingFormat((current) => ({
                                                ...current,
                                                is_default: Boolean(checked),
                                            }))
                                        }
                                    />
                                    Set as default
                                </label>

                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        disabled={isDialogBusy}
                                        checked={editingFormat.is_active}
                                        onCheckedChange={(checked) =>
                                            setEditingFormat((current) => ({
                                                ...current,
                                                is_active: Boolean(checked),
                                            }))
                                        }
                                    />
                                    Active
                                </label>
                            </div>

                            <div className="rounded-md border bg-muted/30 p-2 text-xs">
                                Preview:{' '}
                                {buildFormatPreview(
                                    editingFormat.format,
                                    editingFormat.increment_start,
                                    editingFormat.increment_padding,
                                )}
                            </div>

                            {dialogError && (
                                <p className="text-sm text-destructive">
                                    {dialogError}
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isDialogBusy}
                            onClick={() => setOpenDialog(false)}
                        >
                            Close
                        </Button>
                        <Button
                            type="button"
                            disabled={isDialogBusy}
                            onClick={() => void handleSaveFormat()}
                        >
                            {isSavingFormat && (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            )}
                            {isSavingFormat ? 'Saving...' : 'Save Format'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
