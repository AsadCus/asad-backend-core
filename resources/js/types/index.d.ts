import { NotificationItem } from '@/pages/notifications';
import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    roles: string[];
    permissions: string[];
    is_ghost_user?: boolean;
    hide_customer_from_user_management: boolean;
    can_view_documentation?: boolean;
    notifications: NotificationItem[];
    scope_mode?: 'country' | 'branch' | string;
    scope_labels?: string[];
    scope_country_options?: Array<{ id: number; label: string }>;
    scope_selected_country_ids?: number[];
    scope_selected_branch_ids?: number[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href?: InertiaLinkProps['href'] | null;
    icon?: LucideIcon | null;
    isActive?: boolean;
    matchExact?: boolean;
    subItems?: NavItem[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    flash: {
        success?: string | null;
        error?: string | null;
        result?: {
            success?: boolean;
            message?: string;
            data?: Record<string, unknown>;
            metadata?: Record<string, unknown>;
            photos?: {
                total_found?: number;
                uploaded?: Array<{ url: string; [key: string]: unknown }>;
            };
        };
    };
    [key: string]: unknown;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: Permission[];
}

export interface User {
    id: number;
    name: string;
    email: string;
    roles?: Role[];
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    sales?: Sales;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Sales {
    id: number;
    user_id: number;
    branch_id?: number | null;
    country_id?: number | null;
    branch_ids?: number[];
    country_ids?: number[];
    registration_number: string;
}

// misc types
export interface OptionType {
    value: string;
    label: string;
}

export interface ValueNumberOptionType {
    value: number;
    label: string;
}
