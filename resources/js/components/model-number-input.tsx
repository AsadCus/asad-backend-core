import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    suggestNumber,
    updateFormat,
} from '@/lib/numbering-formats';
import { cn } from '@/lib/utils';
import { Loader2, Settings2, Sparkles, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface NumberingFormatFormState {
    id: number | null;
    name: string;
    prefix: string;
    separator: string;
    include_year: boolean;
    year_format: string;
    increment_padding: number;
    increment_start: number;
    increment_scope: 'format' | 'model';
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
}

interface ModelNumberInputProps {
    modelKey: string;
    label: string;
    value?: string | null;
    formatId?: number | null;
    onValueChange: (value: string) => void;
    onFormatIdChange: (formatId: number | null) => void;
    disabled?: boolean;
    error?: string;
    hint?: string;
}

const DEFAULT_FORMAT_FORM: NumberingFormatFormState = {
    id: null,
    name: '',
    prefix: '',
    separator: '-',
    include_year: true,
    year_format: 'Y',
    increment_padding: 4,
    increment_start: 1,
    increment_scope: 'format',
    is_default: false,
    is_active: true,
    sort_order: 1,
};

const toFormState = (
    format?: NumberingFormatRecord | null,
): NumberingFormatFormState => {
    if (!format) {
        return { ...DEFAULT_FORMAT_FORM };
    }

    return {
        id: format.id,
        name: format.name,
        prefix: format.prefix ?? '',
        separator: format.separator,
        include_year: format.include_year,
        year_format: format.year_format,
        increment_padding: format.increment_padding,
        increment_start: format.increment_start,
        increment_scope: format.increment_scope,
        is_default: format.is_default,
        is_active: format.is_active,
        sort_order: format.sort_order,
    };
};

const toPayload = (
    state: NumberingFormatFormState,
    modelKey: string,
): NumberingFormatPayload => ({
    model_key: modelKey,
    name: state.name.trim(),
    prefix: state.prefix.trim(),
    separator: state.separator.trim(),
    include_year: state.include_year,
    year_format: state.year_format.trim() || 'Y',
    increment_padding: Math.max(1, Number(state.increment_padding || 1)),
    increment_start: Math.max(1, Number(state.increment_start || 1)),
    increment_scope: state.increment_scope,
    is_default: state.is_default,
    is_active: state.is_active,
    sort_order: Math.max(1, Number(state.sort_order || 1)),
});

const buildFormatPreview = (state: NumberingFormatFormState): string => {
    const parts: string[] = [];

    if (state.prefix.trim().length > 0) {
        parts.push(state.prefix.trim());
    }

    if (state.include_year) {
        parts.push(state.year_format === 'y' ? '26' : '2026');
    }

    parts.push(
        String(state.increment_start).padStart(state.increment_padding, '0'),
    );

    return parts.join(state.separator || '-');
};

export default function ModelNumberInput({
    modelKey,
    label,
    value,
    formatId,
    onValueChange,
    onFormatIdChange,
    disabled = false,
    error,
    hint,
}: ModelNumberInputProps) {
    const [formats, setFormats] = useState<NumberingFormatRecord[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isSuggesting, setIsSuggesting] = useState(false);
    const [openDialog, setOpenDialog] = useState(false);
    const [dialogError, setDialogError] = useState<string | null>(null);
    const [editingFormat, setEditingFormat] =
        useState<NumberingFormatFormState>(DEFAULT_FORMAT_FORM);

    const selectedFormat = useMemo(
        () => formats.find((format) => format.id === formatId) ?? null,
        [formats, formatId],
    );

    const loadFormats = async (): Promise<void> => {
        setIsLoading(true);
        setDialogError(null);

        try {
            const nextFormats = await fetchFormats(modelKey);
            setFormats(nextFormats);

            if (!formatId) {
                const defaultFormat = nextFormats.find(
                    (format) => format.is_default,
                );

                if (defaultFormat) {
                    onFormatIdChange(defaultFormat.id);
                }
            }
        } catch (exception) {
            setDialogError(
                exception instanceof Error
                    ? exception.message
                    : 'Unable to load model number formats.',
            );
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        void loadFormats();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [modelKey]);

    const handleSuggest = async (
        nextFormatId?: number | null,
    ): Promise<void> => {
        setIsSuggesting(true);

        try {
            const suggestion = await suggestNumber(
                modelKey,
                nextFormatId ?? formatId,
            );
            onValueChange(suggestion.number);
            onFormatIdChange(suggestion.format_id);
        } catch {
            // Keep current value when suggestion fails.
        } finally {
            setIsSuggesting(false);
        }
    };

    const handleFormatChange = async (nextValue: string): Promise<void> => {
        const parsed = Number(nextValue);
        const nextFormatId = Number.isNaN(parsed) ? null : parsed;

        onFormatIdChange(nextFormatId);

        if (!disabled && nextFormatId) {
            await handleSuggest(nextFormatId);
        }
    };

    const handleOpenDialog = (): void => {
        setDialogError(null);
        setEditingFormat(toFormState(selectedFormat));
        setOpenDialog(true);
    };

    const resetFormatForm = (): void => {
        setEditingFormat(toFormState(null));
    };

    const handleSaveFormat = async (): Promise<void> => {
        setDialogError(null);

        if (editingFormat.name.trim().length === 0) {
            setDialogError('Format name is required.');

            return;
        }

        try {
            const payload = toPayload(editingFormat, modelKey);

            let persisted: NumberingFormatRecord;
            if (editingFormat.id) {
                persisted = await updateFormat(editingFormat.id, payload);
            } else {
                persisted = await createFormat(payload);
            }

            await loadFormats();
            onFormatIdChange(persisted.id);
            setEditingFormat(toFormState(persisted));
            await handleSuggest(persisted.id);
        } catch (exception) {
            setDialogError(
                exception instanceof Error
                    ? exception.message
                    : 'Unable to save number format.',
            );
        }
    };

    const handleDeleteFormat = async (id: number): Promise<void> => {
        const confirmed = window.confirm(
            'Delete this format? Existing records keep their number, and active default will fall back automatically.',
        );

        if (!confirmed) {
            return;
        }

        setDialogError(null);

        try {
            await deleteFormat(id);
            await loadFormats();

            if (formatId === id) {
                onFormatIdChange(null);
                onValueChange('');
            }

            resetFormatForm();
        } catch (exception) {
            setDialogError(
                exception instanceof Error
                    ? exception.message
                    : 'Unable to delete number format.',
            );
        }
    };

    return (
        <>
            <FormField
                label={label}
                error={error}
                fieldRequirementsProps={{
                    hint:
                        hint ??
                        'You can edit this number manually or choose a format to suggest the next number.',
                }}
            >
                <div className="space-y-2">
                    <div className="grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto]">
                        <Select
                            value={formatId ? String(formatId) : '__none__'}
                            onValueChange={(nextValue) => {
                                if (nextValue === '__none__') {
                                    onFormatIdChange(null);

                                    return;
                                }

                                void handleFormatChange(nextValue);
                            }}
                            disabled={disabled || isLoading}
                        >
                            <SelectTrigger>
                                <SelectValue
                                    placeholder={
                                        isLoading
                                            ? 'Loading formats...'
                                            : 'Select number format'
                                    }
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">
                                    No format selected
                                </SelectItem>
                                {formats.map((format) => (
                                    <SelectItem
                                        key={format.id}
                                        value={String(format.id)}
                                    >
                                        {format.name}
                                        {format.is_default ? ' (Default)' : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleOpenDialog}
                            disabled={disabled}
                            size="icon"
                            className="w-full md:w-10"
                        >
                            <Settings2 className="h-4 w-4" />
                            <span className="sr-only">
                                Configure model number formats
                            </span>
                        </Button>
                    </div>

                    <div className="grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto]">
                        <Input
                            value={value ?? ''}
                            onChange={(event) =>
                                onValueChange(event.target.value)
                            }
                            disabled={disabled}
                            placeholder="Enter model number"
                        />

                        <Button
                            type="button"
                            variant="secondary"
                            disabled={disabled || isSuggesting}
                            onClick={() => void handleSuggest()}
                            className="w-full md:w-auto"
                        >
                            {isSuggesting ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Sparkles className="mr-2 h-4 w-4" />
                            )}
                            Suggest next
                        </Button>
                    </div>
                </div>
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
                            <div className="text-sm font-medium">
                                Available Formats
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
                                            'rounded-md border p-3',
                                            editingFormat.id === format.id &&
                                                'border-primary bg-primary/5',
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <div>
                                                <p className="text-sm font-semibold">
                                                    {format.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {format.prefix ??
                                                        'No prefix'}
                                                    {format.include_year
                                                        ? `${format.separator}${format.year_format}`
                                                        : ''}
                                                    {format.separator}
                                                    {'#'.repeat(
                                                        format.increment_padding,
                                                    )}
                                                </p>
                                            </div>

                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setEditingFormat(
                                                            toFormState(format),
                                                        )
                                                    }
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        void handleDeleteFormat(
                                                            format.id,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-3 rounded-md border p-3">
                            <div className="flex items-center justify-between">
                                <h4 className="text-sm font-semibold">
                                    {editingFormat.id
                                        ? 'Edit Format'
                                        : 'Create Format'}
                                </h4>
                                {editingFormat.id && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={resetFormatForm}
                                    >
                                        New
                                    </Button>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="format_name">Format Name</Label>
                                <Input
                                    id="format_name"
                                    value={editingFormat.name}
                                    onChange={(event) =>
                                        setEditingFormat((current) => ({
                                            ...current,
                                            name: event.target.value,
                                        }))
                                    }
                                    placeholder="Default"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="format_prefix">Prefix</Label>
                                <Input
                                    id="format_prefix"
                                    value={editingFormat.prefix}
                                    onChange={(event) =>
                                        setEditingFormat((current) => ({
                                            ...current,
                                            prefix: event.target.value,
                                        }))
                                    }
                                    placeholder="KTG"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <div className="space-y-2">
                                    <Label htmlFor="format_separator">
                                        Separator
                                    </Label>
                                    <Input
                                        id="format_separator"
                                        value={editingFormat.separator}
                                        onChange={(event) =>
                                            setEditingFormat((current) => ({
                                                ...current,
                                                separator: event.target.value,
                                            }))
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="format_year">
                                        Year Format
                                    </Label>
                                    <Input
                                        id="format_year"
                                        value={editingFormat.year_format}
                                        onChange={(event) =>
                                            setEditingFormat((current) => ({
                                                ...current,
                                                year_format:
                                                    event.target.value || 'Y',
                                            }))
                                        }
                                        disabled={!editingFormat.include_year}
                                        placeholder="Y"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <div className="space-y-2">
                                    <Label htmlFor="format_padding">
                                        Increment Digits
                                    </Label>
                                    <Input
                                        id="format_padding"
                                        type="number"
                                        min={1}
                                        max={12}
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
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="format_start">
                                        Start From
                                    </Label>
                                    <Input
                                        id="format_start"
                                        type="number"
                                        min={1}
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
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Increment Scope</Label>
                                <Select
                                    value={editingFormat.increment_scope}
                                    onValueChange={(nextValue) =>
                                        setEditingFormat((current) => ({
                                            ...current,
                                            increment_scope: nextValue as
                                                | 'format'
                                                | 'model',
                                        }))
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select scope" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="format">
                                            Per format sequence
                                        </SelectItem>
                                        <SelectItem value="model">
                                            Shared model sequence
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid grid-cols-2 gap-2">
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={editingFormat.include_year}
                                        onCheckedChange={(checked) =>
                                            setEditingFormat((current) => ({
                                                ...current,
                                                include_year: Boolean(checked),
                                            }))
                                        }
                                    />
                                    Include year
                                </label>

                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
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
                                Preview: {buildFormatPreview(editingFormat)}
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
                            onClick={() => setOpenDialog(false)}
                        >
                            Close
                        </Button>
                        <Button
                            type="button"
                            onClick={() => void handleSaveFormat()}
                        >
                            Save Format
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
