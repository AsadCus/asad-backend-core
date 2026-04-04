import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { Eye, FileText, Trash2 } from 'lucide-react';
import type { ModuleTemplate, RegisteredModule } from './types';

interface ModuleTemplateSectionProps {
    selectedModule: string;
    setSelectedModule: (key: string) => void;
    onPreview: (key: string) => void;
    activeModule: ModuleTemplate;
    activeDefinition: RegisteredModule | undefined;
    builtinModules: RegisteredModule[];
    registeredModules: RegisteredModule[];
    updateModule: (field: keyof ModuleTemplate, value: string | boolean) => void;
    updateModuleSignatureStampNameDateVisibility: (value: boolean) => void;
    handleDeleteModule: (key: string) => void;
    /** Rendered element — caller controls key, no wrapper needed */
    AddModuleDialog: React.ReactNode;
}

interface ModuleRowProps {
    module: RegisteredModule;
    isSelected: boolean;
    isBuiltin: boolean;
    onSelect: () => void;
    onPreview: () => void;
    onDelete: () => void;
}

function ModuleRow({
    module,
    isSelected,
    isBuiltin,
    onSelect,
    onPreview,
    onDelete,
}: ModuleRowProps) {
    return (
        <div
            className={cn(
                'group flex items-center gap-2.5 rounded-lg border px-3.5 py-2.5 transition-all cursor-pointer',
                isSelected
                    ? 'border-primary/40 bg-primary/5 shadow-sm'
                    : 'border-transparent hover:border-border hover:bg-muted/50',
            )}
            onClick={onSelect}
        >
            {/* Active indicator */}
            <span
                className={cn(
                    'h-1.5 w-1.5 shrink-0 rounded-full transition-colors',
                    isSelected ? 'bg-primary' : 'bg-muted-foreground/30',
                )}
            />

            {/* Label */}
            <span
                className={cn(
                    'flex-1 truncate text-sm',
                    isSelected ? 'font-semibold text-primary' : 'font-medium text-foreground',
                )}
            >
                {module.label}
            </span>

            {/* Document type badge */}
            <span className="hidden shrink-0 rounded bg-muted px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-muted-foreground xl:inline">
                {module.document_type}
            </span>

            {/* Action buttons — visible on hover or selected */}
            <div className={cn(
                'flex shrink-0 items-center gap-0.5 transition-opacity',
                isSelected ? 'opacity-100' : 'opacity-40 group-hover:opacity-100',
            )}>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-6 w-6 hover:text-blue-600"
                    title={`Preview ${module.label}`}
                    onClick={(e) => {
                        e.stopPropagation();
                        onPreview();
                    }}
                >
                    <Eye className="h-3 w-3" />
                </Button>

                {!isBuiltin && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6 hover:text-destructive"
                        title={`Delete ${module.label}`}
                        onClick={(e) => {
                            e.stopPropagation();
                            onDelete();
                        }}
                    >
                        <Trash2 className="h-3 w-3" />
                    </Button>
                )}
            </div>
        </div>
    );
}

/** Reusable toggle row for document element switches */
function ToggleRow({
    title,
    description,
    checked,
    onCheckedChange,
}: {
    title: string;
    description: string;
    checked: boolean;
    onCheckedChange: (v: boolean) => void;
}) {
    return (
        <div className="flex items-start justify-between gap-4 rounded-lg border p-3.5 lg:p-4">
            <div className="min-w-0">
                <p className="text-sm font-semibold leading-tight">{title}</p>
                <p className="mt-1 text-xs text-muted-foreground">{description}</p>
            </div>
            <Switch
                checked={checked}
                onCheckedChange={onCheckedChange}
                className="shrink-0"
            />
        </div>
    );
}

