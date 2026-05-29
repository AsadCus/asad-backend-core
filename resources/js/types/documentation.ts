export interface ManualInfo {
    title: string;
    copyright: string;
}

export interface BibliographyItem {
    id: string;
    title: string;
}

export interface RoleGuideItem {
    role: string;
    scope: string;
    primary_actions: string[];
}

export interface MenuStructureItem {
    menu: string;
    children: string[];
}

export interface PlaybookContentBlock {
    type: 'text' | 'image' | 'gif';
    text?: string;
    src?: string;
    alt?: string;
}

export interface PlaybookStep {
    text?: string;
    path?: string;
    screenshot?: string;
    content_blocks?: PlaybookContentBlock[];
}

export interface PlaybookProcedure {
    name: string;
    screenshot?: string;
    purpose?: string;
    features?: string[];
    steps: PlaybookStep[];
}

export interface ModulePlaybook {
    id: string;
    title: string;
    overview: string;
    highlights: string[];
    procedures: PlaybookProcedure[];
}

export interface MenuGroup {
    menu: string;
    module: string;
    purpose: string;
    features: string[];
    how_to: string[];
    route_path?: string;
}

export interface WorkflowStep {
    text: string;
    path?: string;
}

export interface Workflow {
    name: string;
    goal: string;
    steps: WorkflowStep[];
}

export interface HowToStep {
    text: string;
    path?: string;
}

export interface HowToGuide {
    task: string;
    steps: HowToStep[];
}

export interface CommonStatus {
    topic: string;
    notes: string[];
}

export interface DocumentationData {
    manual: ManualInfo;
    introduction: string;
    bibliography: BibliographyItem[];
    roleGuide: RoleGuideItem[];
    menuStructure: MenuStructureItem[];
    modulePlaybooks: ModulePlaybook[];
    menuGroups: MenuGroup[];
    coreWorkflows: Workflow[];
    howToGuides: HowToGuide[];
    commonStatuses: CommonStatus[];
    tips: string[];
}

export interface DocumentationPageProps {
    documentation: DocumentationData;
    moduleSlug?: string | null;
    procedureSlug?: string | null;
}
