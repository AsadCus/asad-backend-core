import { useEffect, useCallback, useRef, useState } from 'react';
import { MaidFormData } from '../types';
import { debounce } from 'lodash';

const DRAFT_STORAGE_KEY = 'maid_form_draft';
const DRAFT_TIMESTAMP_KEY = 'maid_form_draft_timestamp';
const AUTO_SAVE_DELAY = 2000; // 2 seconds

interface UseAutoSaveDraftProps {
    data: MaidFormData;
    enabled?: boolean;
    mode: 'create' | 'edit' | 'view';
}

// Helper function to check if form has meaningful data
function hasFormData(data: MaidFormData): boolean {
    // Helper to check if string value is meaningful (not empty, not just whitespace)
    const hasValue = (val: unknown): boolean => {
        if (typeof val === 'string') {
            return val.trim().length > 0;
        }
        if (typeof val === 'number') {
            return true;
        }
        return false;
    };

    // Check if any required field has been filled with actual data
    const hasRequiredData = !!(
        hasValue(data.name) ||
        hasValue(data.date_of_birth) ||
        hasValue(data.place_of_birth) ||
        hasValue(data.height) ||
        hasValue(data.weight) ||
        hasValue(data.country_id) ||
        hasValue(data.address) ||
        hasValue(data.repatriation_port_airport) ||
        hasValue(data.contact_number_home_country) ||
        hasValue(data.religion_id) ||
        hasValue(data.education_level_id) ||
        hasValue(data.marital_status) ||
        hasValue(data.status) ||
        hasValue(data.supplier_id)
    );

    // Check if any optional text field has meaningful data
    const hasOptionalTextData = !!(
        hasValue(data.age) ||
        hasValue(data.number_of_siblings) ||
        hasValue(data.number_of_children) ||
        hasValue(data.children_ages) ||
        hasValue(data.rest_days_per_month) ||
        hasValue(data.other_remarks) ||
        hasValue(data.remaining_loan) ||
        hasValue(data.cost_of_maid) ||
        hasValue(data.employment_feedback) ||
        hasValue(data.availability_remarks) ||
        hasValue(data.eval_overseas_name) ||
        hasValue(data.eval_overseas_cert) ||
        // A2. Medical History fields
        hasValue(data.allergies) ||
        hasValue(data.physical_disabilities) ||
        hasValue(data.dietary_restrictions)
    );

    // Check if employer_feedback array has data
    const hasEmployerFeedbackData = !!(
        data.employer_feedback && data.employer_feedback.length > 0 &&
        data.employer_feedback.some(feedback => 
            hasValue(feedback.employer) || 
            hasValue(feedback.feedback)
        )
    );

    // Check if any array has actual items with data
    const hasArrayData = !!(
        (data.attributes && data.attributes.length > 0 && 
            data.attributes.some(attr => 
                hasValue(attr.attribute_category) || 
                hasValue(attr.attribute_name)
            )) ||
        (data.skills_assessment_singapore && data.skills_assessment_singapore.length > 0 &&
            data.skills_assessment_singapore.some(skill => 
                hasValue(skill.area) || 
                hasValue(skill.willingness) || 
                hasValue(skill.experience) ||
                hasValue(skill.assessment) ||
                hasValue(skill.observation)
            )) ||
        (data.skills_assessment_overseas && data.skills_assessment_overseas.length > 0 &&
            data.skills_assessment_overseas.some(skill => 
                hasValue(skill.area) || 
                hasValue(skill.willingness) || 
                hasValue(skill.experience) ||
                hasValue(skill.assessment) ||
                hasValue(skill.observation)
            )) ||
        (data.employment_history && data.employment_history.length > 0 &&
            data.employment_history.some(emp => 
                hasValue(emp.country) || 
                hasValue(emp.employer) || 
                hasValue(emp.period) ||
                hasValue(emp.duties) ||
                hasValue(emp.remarks)
            ))
    );

    // Check if any boolean flags are set (excluding default false values)
    // Only consider it meaningful if explicitly true
    const hasAvailabilityData = !!(
        data.interview_not_available ||
        data.interview_by_phone ||
        data.interview_by_video ||
        data.interview_in_person
    );

    // Check if any medical checkboxes are set (A2 fields)
    const hasMedicalData = !!(
        data.illness_mental_illness ||
        data.illness_epilepsy ||
        data.illness_asthma ||
        data.illness_diabetes ||
        data.illness_hypertension ||
        data.illness_tuberculosis ||
        data.illness_heart_disease ||
        data.illness_malaria ||
        data.illness_operations ||
        data.food_handling_no_beef ||
        data.food_handling_no_pork
    );

    const hasEvaluationData = !!(
        data.singapore_experience ||
        data.eval_declaration_no_eval ||
        (data.eval_sg_interview && (
            data.eval_sg_phone || 
            data.eval_sg_video || 
            data.eval_sg_in_person
        )) ||
        (data.eval_overseas_interview && (
            hasValue(data.eval_overseas_name) ||
            hasValue(data.eval_overseas_cert) ||
            data.eval_overseas_phone ||
            data.eval_overseas_video ||
            data.eval_overseas_in_person
        ))
    );

    // Check if photo has been uploaded
    const hasPhoto = !!(
        (data.photo_url && typeof data.photo_url !== 'string') || // File object
        (typeof data.photo_url === 'string' && hasValue(data.photo_url))
    );

    return hasRequiredData || hasOptionalTextData || hasArrayData || hasEmployerFeedbackData || hasAvailabilityData || hasEvaluationData || hasMedicalData || hasPhoto;
}

