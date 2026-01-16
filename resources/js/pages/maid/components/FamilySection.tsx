import { Label } from '@/components/ui/label';
import { MaidFormData, SetDataFn } from '../types';
import { FieldError } from './FieldError';
import { ProperInput } from '../../../components/proper-input';

type FamilySectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
    errors: Partial<Record<keyof MaidFormData, string>>;
};

export function FamilySection({ data, setData, isView, errors }: FamilySectionProps) {
    const totalChildren = Number(data.number_of_children ?? 0) || 0;

    const renderChildAgeInput = (index: number) => {
        const ages = (data.children_ages ?? '')
            .split(',')
            .map((age) => age.trim())
            .filter((age, i) => i < totalChildren); // Only keep ages up to totalChildren

        // Ensure ages array has enough slots
        while (ages.length < totalChildren) {
            ages.push('');
        }

        return (
            <div key={index} className="grid w-full items-center gap-3">
                <Label htmlFor={`child_age_${index}`}>Child {index + 1} Age</Label>
                <div className="relative">
                    <ProperInput
                        id={`child_age_${index}`}
                        value={ages[index] ?? ''}
                        onCommit={(value) => {
                            const next = [...ages];
                            next[index] = value;
                            // Filter out empty trailing values to keep data clean
                            const cleanAges = next.slice(0, totalChildren);
                            setData('children_ages', cleanAges.join(', '));
                        }}
                        placeholder="Age"
                        disabled={isView}
                        type="number"
                    />
                    <span className="absolute top-1/2 right-4 -translate-y-1/2 text-sm text-gray-500">
                        years old
                    </span>
                </div>
            </div>
        );
    };

    return (
        <section className="space-y-4">
            <p className="text-l border-b py-2 font-semibold">Siblings &amp; Children</p>
            <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="number_of_siblings">Number of Siblings</Label>
                    <div className="relative">
                        <ProperInput
                            id="number_of_siblings"
                            value={data.number_of_siblings ?? ''}
                            onCommit={(value) =>
                                setData('number_of_siblings', value)
                            }
                            placeholder="Number of Siblings"
                            disabled={isView}
                            type="number"
                        />
                        <FieldError message={errors.number_of_siblings} />
                    </div>
                </div>

                <div className="grid w-full items-center gap-3">
                    <Label htmlFor="number_of_children">Number of Children</Label>
                    <div className="relative">
                        <ProperInput
                            id="number_of_children"
                            value={data.number_of_children ?? ''}
                            onCommit={(value) => {
                                const newCount = Number(value) || 0;
                                setData('number_of_children', value);
                                
                                // Auto-adjust children_ages array based on new count
                                const currentAges = (data.children_ages ?? '')
                                    .split(',')
                                    .map((age) => age.trim())
                                    .filter(Boolean);
                                
                                const newAges = Array.from({ length: newCount }, (_, index) => 
                                    currentAges[index] ?? ''
                                );
                                
                                setData('children_ages', newAges.join(', '));
                            }}
                            placeholder="Number of Children"
                            disabled={isView}
                            type="number"
                        />
                        <FieldError message={errors.number_of_children} />
                        <FieldError message={errors.children_ages} />
                    </div>
                </div>
            </div>

            {totalChildren > 0 && (
                <div className="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
                    {Array.from({ length: totalChildren }).map((_, index) =>
                        renderChildAgeInput(index),
                    )}
                </div>
            )}
        </section>
    );
}
