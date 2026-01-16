import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MaidFormData, SetDataFn } from '../types';

type EvaluationSectionProps = {
    data: MaidFormData;
    setData: SetDataFn;
    isView: boolean;
};

export function EvaluationSection({ data, setData, isView }: EvaluationSectionProps) {
    return (
        <section className="space-y-4">
            <p className="text-l border-b py-2 font-semibold">
                Evaluation Method
            </p>

            <div className="space-y-4">
                {/* No Evaluation */}
                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={data.eval_declaration_no_eval ?? false}
                        onChange={(e) => setData('eval_declaration_no_eval', e.target.checked)}
                        disabled={isView}
                    />
                    <span className="text-sm">No evaluation conducted</span>
                </label>

                {/* Singapore Interview */}
                <div className="space-y-3 rounded-md border p-4">
                    <label className="flex items-center gap-2 font-medium">
                        <input
                            type="checkbox"
                            checked={data.eval_sg_interview ?? false}
                            onChange={(e) => setData('eval_sg_interview', e.target.checked)}
                            disabled={isView}
                        />
                        <span className="text-sm">Singapore Interview</span>
                    </label>

                    {data.eval_sg_interview && (
                        <div className="ml-6 space-y-2">
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.eval_sg_phone ?? false}
                                    onChange={(e) => setData('eval_sg_phone', e.target.checked)}
                                    disabled={isView}
                                />
                                <span className="text-sm">Telephone</span>
                            </label>

                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.eval_sg_video ?? false}
                                    onChange={(e) => setData('eval_sg_video', e.target.checked)}
                                    disabled={isView}
                                />
                                <span className="text-sm">Video call</span>
                            </label>

                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.eval_sg_in_person ?? false}
                                    onChange={(e) => setData('eval_sg_in_person', e.target.checked)}
                                    disabled={isView}
                                />
                                <span className="text-sm">In-person</span>
                            </label>

                            {data.eval_sg_in_person && (
                                <label className="ml-6 flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={data.eval_sg_in_person_observed ?? false}
                                        onChange={(e) => setData('eval_sg_in_person_observed', e.target.checked)}
                                        disabled={isView}
                                    />
                                    <span className="text-sm">Observed performing tasks</span>
                                </label>
                            )}
                        </div>
                    )}
                </div>

                {/* Overseas Interview */}
                <div className="space-y-3 rounded-md border p-4">
                    <label className="flex items-center gap-2 font-medium">
                        <input
                            type="checkbox"
                            checked={data.eval_overseas_interview ?? false}
                            onChange={(e) => setData('eval_overseas_interview', e.target.checked)}
                            disabled={isView}
                        />
                        <span className="text-sm">Overseas Interview</span>
                    </label>

                    {data.eval_overseas_interview && (
                        <div className="ml-6 space-y-3">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="eval_overseas_name">Evaluator Name</Label>
                                    <Input
                                        id="eval_overseas_name"
                                        type="text"
                                        value={data.eval_overseas_name ?? ''}
                                        onChange={(e) => setData('eval_overseas_name', e.target.value)}
                                        placeholder="Name of evaluator"
                                        disabled={isView}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="eval_overseas_cert">Certificate/Qualification</Label>
                                    <Input
                                        id="eval_overseas_cert"
                                        type="text"
                                        value={data.eval_overseas_cert ?? ''}
                                        onChange={(e) => setData('eval_overseas_cert', e.target.value)}
                                        placeholder="Certificate or qualification"
                                        disabled={isView}
                                    />
                                </div>
                            </div>

                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.eval_overseas_phone ?? false}
                                    onChange={(e) => setData('eval_overseas_phone', e.target.checked)}
                                    disabled={isView}
                                />
                                <span className="text-sm">Telephone</span>
                            </label>

                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.eval_overseas_video ?? false}
                                    onChange={(e) => setData('eval_overseas_video', e.target.checked)}
                                    disabled={isView}
                                />
                                <span className="text-sm">Video call</span>
                            </label>

                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.eval_overseas_in_person ?? false}
                                    onChange={(e) => setData('eval_overseas_in_person', e.target.checked)}
                                    disabled={isView}
                                />
                                <span className="text-sm">In-person</span>
                            </label>

                            {data.eval_overseas_in_person && (
                                <label className="ml-6 flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={data.eval_overseas_in_person_observed ?? false}
                                        onChange={(e) => setData('eval_overseas_in_person_observed', e.target.checked)}
                                        disabled={isView}
                                    />
                                    <span className="text-sm">Observed performing tasks</span>
                                </label>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
