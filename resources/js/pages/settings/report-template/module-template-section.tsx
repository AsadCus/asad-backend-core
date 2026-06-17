import { FieldRequirements } from '@/components/field-requirements';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
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
    updateModule: (
        field: keyof ModuleTemplate,
        value: string | boolean,
    ) => void;
    updateModuleSignatureStampNameDateVisibility: (value: boolean) => void;
    handleDeleteModule: (key: string) => void;
    /** Rendered element — caller controls key, no wrapper needed */
    AddModuleDialog: React.ReactNode;
}

/** Reusable toggle row for document element switches */
function ToggleRow({
    title,
    description,
    hint,
    checked,
    onCheckedChange,
}: {
    title: string;
    description: string;
    hint: string;
    checked: boolean;
    onCheckedChange: (v: boolean) => void;
}) {
    return (
        <div className="flex items-start justify-between gap-4 rounded-lg border p-3.5 lg:p-4">
            <div className="min-w-0">
                <p className="inline-flex items-center gap-1.5 text-base leading-tight font-medium">
                    {title}
                    <FieldRequirements hint={hint} />
                </p>
                <p className="mt-1 text-base text-muted-foreground">
                    {description}
                </p>
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

    return (
        <div className="overflow-hidden rounded-2xl border bg-card shadow-sm">
            {/* Header */}
            <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/35 px-5 py-4 lg:px-6 lg:py-5">
                <div>
                    <p className="text-base font-medium">Module Template</p>
                    <p className="text-base text-muted-foreground">
                        Customize PDF appearance per document type
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                        Module
                        <FieldRequirements hint="Select which module settings you want to edit." />
                    </span>
                    <Select
                        value={selectedModule}
                        onValueChange={(value) => setSelectedModule(value)}
                    >
                        <SelectTrigger className="h-9 w-[220px]">
                            <SelectValue placeholder="Select module" />
                        </SelectTrigger>
                        <SelectContent>
                            {builtinModules.length > 0 && (
                                <SelectGroup>
                                    <SelectLabel>Built-in</SelectLabel>
                                    {builtinModules.map((module) => (
                                        <SelectItem
                                            key={module.key}
                                            value={module.key}
                                        >
                                            {module.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            )}
                            {registeredModules.length > 0 && (
                                <SelectGroup>
                                    <SelectLabel>Custom</SelectLabel>
                                    {registeredModules.map((module) => (
                                        <SelectItem
                                            key={module.key}
                                            value={module.key}
                                        >
                                            {module.label}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            )}
                        </SelectContent>
                    </Select>
                    {AddModuleDialog}
                </div>
            </div>

            {/* Body: Settings Panel */}
            <div className="flex min-h-[460px] min-w-0 flex-col">
                {/* Settings sub-header */}
                <div className="flex flex-col gap-3 border-b bg-muted/10 px-5 py-4 sm:flex-row sm:items-center sm:justify-between xl:px-6">
                    <div className="flex min-w-0 items-center gap-2">
                        <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                        <span className="truncate text-base font-medium">
                            {activeDefinition?.label ?? selectedModule}
                        </span>
                        <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            {activeDefinition?.document_type ?? '—'}
                        </span>
                    </div>
                    <div className="flex items-center gap-2 self-start sm:self-auto">
                        {!selectedModuleIsBuiltin && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="flex shrink-0 items-center gap-1.5 text-sm text-destructive hover:bg-destructive/10 hover:text-destructive"
                                onClick={() =>
                                    handleDeleteModule(selectedModule)
                                }
                            >
                                <Trash2 className="h-4 w-4" />
                                Delete
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="flex shrink-0 items-center gap-1.5 text-sm"
                            onClick={() => onPreview(selectedModule)}
                        >
                            <Eye className="h-4 w-4" />
                            Preview
                        </Button>
                    </div>
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

                        <p className="text-base leading-relaxed text-muted-foreground">
                            Footer text is best used for compact payment terms,
                            short policy notes, or module-specific disclaimers.
                        </p>
                    </section>

                    <section className="space-y-3 rounded-xl border bg-background p-4 shadow-sm lg:p-5">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Document Elements
                            </h4>
                        </div>

                        <ToggleRow
                            title="Show Company Stamp"
                            description="Display stamp from Branding section."
                            hint="Enable to render company stamp in this module report output."
                            checked={activeModule?.show_stamp ?? false}
                            onCheckedChange={(v) =>
                                updateModule('show_stamp', v)
                            }
                        />

                        <ToggleRow
                            title="Show Authorised Signature"
                            description="Display signature from Branding section."
                            hint="Enable to render authorised signature in this module report output."
                            checked={activeModule?.show_signature ?? false}
                            onCheckedChange={(v) =>
                                updateModule('show_signature', v)
                            }
                        />

                        <ToggleRow
                            title="Show QR Image"
                            description="Display QR image from Branding section."
                            hint="Enable to render QR image in this module report output."
                            checked={activeModule?.show_qr ?? true}
                            onCheckedChange={(v) => updateModule('show_qr', v)}
                        />

                        <ToggleRow
                            title="Show Full Name &amp; Date"
                            description="Display name and date below signature and stamp."
                            hint="Shows configured signer full name and date together below signature and stamp."
                            checked={
                                (activeModule?.show_signature_stamp_name ??
                                    false) &&
                                (activeModule?.show_signature_stamp_date ??
                                    false)
                            }
                            onCheckedChange={
                                updateModuleSignatureStampNameDateVisibility
                            }
                        />
                    </section>
                </div>
            </div>
        </div>
    );
}
