import { Accordion } from '@/components/ui/accordion';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { ValueNumberOptionType } from '@/types';
import { useForm } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import { FormProgressHeader } from '../../components/form-progress-header';
import { FormSection } from '../../components/form-section';
import { navigateToSection } from '../../lib/navigation-helper';
import { AvailabilitySection } from './components/AvailabilitySection';
import { DocumentUpload } from './components/DocumentUpload';
import { EmployerFeedbackSection } from './components/EmployerFeedbackSection';
import { EmploymentHistorySection } from './components/EmploymentHistorySection';
import { MedicalSection } from './components/MedicalSection';
import { OtherRemarksSection } from './components/OtherRemarksSection';
import { ProfileSection } from './components/ProfileSection';
import { RestPreferencesSection } from './components/RestPreferencesSection';
import { SkillsAssessmentTables } from './components/SkillsAssessmentTables';
import { StatusFinancialSection } from './components/StatusFinancialSection';
import { useAutoSaveDraft } from './hooks/useAutoSaveDraft';
import { useDraftDialog } from './hooks/useDraftDialog';
import { useFieldValidation } from './hooks/useFieldValidation';
import { useFormSubmission } from './hooks/useFormSubmission';
import { useMaidDocumentUpload } from './hooks/useMaidDocumentUpload';
import { useSectionStatus } from './hooks/useSectionStatus';
import { MaidSchema } from './schema';
import { MaidFormData, SetDataFn } from './types';
import {
    convertFieldsToAttributes,
    mergeInitialData,
} from './utils/formDataProcessor';

interface MaidFormProps {
    mode: 'create' | 'edit' | 'view';
    initialData?: MaidSchema;
    onCancel?: () => void;
    nationalities?: ValueNumberOptionType[];
    religions?: ValueNumberOptionType[];
    educationLevels?: ValueNumberOptionType[];
    suppliers?: ValueNumberOptionType[];
}

