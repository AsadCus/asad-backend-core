import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { Maximize2, Minimize2, X } from 'lucide-react';
import { useState } from 'react';
import { PdfPreview } from './pdf-preview-panel';
import type { ModuleTemplate, SignatureStampLayoutConfig } from './types';

type PreviewScaleMode = 'fit-width' | 'actual-size';

interface ReportTemplatePreviewModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    moduleLabel: string;
    selectedModule: string;
    brand_color: string;
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    page_margin_preset: 'narrow' | 'normal' | 'wide';
    section_spacing_preset: 'compact' | 'normal' | 'relaxed';
    custom_signature_stamp_layout: SignatureStampLayoutConfig;
    module_templates: Record<string, ModuleTemplate>;
}

export function ReportTemplatePreviewModal({
    open,
    onOpenChange,
    moduleLabel,
    selectedModule,
    brand_color,
    company_name,
    company_address,
    company_phone,
    company_email,
    page_margin_preset,
    section_spacing_preset,
    custom_signature_stamp_layout,
    module_templates,
}: ReportTemplatePreviewModalProps) {
    const [scaleMode, setScaleMode] = useState<PreviewScaleMode>('fit-width');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                showCloseButton={false}
                className="z-50 flex h-full max-h-[96%] flex-col p-0 sm:max-w-[96vw] xl:max-w-[92vw]"
            >
                <DialogHeader className="flex-shrink-0 border-b px-6 py-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <DialogTitle>{moduleLabel} - PDF Preview</DialogTitle>
                            <DialogDescription>
                                Live preview reflects current unsaved settings.
                            </DialogDescription>
                        </div>

                        <div className="flex items-center gap-2 sm:pr-1">
                            <div className="inline-flex items-center rounded-md border bg-muted/30 p-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setScaleMode('fit-width')}
                                    className={cn(
                                        'h-7 gap-1.5 px-2.5 text-xs',
                                        scaleMode === 'fit-width' &&
                                            'bg-background shadow-sm hover:bg-background',
                                    )}
                                >
                                    <Minimize2 className="h-3.5 w-3.5" />
                                    Fit Width
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setScaleMode('actual-size')}
                                    className={cn(
                                        'h-7 gap-1.5 px-2.5 text-xs',
                                        scaleMode === 'actual-size' &&
                                            'bg-background shadow-sm hover:bg-background',
                                    )}
                                >
                                    <Maximize2 className="h-3.5 w-3.5" />
                                    Actual Size
                                </Button>
                            </div>

                            <DialogClose asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    className="h-8 w-8"
                                    aria-label="Close preview"
                                >
                                    <X className="h-4 w-4" />
                                </Button>
                            </DialogClose>
                        </div>
                    </div>
                </DialogHeader>

                <div className="flex-1 overflow-auto bg-muted/30 p-4 sm:p-6 lg:p-8">
                    <PdfPreview
                        scaleMode={scaleMode}
                        selectedModule={selectedModule}
                        brand_color={brand_color}
                        company_name={company_name}
                        company_address={company_address}
                        company_phone={company_phone}
                        company_email={company_email}
                        page_margin_preset={page_margin_preset}
                        section_spacing_preset={section_spacing_preset}
                        signature_stamp_layout="custom"
                        custom_signature_stamp_layout={
                            custom_signature_stamp_layout
                        }
                        module_templates={module_templates}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}
