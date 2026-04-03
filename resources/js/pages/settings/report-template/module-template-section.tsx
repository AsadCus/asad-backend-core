import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { Eye, Trash2 } from 'lucide-react';
import type { ModuleTemplate, RegisteredModule } from './types';

interface ModuleTemplateSectionProps {
    selectedModule: string;
    setSelectedModule: (key: string) => void;
    onPreview: (key: string) => void;
    activeModule: ModuleTemplate;
    activeDefinition: RegisteredModule | undefined;

    isBuiltin: boolean;
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
                'group flex items-center gap-2 rounded-md border px-3 py-2.5 transition-colors cursor-pointer',
                isSelected
                    ? 'border-primary/40 bg-primary/5 shadow-sm'
                    : 'hover:bg-muted/50',
            )}
            onClick={onSelect}
        >
            {/* Active indicator */}
            <span
                className={cn(
                    'h-2 w-2 shrink-0 rounded-full transition-colors',
                    isSelected ? 'bg-primary' : 'bg-muted-foreground/30',
                )}
            />

            {/* Label */}
            <span
                className={cn(
                    'flex-1 truncate text-sm font-medium',
                    isSelected ? 'text-primary' : 'text-foreground',
                )}
            >
                {module.label}
            </span>

            {/* Document type badge */}
            <span className="hidden shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground sm:inline">
                {module.document_type}
            </span>

            {/* Action buttons */}
            <div className="flex shrink-0 items-center gap-1">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 opacity-60 hover:opacity-100 hover:text-blue-600"
                    title={`Preview ${module.label}`}
                    onClick={(e) => {
                        e.stopPropagation();
                        onPreview();
                    }}
                >
                    <Eye className="h-3.5 w-3.5" />
                </Button>

                {!isBuiltin && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7 opacity-60 hover:opacity-100 hover:text-destructive"
                        title={`Delete ${module.label}`}
                        onClick={(e) => {
                            e.stopPropagation();
                            onDelete();
                        }}
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                )}
            </div>
        </div>
    );
}

export function ModuleTemplateSection({
    selectedModule,
    setSelectedModule,
    onPreview,
    activeModule,
    activeDefinition,
    isBuiltin,
    builtinModules,
    registeredModules,
    updateModule,
    updateModuleSignatureStampNameDateVisibility,
    handleDeleteModule,
    AddModuleDialog,
}: ModuleTemplateSectionProps) {
    return (
        <div className="overflow-hidden rounded-lg border bg-card shadow-sm">
            {/* Header */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/40 px-4 py-3">
                <div>
                    <p className="text-base font-semibold">Module Template</p>
                    <p className="text-sm text-muted-foreground">
                        Customize PDF appearance per document type
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    {AddModuleDialog}
                </div>
            </div>

            <div className="grid grid-cols-1 divide-y sm:grid-cols-[280px_1fr] sm:divide-x sm:divide-y-0">
                {/* Left: Module List */}
                <div className="flex flex-col">
                    <div className="border-b bg-muted/20 px-3 py-2">
                        <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Modules
                        </p>
                    </div>
                    <div className="max-h-[420px] overflow-y-auto p-2 space-y-1">
                        {builtinModules.length > 0 && (
                            <>
                                <p className="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    Built-in
                                </p>
                                {builtinModules.map((m) => (
                                    <ModuleRow
                                        key={m.key}
                                        module={m}
                                        isSelected={selectedModule === m.key}
                                        isBuiltin={true}
                                        onSelect={() => setSelectedModule(m.key)}
                                        onPreview={() => onPreview(m.key)}
                                        onDelete={() => handleDeleteModule(m.key)}
                                    />
                                ))}
                            </>
                        )}

                        {registeredModules?.length > 0 && (
                            <>
                                <div className="border-t pt-2 mt-1">
                                    <p className="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Custom
                                    </p>
                                </div>
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
                            </>
                        )}
                    </div>
                </div>

                {/* Right: Settings Panel */}
                <div className="p-5">
                    {/* Selected module heading */}
                    <div className="mb-4 flex items-center gap-2">
                        <span className="text-sm font-semibold">
                            {activeDefinition?.label ?? selectedModule}
                        </span>
                        <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                            {activeDefinition?.document_type ?? '—'}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="ml-auto flex items-center gap-1.5 h-7 text-xs"
                            onClick={() => onPreview(selectedModule)}
                        >
                            <Eye className="h-3.5 w-3.5" />
                            Preview
                        </Button>
                    </div>

                    <div className="space-y-5">
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
                            />
                        </FormField>

                        <Separator />

                        <div className="space-y-3">
                            <h4 className="text-base font-medium">
                                Document Elements
                            </h4>
                            <>
                                <div className="flex items-center justify-between rounded-lg border p-3 shadow-sm">
                                    <div>
                                        <p className="text-base font-medium">
                                            Show Company Stamp
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Display stamp from Branding section.
                                        </p>
                                    </div>
                                    <Switch
                                        checked={activeModule?.show_stamp ?? false}
                                        onCheckedChange={(v) =>
                                            updateModule('show_stamp', v)
                                        }
                                    />
                                </div>
                                <div className="flex items-center justify-between rounded-lg border p-3 shadow-sm">
                                    <div>
                                        <p className="text-base font-medium">
                                            Show Authorised Signature
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Display signature from Branding section.
                                        </p>
                                    </div>
                                    <Switch
                                        checked={activeModule?.show_signature ?? false}
                                        onCheckedChange={(v) =>
                                            updateModule('show_signature', v)
                                        }
                                    />
                                </div>
                                <div className="flex items-center justify-between rounded-lg border p-3 shadow-sm">
                                    <div>
                                        <p className="text-base font-medium">
                                            Show Full Name and Date
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Display full name and date below signature and stamp.
                                        </p>
                                    </div>
                                    <Switch
                                        checked={
                                            (activeModule?.show_signature_stamp_name ?? false) &&
                                            (activeModule?.show_signature_stamp_date ?? false)
                                        }
                                        onCheckedChange={
                                            updateModuleSignatureStampNameDateVisibility
                                        }
                                    />
                                </div>
                            </>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
