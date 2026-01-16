import { Button } from '@/components/ui/button';
import { triggerDownload } from '@/lib/utils';
import { Download, Eye, Loader2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import { MaidBiodataPreview } from './maid-biodata-preview';

interface DocumentGeneratorProps {
    maidId: number;
    maidName?: string;
}

export function DocumentGenerator({
    maidId,
    maidName,
}: DocumentGeneratorProps) {
    const [isGenerating, setIsGenerating] = useState(false);
    const [isPreviewOpen, setIsPreviewOpen] = useState(false);

    const handleGeneratePdf = useCallback(() => {
        setIsGenerating(true);
        triggerDownload(`/maid/${maidId}/generate-pdf`);
        setTimeout(() => setIsGenerating(false), 500);
        toast.success('PDF Generated', {
            description: 'Your PDF biodata has been generated successfully.',
        });
    }, [maidId]);

    const handlePreview = useCallback(() => {
        setIsPreviewOpen(true);
    }, []);

    return (
        <>
            <div className="flex gap-2">
                <Button
                    onClick={handlePreview}
                    variant="outline"
                    size="sm"
                    className="gap-2"
                >
                    <Eye className="h-4 w-4" />
                    Preview Biodata
                </Button>

                <Button
                    onClick={handleGeneratePdf}
                    disabled={isGenerating}
                    size="sm"
                    className="gap-2"
                >
                    {isGenerating ? (
                        <>
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Generating...
                        </>
                    ) : (
                        <>
                            <Download className="h-4 w-4" />
                            Generate PDF
                        </>
                    )}
                </Button>
            </div>

            <MaidBiodataPreview
                maidId={maidId}
                maidName={maidName}
                isOpen={isPreviewOpen}
                onOpenChange={setIsPreviewOpen}
            />
        </>
    );
}
