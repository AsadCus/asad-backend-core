import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { Loader2, Upload } from 'lucide-react';
import { ChangeEvent, DragEvent, RefObject, useEffect } from 'react';

type DocumentUploadProps = {
    file: File | null;
    isDragging: boolean;
    isLoading?: boolean;
    onFileChange: (event: ChangeEvent<HTMLInputElement>) => void;
    onDrop: (event: DragEvent<HTMLDivElement>) => void;
    onSubmit: () => void | Promise<void>;
    setIsDragging: (value: boolean) => void;
    fileInputRef?: RefObject<HTMLInputElement>;
};

export function DocumentUpload({
    file,
    isDragging,
    isLoading = false,
    onFileChange,
    onDrop,
    onSubmit,
    setIsDragging,
    fileInputRef,
}: DocumentUploadProps) {
    const handleDragOver = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragging(true);
    };

    const handleDragLeave = () => setIsDragging(false);

    useEffect(() => {
        if (file) {
            const timer = setTimeout(onSubmit, 500);
            return () => clearTimeout(timer);
        }
    }, [file, onSubmit]);

    return (
        <div
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={onDrop}
            className={cn(
                'flex flex-row items-center justify-between rounded-xl border-2 border-dashed px-10 py-6 text-center transition-colors duration-200',
                isDragging
                    ? 'border-primary bg-primary/10'
                    : 'border-gray-300 hover:border-primary/60',
                isLoading && 'pointer-events-none opacity-60',
            )}
        >
            <div className="flex items-center">
                {isLoading ? (
                    <Loader2 className="mr-2 h-10 w-10 animate-spin text-primary" />
                ) : (
                    <Upload className="mr-2 h-10 w-10 text-gray-600 dark:text-white" />
                )}
                <div className="flex flex-col text-left">
                    <p className="text-base text-gray-600 dark:text-white">
                        {isLoading
                            ? 'Processing file...'
                            : 'Drag & drop file here'}
                    </p>
                    <span className="text-sm text-gray-400 dark:text-gray-200">
                        {isLoading
                            ? 'Please wait while we process your document'
                            : 'file format supported: docs & pdf'}
                    </span>
                </div>
            </div>

            <div className="flex items-center space-x-3">
                <div className="flex">
                    <input
                        ref={fileInputRef}
                        type="file"
                        accept=".pdf,.docx"
                        className="hidden"
                        id="maid-upload"
                        onChange={onFileChange}
                        aria-label="Upload maid document"
                        title="Upload maid document"
                        disabled={isLoading}
                    />
                    <Label
                        htmlFor="maid-upload"
                        className={cn(
                            'cursor-pointer rounded-md bg-primary px-4 py-2 text-base text-white hover:bg-primary/90 dark:text-black',
                            isLoading && 'cursor-not-allowed opacity-50',
                        )}
                    >
                        {isLoading ? 'Processing...' : 'Browse File'}
                    </Label>
                </div>

                {file && !isLoading && (
                    <p className="text-sm text-gray-500">
                        Uploaded:{' '}
                        <span className="font-medium">{file.name}</span>
                    </p>
                )}
            </div>
        </div>
    );
}
