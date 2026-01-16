import { MaidSchema } from './schema';

export type SkillRow = {
    area?: string;
    willingness?: string;
    experience?: string;
    experience_yesno?: string;
    experience_years?: string;
    assessment?: string;
    observation?: string;
    assessment_observation?: string;
};

export type MaidFormData = MaidSchema & {
    _method?: 'PUT' | 'POST';
    eval_declaration_no_eval?: boolean;
    eval_sg_interview?: boolean;
    eval_sg_phone?: boolean;
    eval_sg_video?: boolean;
    eval_sg_in_person?: boolean;
    eval_sg_in_person_observed?: boolean;
    eval_overseas_interview?: boolean;
    eval_overseas_name?: string | null;
    eval_overseas_cert?: string | null;
    eval_overseas_phone?: boolean;
    eval_overseas_video?: boolean;
    eval_overseas_in_person?: boolean;
    eval_overseas_in_person_observed?: boolean;
};

export type SetDataFn = <K extends keyof MaidFormData>(
    key: K,
    value: MaidFormData[K],
) => void;
