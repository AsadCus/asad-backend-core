import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Trash2 } from 'lucide-react';
import type { ModuleTemplate, PdfPreviewProps, RegisteredModule } from './types';

interface ModuleTemplateSectionProps {
    selectedModule: string;
    setSelectedModule: (key: string) => void;
    activeModule: ModuleTemplate;
    activeDefinition: RegisteredModule | undefined;
    isBuiltin: boolean;
    builtinModules: RegisteredModule[];
    registeredModules: RegisteredModule[];
    updateModule: (field: keyof ModuleTemplate, value: string | boolean) => void;
    handleDeleteModule: (key: string) => void;
    /** Rendered element — caller controls key, no wrapper needed */
    AddModuleDialog: React.ReactNode;
    PdfPreview: React.ComponentType<PdfPreviewProps>;
    previewProps: PdfPreviewProps;
}

export function ModuleTemplateSection({
    selectedModule,
    setSelectedModule,
    activeModule,
    activeDefinition,
    isBuiltin,
    builtinModules,
    registeredModules,
    updateModule,
    handleDeleteModule,
    AddModuleDialog,
    PdfPreview,
    previewProps,
}: ModuleTemplateSectionProps) {
    return (
        <div className="overflow-hidden rounded-lg border bg-card shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b bg-muted/40 px-4 py-3">
                <div>
                    <p className="text-base font-semibold">Module Template</p>
                    <p className="text-sm text-muted-foreground">
                        Customize PDF appearance per document type
                    </p>
                </div>
                <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                    <Select value={selectedModule} onValueChange={setSelectedModule}>
                        <SelectTrigger className="w-full sm:w-44">
                            <SelectValue placeholder="Select module" />
                        </SelectTrigger>
                        <SelectContent>
                            {builtinModules.length > 0 && (
                                <div className="px-2 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    Built-in
                                </div>
                            )}
                            {builtinModules.map((m) => (
                                <SelectItem key={m.key} value={m.key}>
                                    {m.label}
                                </SelectItem>
                            ))}
                            {registeredModules?.length > 0 && (
                                <div className="mt-1 border-t px-2 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    Custom
                                </div>
                            )}
                            {registeredModules.map((m) => (
                                <SelectItem key={m.key} value={m.key}>
                                    {m.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {!isBuiltin && (
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => handleDeleteModule(selectedModule)}
                            className="w-full sm:w-auto flex items-center gap-1.5"
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                            Delete
                        </Button>
                    )}

                    {AddModuleDialog}
                </div>
            </div>

            <div className="p-5">
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
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
                        </div>
                    </div>

                    <div className="space-y-2">
                        <FormField
                            label="Live Preview"
                            fieldRequirementsProps={{
                                hint: 'Updates as you change settings above',
                            }}
                        >
                            <PdfPreview {...previewProps} />
                        </FormField>
                    </div>
                </div>
            </div>
        </div>
    );
}