export function ModuleTemplateSection({
    selectedModule,
    setSelectedModule,
    onPreview,
    activeModule,
    activeDefinition,
    builtinModules,
    registeredModules,
    updateModule,
    updateModuleSignatureStampNameDateVisibility,
    handleDeleteModule,
    AddModuleDialog,
}: ModuleTemplateSectionProps) {
    const selectedModuleIsBuiltin = builtinModules.some(
        (module) => module.key === selectedModule,
    );
    const selectedBuiltinValue = selectedModuleIsBuiltin
        ? selectedModule
        : undefined;

    return (
        <div className="overflow-hidden rounded-2xl border bg-card shadow-sm">
            {/* Header */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/35 px-5 py-4 lg:px-6 lg:py-5">
                <div>
                    <p className="text-sm font-semibold">Module Template</p>
                    <p className="text-xs text-muted-foreground">
                        Customize PDF appearance per document type
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    {AddModuleDialog}
                </div>
            </div>

            {/* Mobile/zoom-friendly module selector */}
            <div className="border-b bg-muted/10 px-4 py-3 xl:hidden">
                <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                    Active Module
                </p>
                <div className="flex items-center gap-2">
                    <Select
                        value={selectedModule}
                        onValueChange={(value) => setSelectedModule(value)}
                    >
                        <SelectTrigger className="h-9">
                            <SelectValue placeholder="Select module" />
                        </SelectTrigger>
                        <SelectContent>
                            {builtinModules.map((module) => (
                                <SelectItem key={module.key} value={module.key}>
                                    {module.label}
                                </SelectItem>
                            ))}
                            {registeredModules.map((module) => (
                                <SelectItem key={module.key} value={module.key}>
                                    {module.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-9 shrink-0 px-2.5"
                        onClick={() => onPreview(selectedModule)}
                        title="Preview active module"
                    >
                        <Eye className="h-3.5 w-3.5" />
                    </Button>

                    {!selectedModuleIsBuiltin && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-9 shrink-0 px-2.5 text-destructive hover:text-destructive"
                            onClick={() => handleDeleteModule(selectedModule)}
                            title="Delete active custom module"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                    )}
                </div>
            </div>

            {/* Body: sidebar list + settings panel */}
            <div className="grid min-h-[560px] grid-cols-1 divide-y xl:grid-cols-[minmax(220px,260px)_minmax(0,1fr)] xl:divide-x xl:divide-y-0">
                {/* ── Left: Module List ── */}
                <div className="hidden flex-col xl:flex xl:max-h-[calc(100vh-14rem)]">
                    {/* List header */}
                    <div className="border-b bg-muted/20 px-4 py-3 lg:px-5">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Modules
                        </p>
                    </div>

                    <div className="flex-1 overflow-y-auto p-3.5 lg:p-4 lg:pr-3">
                        {builtinModules.length > 0 && (
                            <>
                                <p className="px-2 pb-2 pt-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    Built-in
                                </p>
                                <div className="w-full max-w-[240px] rounded-lg border bg-background p-2">
                                    <Select
                                        value={selectedBuiltinValue}
                                        onValueChange={(value) =>
                                            setSelectedModule(value)
                                        }
                                    >
                                        <SelectTrigger className="h-8 text-xs">
                                            <SelectValue placeholder="Select built-in module" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {builtinModules.map((module) => (
                                                <SelectItem
                                                    key={module.key}
                                                    value={module.key}
                                                    className="text-xs"
                                                >
                                                    {module.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </>
                        )}

                        {registeredModules?.length > 0 && (
                            <>
                                <div className="mt-4 border-t pt-3.5">
                                    <p className="px-2 pb-2 pt-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Custom
                                    </p>
                                </div>
                                <div className="space-y-1.5">
                                    {registeredModules.map((m) => (
                                        <ModuleRow
                                            key={m.key}
                                            module={m}
                                            isSelected={selectedModule === m.key}
                                            isBuiltin={false}
                                            onSelect={() => setSelectedModule(m.key)}
                                            onPreview={() => onPreview(m.key)}
                                            onDelete={() => handleDeleteModule(m.key)}
                                        />
                                    ))}
                                </div>
                            </>
                        )}
                    </div>
                </div>

                {/* ── Right: Settings Panel ── */}
                <div className="flex min-w-0 flex-col">
                    {/* Settings sub-header */}
                    <div className="flex flex-col gap-3 border-b bg-muted/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between xl:px-6">
                        <div className="flex min-w-0 items-center gap-2">
                            <FileText className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            <span className="truncate text-sm font-semibold">
                                {activeDefinition?.label ?? selectedModule}
                            </span>
                            <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-muted-foreground">
                                {activeDefinition?.document_type ?? '—'}
                            </span>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="flex shrink-0 items-center gap-1.5 self-start text-xs sm:self-auto"
                            onClick={() => onPreview(selectedModule)}
                        >
                            <Eye className="h-3 w-3" />
                            Preview
                        </Button>
                    </div>

                    {/* Settings content */}
                    <div className="flex flex-1 flex-col gap-5 p-4 sm:p-5 lg:p-6 xl:p-7">
                        <section className="space-y-4 rounded-xl border bg-background p-4 shadow-sm lg:p-5">
                            <FormField
                                label="Footer Text"
                                fieldRequirementsProps={{
                                    hint: `Custom footer for ${activeDefinition?.label ?? selectedModule}. Leave blank to use default`,
                                }}
                                htmlFor="module_footer_text"
                            >
                                <Textarea
                                    id="module_footer_text"
                                    value={activeModule?.footer_text || ''}
                                    onChange={(e) =>
                                        updateModule('footer_text', e.target.value)
                                    }
                                    rows={4}
                                    placeholder="e.g. Payment terms, bank details, or custom notes..."
                                    className="min-h-[10rem] resize-none"
                                />
                            </FormField>

                            <p className="text-xs leading-relaxed text-muted-foreground sm:text-sm">
                                Footer text is best used for compact payment terms, short policy notes, or module-specific disclaimers.
                            </p>
                        </section>

                        <section className="space-y-3 rounded-xl border bg-background p-4 shadow-sm lg:p-5">
                            <div className="flex items-center justify-between">
                                <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Document Elements
                                </h4>
                            </div>

                            <ToggleRow
                                title="Show Company Stamp"
                                description="Display stamp from Branding section."
                                checked={activeModule?.show_stamp ?? false}
                                onCheckedChange={(v) => updateModule('show_stamp', v)}
                            />

                            <ToggleRow
                                title="Show Authorised Signature"
                                description="Display signature from Branding section."
                                checked={activeModule?.show_signature ?? false}
                                onCheckedChange={(v) => updateModule('show_signature', v)}
                            />

                            <ToggleRow
                                title="Show Full Name &amp; Date"
                                description="Display name and date below signature and stamp."
                                checked={
                                    (activeModule?.show_signature_stamp_name ?? false) &&
                                    (activeModule?.show_signature_stamp_date ?? false)
                                }
                                onCheckedChange={updateModuleSignatureStampNameDateVisibility}
                            />
                        </section>
                    </div>
                </div>
            </div>
        </div>
    );
}
