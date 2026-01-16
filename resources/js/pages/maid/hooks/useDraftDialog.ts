/**
 * Custom hook for draft dialog management
 * Handles draft loading confirmation dialog
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { MaidFormData } from '../types';

interface UseDraftDialogProps {
    isCreate: boolean;
    hasDraft: () => boolean;
    loadDraft: () => MaidFormData | null;
    clearDraft: () => void;
    mergeFormData: (updater: (previous: MaidFormData) => MaidFormData) => void;
}

interface UseDraftDialogReturn {
    showDraftDialog: boolean;
    setShowDraftDialog: (show: boolean) => void;
    handleLoadDraft: () => void;
    handleStartFresh: () => void;
}

export function useDraftDialog({
    isCreate,
    hasDraft,
    loadDraft,
    clearDraft,
    mergeFormData,
}: UseDraftDialogProps): UseDraftDialogReturn {
    const [showDraftDialog, setShowDraftDialog] = useState(false);
    const hasCheckedDraft = useRef(false);

    // Check for draft on mount
    useEffect(() => {
        if (isCreate && !hasCheckedDraft.current && hasDraft()) {
            setShowDraftDialog(true);
            hasCheckedDraft.current = true;
        }
    }, [isCreate, hasDraft]);

    // Handle draft loading
    const handleLoadDraft = useCallback(() => {
        const draft = loadDraft();
        if (draft) {
            mergeFormData((prev) => ({
                ...prev,
                ...draft,
                _method: prev._method,
            }));
            toast.success('Draft loaded successfully');
        }
        setShowDraftDialog(false);
    }, [loadDraft, mergeFormData]);

    // Handle starting fresh
    const handleStartFresh = useCallback(() => {
        clearDraft();
        setShowDraftDialog(false);
        toast.info('Starting with a fresh form');
    }, [clearDraft]);

    return {
        showDraftDialog,
        setShowDraftDialog,
        handleLoadDraft,
        handleStartFresh,
    };
}