export function useAutoSaveDraft({ data, enabled = true, mode }: UseAutoSaveDraftProps) {
    const [lastSaved, setLastSaved] = useState<Date | null>(null);
    const [isDraft, setIsDraft] = useState(false);
    const initialLoadRef = useRef(false);
    const hasLoadedDraft = useRef(false);
    const isViewMode = mode === 'view';
    const isCreateMode = mode === 'create';

    // Save draft to localStorage
    const saveDraft = useCallback(async (formData: MaidFormData) => {
        if (!enabled || isViewMode || !isCreateMode) return;

        // Only save if form has meaningful data
        if (!hasFormData(formData)) {
            return;
        }

        try {
            // Create a copy to avoid mutating original data
            const draftData = { ...formData };
            
            // Convert File object to base64 for storage
            if (draftData.photo_url && draftData.photo_url instanceof File) {
                const reader = new FileReader();
                const base64Promise = new Promise<string>((resolve) => {
                    reader.onloadend = () => {
                        resolve(reader.result as string);
                    };
                    reader.readAsDataURL(draftData.photo_url as File);
                });
                
                const base64Data = await base64Promise;
                // Store as special format so we can identify it when loading
                const photoData: Record<string, string> = { _base64: base64Data, _filename: (formData.photo_url as File).name };
                draftData.photo_url = photoData as unknown as File;
            }

            localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(draftData));
            localStorage.setItem(DRAFT_TIMESTAMP_KEY, new Date().toISOString());
            setLastSaved(new Date());
            setIsDraft(true);
        } catch (error) {
            console.error('Failed to save draft:', error);
        }
    }, [enabled, isViewMode, isCreateMode]);

    // Debounced save function
    const debouncedSave = useRef(
        debounce((formData: MaidFormData) => {
            saveDraft(formData);
        }, AUTO_SAVE_DELAY)
    ).current;

    // Helper to convert base64 back to File
    const base64ToFile = (base64: string, filename: string): File => {
        const arr = base64.split(',');
        const mime = arr[0].match(/:(.*?);/)?.[1] || 'image/jpeg';
        const bstr = atob(arr[1]);
        let n = bstr.length;
        const u8arr = new Uint8Array(n);
        while (n--) {
            u8arr[n] = bstr.charCodeAt(n);
        }
        return new File([u8arr], filename, { type: mime });
    };

    // Load draft from localStorage
    const loadDraft = useCallback((): MaidFormData | null => {
        if (!enabled || isViewMode || !isCreateMode) return null;

        try {
            const savedDraft = localStorage.getItem(DRAFT_STORAGE_KEY);
            const savedTimestamp = localStorage.getItem(DRAFT_TIMESTAMP_KEY);

            if (savedDraft && savedTimestamp) {
                const draft = JSON.parse(savedDraft) as MaidFormData;
                const timestamp = new Date(savedTimestamp);
                
                // Convert base64 photo back to File object
                if (draft.photo_url && typeof draft.photo_url === 'object' && '_base64' in draft.photo_url) {
                    const photoData = draft.photo_url as unknown as { _base64: string; _filename: string };
                    draft.photo_url = base64ToFile(photoData._base64, photoData._filename);
                }
                
                // Mark that we've loaded a draft
                hasLoadedDraft.current = true;
                
                setLastSaved(timestamp);
                setIsDraft(true);
                return draft;
            }
        } catch (error) {
            console.error('Failed to load draft:', error);
        }

        return null;
    }, [enabled, isViewMode, isCreateMode]);

    // Clear draft from localStorage
    const clearDraft = useCallback(() => {
        try {
            localStorage.removeItem(DRAFT_STORAGE_KEY);
            localStorage.removeItem(DRAFT_TIMESTAMP_KEY);
            setLastSaved(null);
            setIsDraft(false);
        } catch (error) {
            console.error('Failed to clear draft:', error);
        }
    }, []);

    // Check if draft exists
    const hasDraft = useCallback((): boolean => {
        if (!isCreateMode) return false;
        
        try {
            const savedDraft = localStorage.getItem(DRAFT_STORAGE_KEY);
            if (!savedDraft) return false;
            
            // Validate that draft has meaningful data
            const draft = JSON.parse(savedDraft) as MaidFormData;
            return hasFormData(draft);
        } catch {
            return false;
        }
    }, [isCreateMode]);

    // Auto-save when data changes
    useEffect(() => {
        // Only auto-save if:
        // 1. Initial load is done
        // 2. Enabled and in create mode
        // 3. Form has meaningful data
        // 4. Not immediately after mounting (give user time to interact)
        if (initialLoadRef.current && enabled && isCreateMode && !isViewMode) {
            // Don't save on first render or if we just loaded a draft
            if (hasFormData(data)) {
                debouncedSave(data);
            }
        }
    }, [data, debouncedSave, enabled, isViewMode, isCreateMode]);

    // Mark initial load as done after a short delay
    useEffect(() => {
        const timer = setTimeout(() => {
            initialLoadRef.current = true;
        }, 500); // Wait 500ms before enabling auto-save

        return () => clearTimeout(timer);
    }, []);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            debouncedSave.cancel();
        };
    }, [debouncedSave]);

    return {
        saveDraft,
        loadDraft,
        clearDraft,
        hasDraft,
        lastSaved,
        isDraft,
    };
}
