import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { FieldRequirements } from '../../../components/field-requirements';
import { ProperInput } from '../../../components/proper-input';
import { illnessOptions } from '../schema';
import { MaidFormData, SetDataFn } from '../types';

type MedicalSectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
};

export function MedicalSection({ data, setData, isView }: MedicalSectionProps) {
    // Handle select for illness
    const handleIllnessSelect = (illness: string, value: string) => {
        const valueKey =
            `illness_${illness.toLowerCase().replace(/\s+/g, '_')}_value` as keyof MaidFormData;
        setData(valueKey, value);
    };

    return (
        <section className="space-y-4">
            <h3 className="border-b pb-2 text-lg font-semibold">
                A2. Medical History/Dietary Restrictions
            </h3>

            <div className="space-y-6">
                {/* 16. Allergies */}
                <div className="grid w-full items-center gap-3">
                    <Label
                        htmlFor="allergies"
                        className="flex items-center gap-1"
                    >
                        16. Allergies (if any)
                        <FieldRequirements
                            hint="List any known allergies"
                            example="Peanuts, Shellfish, Dust"
                        />
                    </Label>
                    <ProperInput
                        id="allergies"
                        value={data.allergies ?? ''}
                        onCommit={(value) => setData('allergies', value)}
                        placeholder="Enter allergies if any"
                        disabled={isView}
                        textarea={true}
                    />
                    <p className="text-right text-xs text-muted-foreground">
                        {(data.allergies ?? '').length}/200 characters
                    </p>
                </div>

                {/* 17. Past and existing illnesses */}
                <div className="grid w-full items-center gap-3">
                    <Label className="flex items-center gap-1">
                        17. Past and existing illnesses (including chronic
                        ailments and illnesses requiring medication)
                        <FieldRequirements hint="Select Yes/No for each illness" />
                    </Label>
                    <div className="rounded-lg border p-4">
                        <div className="grid grid-cols-1 gap-x-4 gap-y-3 lg:grid-cols-3">
                            {illnessOptions.map((illness) => {
                                const valueKey =
                                    `illness_${illness.toLowerCase().replace(/\s+/g, '_')}_value` as keyof MaidFormData;

                                return (
                                    <div
                                        key={illness}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <Label className="flex-1 text-sm font-normal">
                                            {illness}
                                        </Label>
                                        <Select
                                            value={String(data[valueKey] || '')}
                                            onValueChange={(value) =>
                                                handleIllnessSelect(
                                                    illness,
                                                    value,
                                                )
                                            }
                                            disabled={isView}
                                        >
                                            <SelectTrigger className="h-8 w-[85px]">
                                                <SelectValue placeholder="Select" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="Yes">
                                                    Yes
                                                </SelectItem>
                                                <SelectItem value="No">
                                                    No
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                );
                            })}

                            {/* Others textarea */}
                            <div className="flex flex-col gap-1 lg:col-span-3">
                                <div className="flex items-center gap-3">
                                    <Label className="w-[100px] text-sm font-normal">
                                        Others
                                    </Label>
                                    <ProperInput
                                        value={String(
                                            data.illness_others_value ?? '',
                                        )}
                                        onCommit={(value) =>
                                            setData(
                                                'illness_others_value',
                                                value,
                                            )
                                        }
                                        placeholder="Specify other illness (if any)"
                                        disabled={isView}
                                        className="flex-1"
                                        textarea={true}
                                    />
                                </div>
                                <p className="text-right text-xs text-muted-foreground">
                                    {
                                        String(data.illness_others_value ?? '')
                                            .length
                                    }
                                    /200 characters
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* 18. Physical disabilities */}
                <div className="grid w-full items-center gap-3">
                    <Label
                        htmlFor="physical_disabilities"
                        className="flex items-center gap-1"
                    >
                        18. Physical disabilities
                        <FieldRequirements
                            hint="Describe any physical disabilities"
                            example="None, or describe specific condition"
                        />
                    </Label>
                    <ProperInput
                        id="physical_disabilities"
                        value={data.physical_disabilities ?? ''}
                        onCommit={(value) =>
                            setData('physical_disabilities', value)
                        }
                        placeholder="Enter physical disabilities if any"
                        disabled={isView}
                        textarea={true}
                    />
                    <p className="text-right text-xs text-muted-foreground">
                        {(data.physical_disabilities ?? '').length}/200
                        characters
                    </p>
                </div>

                {/* 19. Dietary Restrictions */}
                <div className="grid w-full items-center gap-3">
                    <Label
                        htmlFor="dietary_restrictions"
                        className="flex items-center gap-1"
                    >
                        19. Dietary Restrictions
                        <FieldRequirements
                            hint="List any dietary restrictions"
                            example="Vegetarian, Halal, No dairy"
                        />
                    </Label>
                    <ProperInput
                        id="dietary_restrictions"
                        value={data.dietary_restrictions ?? ''}
                        onCommit={(value) =>
                            setData('dietary_restrictions', value)
                        }
                        placeholder="Enter dietary restrictions if any"
                        disabled={isView}
                        textarea={true}
                    />
                    <p className="text-right text-xs text-muted-foreground">
                        {(data.dietary_restrictions ?? '').length}/200
                        characters
                    </p>
                </div>

                {/* 20. Food handling preferences */}
                <div className="grid w-full items-center gap-3">
                    <Label className="flex items-center gap-1">
                        20. Food handling preferences
                        <FieldRequirements hint="Select willingness to handle each food type" />
                    </Label>
                    <div className="rounded-lg border p-4">
                        {/* 2 Grid Layout: Left (Beef/Pork) and Right (Others) */}
                        <div className="grid grid-cols-1 gap-3 lg:grid-cols-4">
                            {/* Left Grid: Beef and Pork */}
                            <div className="col-span-4 space-y-3 lg:col-span-1">
                                <div className="flex items-center justify-between gap-3">
                                    <Label className="flex-1 text-sm font-normal">
                                        Beef
                                    </Label>
                                    <Select
                                        value={String(data.food_handling_no_beef_value || '')}
                                        onValueChange={(value) =>
                                            setData(
                                                'food_handling_no_beef_value',
                                                value,
                                            )
                                        }
                                        disabled={isView}
                                    >
                                        <SelectTrigger className="h-8 w-[100px]">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="No">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="Yes">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex items-center justify-between gap-3">
                                    <Label className="flex-1 text-sm font-normal">
                                        Pork
                                    </Label>
                                    <Select
                                        value={String(data.food_handling_no_pork_value || '')}
                                        onValueChange={(value) =>
                                            setData(
                                                'food_handling_no_pork_value',
                                                value,
                                            )
                                        }
                                        disabled={isView}
                                    >
                                        <SelectTrigger className="h-8 w-[100px]">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="No">
                                                Yes
                                            </SelectItem>
                                            <SelectItem value="Yes">
                                                No
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Right Grid: Others */}
                            <div className="col-span-4 flex flex-col gap-1 lg:col-span-3">
                                <div className="flex items-start gap-3">
                                    <Label className="w-[80px] pt-1 text-sm font-normal">
                                        Others
                                    </Label>
                                    <ProperInput
                                        value={String(
                                            data.food_handling_others_value ??
                                            '',
                                        )}
                                        onCommit={(value) =>
                                            setData(
                                                'food_handling_others_value',
                                                value,
                                            )
                                        }
                                        placeholder="Specify other food preferences (if any)"
                                        disabled={isView}
                                        className="flex-1"
                                        textarea={true}
                                    />
                                </div>
                                <p className="text-right text-xs text-muted-foreground">
                                    {
                                        String(
                                            data.food_handling_others_value ??
                                            '',
                                        ).length
                                    }
                                    /200 characters
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
