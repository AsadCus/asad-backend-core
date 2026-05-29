import {
    ArrowLeftRight,
    BarChart3,
    CheckCircle2,
    ClipboardList,
    FileSpreadsheet,
    FileText,
    LayoutDashboard,
    Map,
    MessageSquare,
    PauseCircle,
    Receipt,
    RotateCcw,
    Settings,
    Truck,
    UserCheck,
    Users,
    Wallet,
    XCircle,
    type LucideIcon,
} from 'lucide-react';

const MODULE_ICON_MAP: Record<string, LucideIcon> = {
    dashboard: LayoutDashboard,
    master: Settings,
    sales: BarChart3,
    customer: Users,
    enquiry: MessageSquare,
    'confirmed-customer': UserCheck,
    'confirmed customer': UserCheck,
    quotation: FileText,
    invoice: FileSpreadsheet,
    receipt: Receipt,
    'change-package': ArrowLeftRight,
    'change package': ArrowLeftRight,
    refund: RotateCcw,
    'customer-holding-area': PauseCircle,
    'customer holding area': PauseCircle,
    'completed-customer': CheckCircle2,
    'completed customer': CheckCircle2,
    'cancelled-customer': XCircle,
    'cancelled customer': XCircle,
    'ops-movement': Truck,
    'ops movement': Truck,
    pif: ClipboardList,
    itinerary: Map,
    budget: Wallet,
};

export function getModuleIcon(menuName: string): LucideIcon {
    const key = menuName
        .toLowerCase()
        .replace(/ module$/i, '')
        .trim();
    return MODULE_ICON_MAP[key] ?? FileText;
}

export const slugify = (text: string): string =>
    text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

export const normalizeText = (value: string): string =>
    value.toLowerCase().trim();

export const matchesQuery = (value: string, query: string): boolean =>
    normalizeText(value).includes(normalizeText(query));
