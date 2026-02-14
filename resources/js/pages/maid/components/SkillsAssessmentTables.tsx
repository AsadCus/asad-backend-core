import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { MaidFormData, SetDataFn, SkillRow } from '../types';

const AREAS_ALL = [
    'Care of infants/children',
    'Care of elderly',
    'Care of disabled',
    'General housework',
    'Cooking',
    'Language abilities (spoken)',
    'Other skills',
];

type SkillsAssessmentTablesProps = {
    isView: boolean;
    data: MaidFormData;
    setData: SetDataFn;
};

export function SkillsAssessmentTables({
    isView,
    data,
    setData,
}: SkillsAssessmentTablesProps) {
    const renderSingaporeEvaluation = () => (
        <div className="mb-6 space-y-4">
            {/* B1 Subheader */}
            <h3 className="border-b pb-2 text-lg font-semibold">
                B1. Method of Evaluation of Skills
            </h3>

            <p className="text-base text-gray-700 dark:text-gray-300">
                Please indicate the method(s) used to evaluate the FDW's skills
                (can tick more than one):
            </p>

            <div className="space-y-4">
                {/* Option 1: Based on FDW's declaration */}
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="eval_declaration_no_eval"
                        checked={data.eval_declaration_no_eval ?? false}
                        onCheckedChange={(checked) =>
                            setData(
                                'eval_declaration_no_eval',
                                checked as boolean,
                            )
                        }
                        disabled={isView}
                    />
                    <Label
                        htmlFor="eval_declaration_no_eval"
                        className="cursor-pointer text-base font-normal"
                    >
                        Based on FDW's declaration, no evaluation/observation by
                        Singapore EA or overseas training centre/EA
                    </Label>
                </div>

                {/* Option 2: Interviewed by Singapore EA */}
                <div className="space-y-3">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="eval_sg_interview"
                            checked={data.eval_sg_interview ?? false}
                            onCheckedChange={(checked) =>
                                setData('eval_sg_interview', checked as boolean)
                            }
                            disabled={isView}
                        />
                        <Label
                            htmlFor="eval_sg_interview"
                            className="cursor-pointer text-base font-semibold"
                        >
                            Interviewed by Singapore EA
                        </Label>
                    </div>

                    <div className="ml-6 space-y-3">
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="eval_sg_phone"
                                checked={data.eval_sg_phone ?? false}
                                onCheckedChange={(checked) =>
                                    setData('eval_sg_phone', checked as boolean)
                                }
                                disabled={isView}
                            />
                            <Label
                                htmlFor="eval_sg_phone"
                                className="cursor-pointer text-base font-normal"
                            >
                                Interviewed via telephone/teleconference
                            </Label>
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="eval_sg_video"
                                checked={data.eval_sg_video ?? false}
                                onCheckedChange={(checked) =>
                                    setData('eval_sg_video', checked as boolean)
                                }
                                disabled={isView}
                            />
                            <Label
                                htmlFor="eval_sg_video"
                                className="cursor-pointer text-base font-normal"
                            >
                                Interviewed via videoconference
                            </Label>
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="eval_sg_in_person"
                                checked={data.eval_sg_in_person ?? false}
                                onCheckedChange={(checked) =>
                                    setData(
                                        'eval_sg_in_person',
                                        checked as boolean,
                                    )
                                }
                                disabled={isView}
                            />
                            <Label
                                htmlFor="eval_sg_in_person"
                                className="cursor-pointer text-base font-normal"
                            >
                                Interviewed in-person
                            </Label>
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="eval_sg_in_person_observed"
                                checked={
                                    data.eval_sg_in_person_observed ?? false
                                }
                                onCheckedChange={(checked) =>
                                    setData(
                                        'eval_sg_in_person_observed',
                                        checked as boolean,
                                    )
                                }
                                disabled={isView}
                            />
                            <Label
                                htmlFor="eval_sg_in_person_observed"
                                className="cursor-pointer text-base font-normal"
                            >
                                Interviewed in person and also made observation
                                of FDW in the areas of work listed in table
                            </Label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    const renderNumeric = (
        title: string,
        areas: string[],
        fieldName: 'skills_assessment_singapore' | 'skills_assessment_overseas',
    ) => {
        const rows = data[fieldName] || [];

        const getRow = (area: string): SkillRow => {
            const found = rows.find((row) => row.area === area);
            if (found) {
                return {
                    ...found,
                    area: found.area ?? '',
                    willingness: found.willingness ?? '',
                    experience: found.experience ?? '',
                    assessment: found.assessment ?? '',
                    observation: found.observation ?? '',
                };
            }
            return {
                area,
                willingness: '',
                experience: '',
                assessment: '',
                observation: '',
            };
        };

        const setRow = (area: string, field: keyof SkillRow, value: string) => {
            const next = [...rows];
            const index = next.findIndex((row) => row.area === area);
            if (index >= 0) {
                next[index] = { ...next[index], [field]: value };
            } else {
                next.push({ area, [field]: value } as SkillRow);
            }
            setData(fieldName, next);
        };

        return (
            <div className="mb-6 rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="min-w-[200px] font-semibold">
                                Areas of Work
                            </TableHead>
                            <TableHead className="w-32 text-center font-semibold">
                                Willingness
                                <br />
                                Yes / No
                            </TableHead>
                            <TableHead className="w-40 text-center font-semibold">
                                Experience
                                <br />
                                Yes/No
                                <br />
                                If Yes, state the
                                <br />
                                no. of years
                            </TableHead>
                            <TableHead className="min-w-[280px] text-center font-semibold">
                                Assessment / Observation
                                <br />
                                Please state qualitative observations of FDW
                                and/or rate
                                <br />
                                the FDW (indicate N.A. if no evaluation was
                                done)
                                <br />
                                Poor
                                ...............................Excellent....N.A.
                                <br />
                                1&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;5&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;N.A.
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {areas.map((area) => {
                            const row = getRow(area);

                            return (
                                <TableRow key={area}>
                                    <TableCell className="font-semibold">
                                        {area}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <Input
                                            type="text"
                                            className="mx-auto w-20 text-center"
                                            placeholder="Yes/No"
                                            value={row.willingness || ''}
                                            onChange={(event) =>
                                                setRow(
                                                    area,
                                                    'willingness',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={isView}
                                        />
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <Input
                                            type="text"
                                            className="w-full"
                                            placeholder="Yes/No or specify years"
                                            value={row.experience || ''}
                                            onChange={(event) =>
                                                setRow(
                                                    area,
                                                    'experience',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={isView}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <Textarea
                                            className="w-full min-w-[200px]"
                                            placeholder="Assessment/Observation"
                                            value={row.observation || ''}
                                            onChange={(event) =>
                                                setRow(
                                                    area,
                                                    'observation',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={isView}
                                            rows={2}
                                        />
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>
        );
    };

    const renderOverseasEvaluation = () => (
        <div className="mb-6 space-y-4">
            {/* Option 3: Interviewed by overseas training centre / EA */}
            <div className="space-y-3">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="eval_overseas_interview"
                        checked={data.eval_overseas_interview ?? false}
                        onCheckedChange={(checked) =>
                            setData(
                                'eval_overseas_interview',
                                checked as boolean,
                            )
                        }
                        disabled={isView}
                    />
                    <div className="flex flex-1 flex-wrap items-center gap-1">
                        <Label
                            htmlFor="eval_overseas_interview"
                            className="cursor-pointer text-base font-normal"
                        >
                            Interviewed by overseas training centre / EA (Please
                            state name of foreign training centre / EA:
                        </Label>
                        <Input
                            type="text"
                            value={data.eval_overseas_name ?? ''}
                            onChange={(e) =>
                                setData('eval_overseas_name', e.target.value)
                            }
                            placeholder="Name of EA"
                            disabled={isView}
                            className="h-8 w-64"
                        />
                        <span className="text-base">)</span>
                    </div>
                </div>

                <div className="ml-6 space-y-3">
                    <div className="flex items-center gap-2">
                        <span className="text-base">
                            State if the third party is certified (e.g. ISO9001)
                            or audited periodically by the EA:{' '}
                        </span>
                        <Input
                            type="text"
                            value={data.eval_overseas_cert ?? ''}
                            onChange={(e) =>
                                setData('eval_overseas_cert', e.target.value)
                            }
                            placeholder="Click to input"
                            disabled={isView}
                            className="h-8 w-48"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="eval_overseas_phone"
                            checked={data.eval_overseas_phone ?? false}
                            onCheckedChange={(checked) =>
                                setData(
                                    'eval_overseas_phone',
                                    checked as boolean,
                                )
                            }
                            disabled={isView}
                        />
                        <Label
                            htmlFor="eval_overseas_phone"
                            className="cursor-pointer text-base font-normal"
                        >
                            Interviewed via telephone/teleconference
                        </Label>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="eval_overseas_video"
                            checked={data.eval_overseas_video ?? false}
                            onCheckedChange={(checked) =>
                                setData(
                                    'eval_overseas_video',
                                    checked as boolean,
                                )
                            }
                            disabled={isView}
                        />
                        <Label
                            htmlFor="eval_overseas_video"
                            className="cursor-pointer text-base font-normal"
                        >
                            Interviewed via videoconference
                        </Label>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="eval_overseas_in_person"
                            checked={data.eval_overseas_in_person ?? false}
                            onCheckedChange={(checked) =>
                                setData(
                                    'eval_overseas_in_person',
                                    checked as boolean,
                                )
                            }
                            disabled={isView}
                        />
                        <Label
                            htmlFor="eval_overseas_in_person"
                            className="cursor-pointer text-base font-normal"
                        >
                            Interviewed in person
                        </Label>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="eval_overseas_in_person_observed"
                            checked={
                                data.eval_overseas_in_person_observed ?? false
                            }
                            onCheckedChange={(checked) =>
                                setData(
                                    'eval_overseas_in_person_observed',
                                    checked as boolean,
                                )
                            }
                            disabled={isView}
                        />
                        <Label
                            htmlFor="eval_overseas_in_person_observed"
                            className="cursor-pointer text-base font-normal"
                        >
                            Interviewed in person and also made observation of
                            FDW in the areas of work listed in table
                        </Label>
                    </div>
                </div>
            </div>
        </div>
    );

    const renderQualitative = (
        title: string,
        areas: string[],
        fieldName: 'skills_assessment_singapore' | 'skills_assessment_overseas',
    ) => {
        const rows = data[fieldName] || [];

        const getRow = (area: string): SkillRow => {
            const found = rows.find((row) => row.area === area);
            if (found) {
                return {
                    ...found,
                    area: found.area ?? '',
                    willingness: found.willingness ?? '',
                    experience: found.experience ?? '',
                    experience_years: found.experience_years ?? '',
                    assessment: found.assessment ?? '',
                    observation: found.observation ?? '',
                };
            }
            return {
                area,
                willingness: '',
                experience: '',
                experience_years: '',
                assessment: '',
                observation: '',
            };
        };

        const setRow = (area: string, field: keyof SkillRow, value: string) => {
            const next = [...rows];
            const index = next.findIndex((row) => row.area === area);
            if (index >= 0) {
                next[index] = { ...next[index], [field]: value };
            } else {
                next.push({ area, [field]: value } as SkillRow);
            }
            setData(fieldName, next);
        };

        return (
            <div className="mb-6 rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="min-w-[200px] font-semibold">
                                Areas of Work
                            </TableHead>
                            <TableHead className="w-32 text-center font-semibold">
                                Willingness
                                <br />
                                Yes / No
                            </TableHead>
                            <TableHead className="w-40 text-center font-semibold">
                                Experience
                                <br />
                                Yes/No
                                <br />
                                If Yes, state the
                                <br />
                                no. of years
                            </TableHead>
                            <TableHead className="min-w-[280px] text-center font-semibold">
                                Assessment / Observation
                                <br />
                                Please state qualitative observations of FDW
                                and/or rate
                                <br />
                                the FDW (indicate N.A. if no evaluation was
                                done)
                                <br />
                                Poor
                                ...............................Excellent....N.A.
                                <br />
                                1&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;2&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;3&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;4&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;5&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;N.A.
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {areas.map((area) => {
                            const row = getRow(area);

                            return (
                                <TableRow key={area}>
                                    <TableCell className="font-semibold">
                                        {area}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <Input
                                            type="text"
                                            className="mx-auto w-20 text-center"
                                            placeholder="Yes/No"
                                            value={row.willingness || ''}
                                            onChange={(event) =>
                                                setRow(
                                                    area,
                                                    'willingness',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={isView}
                                        />
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <Input
                                            type="text"
                                            className="w-full"
                                            placeholder="Yes/No or specify years"
                                            value={row.experience || ''}
                                            onChange={(event) =>
                                                setRow(
                                                    area,
                                                    'experience',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={isView}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <Textarea
                                            className="w-full min-w-[280px]"
                                            placeholder="Assessment/Observation"
                                            value={row.observation || ''}
                                            onChange={(event) =>
                                                setRow(
                                                    area,
                                                    'observation',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={isView}
                                            rows={3}
                                        />
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>
        );
    };

    return (
        <div className="space-y-6">
            {renderSingaporeEvaluation()}
            {renderNumeric(
                'Singapore Evaluation',
                AREAS_ALL,
                'skills_assessment_singapore',
            )}
            {renderOverseasEvaluation()}
            {renderQualitative(
                'Overseas Evaluation',
                AREAS_ALL,
                'skills_assessment_overseas',
            )}
        </div>
    );
}
