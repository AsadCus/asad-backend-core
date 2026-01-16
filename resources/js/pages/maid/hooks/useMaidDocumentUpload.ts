import { uploadDocument } from '@/actions/App/Http/Controllers/MaidController';
import { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import {
    ChangeEvent,
    DragEvent,
    useCallback,
    useEffect,
    useState,
} from 'react';
import { toast } from 'sonner';
import { MaidFormData } from '../types';
import {
    formatParsedFileMeta,
    mergeScanResultIntoFormData,
    RawScanResult,
} from '../utils/mergeScanResult';

interface UseMaidDocumentUploadOptions {
    setData: (updater: (previous: MaidFormData) => MaidFormData) => void;
}

export function useMaidDocumentUpload({
    setData,
}: UseMaidDocumentUploadOptions) {
    const [file, setFile] = useState<File | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const { flash } = usePage<SharedData>().props;

    const handleFileChange = useCallback(
        (event: ChangeEvent<HTMLInputElement>) => {
            const nextFile = event.target.files?.[0] ?? null;
            setFile(nextFile);
            if (nextFile) {
                toast.info(`File "${nextFile.name}" uploaded`);
            }
        },
        [],
    );

    const handleDrop = useCallback((event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragging(false);
        const droppedFile = event.dataTransfer.files?.[0] ?? null;
        setFile(droppedFile);
        if (droppedFile) {
            toast.info(`File "${droppedFile.name}" uploaded`);
        }
    }, []);

    const handleScanFile = useCallback(() => {
        return new Promise<void>((resolve, reject) => {
            if (!file) {
                toast.error('Please select a file first');
                reject(new Error('No file selected'));
                return;
            }

            const formData = new FormData();
            formData.append('document', file);

            toast.loading('Parsing document...', { id: 'parsing' });

            router.post(uploadDocument().url, formData, {
                forceFormData: true,
                preserveScroll: true,
                onError: () => {
                    toast.dismiss('parsing');
                    toast.error('Failed to parse document');
                    reject(new Error('Failed to parse document'));
                },
                onSuccess: () => {
                    toast.dismiss('parsing');
                    resolve();
                },
            });
        });
    }, [file]);

    useEffect(() => {
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.error]);

    useEffect(() => {
        const result = flash?.result as RawScanResult | undefined;

        if (!result?.success || !result.data) {
            return;
        }

        setData((previous) => mergeScanResultIntoFormData(previous, result));

        const meta = formatParsedFileMeta(result.metadata);
        const uploadedPhotos = result.photos?.uploaded?.length ?? 0;
        const foundPhotos = result.photos?.total_found ?? 0;

        if (uploadedPhotos > 0) {
            toast.success('Photo detected and uploaded automatically!', {
                description: `Found ${foundPhotos} photo(s) in document`,
            });
        }

        if (meta) {
            toast.info(`Parsed ${meta}`);
        }
    }, [flash?.result, setData]);

    return {
        file,
        setFile,
        isDragging,
        setIsDragging,
        handleFileChange,
        handleDrop,
        handleScanFile,
    } as const;
}