export function MaidForm({
    mode,
    initialData,
    onCancel,
    nationalities = [],
    religions = [],
    educationLevels = [],
    suppliers = [],
}: MaidFormProps) {
    const isView = mode === 'view';
    const isEdit = mode === 'edit';
    const isCreate = mode === 'create';

    const defaultData = mergeInitialData(initialData, isEdit);

    const formInstance = useForm<MaidFormData>(defaultData);

    const fileInputRef = useRef<HTMLInputElement>(null);
    const [isLoadingFile, setIsLoadingFile] = useState(false);

    formInstance.transform((data) => {
        const attributes = convertFieldsToAttributes(data);

        let experienceYears = null;
        if (
            data.employment_history &&
            Array.isArray(data.employment_history) &&
            data.employment_history.length > 0
        ) {
            experienceYears = data.employment_history.reduce(
                (total: number, emp) => {
                    if (!emp.period || typeof emp.period !== 'string')
                        return total;

                    const period = emp.period.trim();
                    const yearRangeMatch = period.match(
                        /(\d{4})\s*-\s*(\d{4})/,
                    );
                    if (yearRangeMatch) {
                        return (
                            total +
                            (parseInt(yearRangeMatch[2]) -
                                parseInt(yearRangeMatch[1]))
                        );
                    }

                    const periodLower = period.toLowerCase();
                    const yearMatch = periodLower.match(
                        /(\d+(?:\.\d+)?)\s*(?:year|yr|y)/i,
                    );
                    const monthMatch = periodLower.match(
                        /(\d+(?:\.\d+)?)\s*(?:month|mon|m)/i,
                    );

                    let years = 0;
                    if (yearMatch) years += parseFloat(yearMatch[1]);
                    if (monthMatch) years += parseFloat(monthMatch[1]) / 12;

                    return total + years;
                },
                0,
            );

            if (experienceYears === 0) experienceYears = null;
        }

        return {
            ...data,
            attributes: attributes,
            experience_years: experienceYears,
        };
    });

    const { data, setData, post, processing, errors, setError, clearErrors } =
        formInstance;

    const sectionErrors = errors as Partial<Record<keyof MaidFormData, string>>;

    const [openSections, setOpenSections] = useState<string[]>(['profile']);

    const mergeFormData = useCallback(
        (updater: (previous: MaidFormData) => MaidFormData) => {
            setData(updater);
        },
        [setData],
    );

    const setFieldValue = useCallback<SetDataFn>(
        (key, value) => {
            mergeFormData((previous) => ({
                ...previous,
                [key]: value,
            }));
        },
        [mergeFormData],
    );

    const { validateField } = useFieldValidation({
        data,
        setError,
        clearErrors,
    });

    const {
        file,
        setFile,
        isDragging,
        setIsDragging,
        handleFileChange,
        handleDrop,
        handleScanFile,
    } = useMaidDocumentUpload({ setData: mergeFormData });

    const { sections, getSectionStatus } = useSectionStatus({
        data,
        errors: sectionErrors,
    });

    const { isDraft, loadDraft, clearDraft, hasDraft } = useAutoSaveDraft({
        data,
        enabled: isCreate,
        mode,
    });

    const {
        showDraftDialog,
        setShowDraftDialog,
        handleLoadDraft,
        handleStartFresh,
    } = useDraftDialog({
        isCreate,
        hasDraft,
        loadDraft,
        clearDraft,
        mergeFormData,
    });

    const { handleSubmit } = useFormSubmission({
        data,
        initialData,
        isCreate,
        isEdit,
        setData: setFieldValue,
        post,
        setError,
        clearDraft,
        sections,
    });

    const handleSectionClick = useCallback(
        (sectionId: string) => {
            navigateToSection(sectionId, setOpenSections);
        },
        [setOpenSections],
    );

    // clear form handler
    const handleClear = useCallback(() => {
        clearDraft();

        const resetData = mergeInitialData(initialData, isEdit);

        mergeFormData(() => resetData);

        setFile(null);

        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }

        toast.success('Draft cleared and form reset');
    }, [clearDraft, initialData, isEdit, mergeFormData, setFile]);

    const handleSubmitFile = useCallback(async () => {
        setIsLoadingFile(true);
        try {
            await handleScanFile();
        } finally {
            setIsLoadingFile(false);
        }
    }, [handleScanFile]);

    return (
        <>
            <div className="mx-auto w-full">
                {/* Progress Header */}
                {!isView && (
                    <FormProgressHeader
                        title="Maid"
                        sections={sections}
                        isDraft={isDraft}
                        onSectionClick={handleSectionClick}
                    />
                )}

                {/* Document Upload */}
                {isCreate && (
                    <div className="py-4">
                        <DocumentUpload
                            file={file}
                            isDragging={isDragging}
                            isLoading={isLoadingFile}
                            onFileChange={handleFileChange}
                            onDrop={handleDrop}
                            onSubmit={handleSubmitFile}
                            setIsDragging={setIsDragging}
                            fileInputRef={
                                fileInputRef as React.RefObject<HTMLInputElement>
                            }
                        />
                    </div>
                )}

                {/* Maid Number Display */}
                {data.maid_number && (
                    <div className="mb-2 rounded-lg border border-primary/20 bg-primary/5 p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Maid No.
                                </p>
                                <p className="text-2xl font-bold text-primary">
                                    {data.maid_number}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Form with Accordion */}
                <form onSubmit={handleSubmit} className="space-y-6 py-2">
                    <Accordion
                        type="multiple"
                        value={openSections}
                        onValueChange={setOpenSections}
                        className="mb-8 space-y-4"
                    >
                        {/* Section A: Profile Of FDW */}
                        <FormSection
                            value="profile"
                            title="Section A: Profile Of FDW"
                            description="A1. Personal Information | A2. Medical History/Dietary Restrictions | A3. Others"
                            status={getSectionStatus('profile')}
                            required
                        >
                            <div id="section-profile" className="space-y-6">
                                <ProfileSection
                                    data={data}
                                    setData={setFieldValue}
                                    isView={isView}
                                    errors={sectionErrors}
                                    nationalities={nationalities}
                                    religions={religions}
                                    educationLevels={educationLevels}
                                    validateField={validateField}
                                    clearErrors={clearErrors}
                                />
                                <MedicalSection
                                    data={data}
                                    setData={setFieldValue}
                                    isView={isView}
                                />
                                <RestPreferencesSection
                                    data={data}
                                    setData={setFieldValue}
                                    isView={isView}
                                    errors={sectionErrors}
                                />
                            </div>
                        </FormSection>

                        {/* Section B: Skills Of FDW */}
                        <FormSection
                            value="skills"
                            title="Section B: Skills Of FDW"
                            description="Assessment of FDW's skills and capabilities"
                            status={getSectionStatus('skills')}
                            required={false}
                        >
                            <div id="section-skills">
                                <SkillsAssessmentTables
                                    isView={isView}
                                    data={data}
                                    setData={setFieldValue}
                                />
                            </div>
                        </FormSection>

                        {/* Section C: EMPLOYMENT HISTORY OF THE FDW */}
                        <FormSection
                            value="employment"
                            title="Section C: Employment History Of The FDW"
                            status={getSectionStatus('employment')}
                            required={false}
                        >
                            <div id="section-employment" className="space-y-6">
                                <EmploymentHistorySection
                                    data={data}
                                    setData={setFieldValue}
                                    isView={isView}
                                />
                                <EmployerFeedbackSection
                                    data={data}
                                    setData={setFieldValue}
                                    isView={isView}
                                />
                            </div>
                        </FormSection>

                        {/* Section D: AVAILABILITY OF FDW TO BE INTERVIEWED BY PROSPECTIVE EMPLOYER */}
                        <FormSection
                            value="availability"
                            title="Section D: Availability Of FDW To Be Interviewed"
                            description="Availability to be interviewed by prospective employer"
                            status={getSectionStatus('availability')}
                            required={false}
                        >
                            <div id="section-availability">
                                <AvailabilitySection
                                    data={data}
                                    setData={setFieldValue}
                                    isView={isView}
                                    errors={sectionErrors}
                                />
                            </div>
                        </FormSection>

                        {/* Section E: OTHER REMARKS */}
                        <FormSection
                            value="other-remarks"
                            title="Section E: Other Remarks"
                            description="Additional remarks and notes"
                            status={getSectionStatus('other-remarks')}
                            required={false}
                        >
                            <div id="section-other-remarks">
                                <OtherRemarksSection
                                    data={data}
                                    setData={setFieldValue}
                                    isView={isView}
                                    errors={sectionErrors}
                                />
                            </div>
                        </FormSection>

                        {/* Status & Financial - Moved to bottom */}
                        <FormSection
                            value="status"
                            title="Status & Financial"
                            description="Employment status and financial information"
                            status={getSectionStatus('status')}
                            required
                        >
                            <div id="section-status">
                                <StatusFinancialSection
                                    data={data}
                                    setData={setFieldValue}
                                    isView={isView}
                                    errors={sectionErrors}
                                    suppliers={suppliers}
                                    validateField={validateField}
                                />
                            </div>
                        </FormSection>
                    </Accordion>

                    <div className="mt-4 flex justify-end gap-4 border-t pt-6">
                        {!isView && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        if (isCreate) {
                                            handleClear();
                                        } else {
                                            onCancel?.();
                                        }
                                    }}
                                >
                                    {isCreate ? 'Cancel' : 'Back'}
                                </Button>
                                {isCreate && isDraft && (
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={handleClear}
                                    >
                                        Clear
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    className="min-w-[140px]"
                                    disabled={processing}
                                >
                                    {isEdit ? 'Update' : 'Submit'}
                                </Button>
                            </>
                        )}
                        {isView && onCancel && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onCancel}
                            >
                                Back
                            </Button>
                        )}
                    </div>
                </form>
            </div>

            {/* Draft Dialog */}
            <AlertDialog
                open={showDraftDialog}
                onOpenChange={setShowDraftDialog}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Load Draft?</AlertDialogTitle>
                        <AlertDialogDescription>
                            We found a saved draft from your previous session.
                            Would you like to continue from where you left off?
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={handleStartFresh}>
                            Start Fresh
                        </AlertDialogCancel>
                        <AlertDialogAction onClick={handleLoadDraft}>
                            Load Draft
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
