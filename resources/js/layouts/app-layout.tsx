import { Toaster } from '@/components/ui/sonner';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
// import AppHeaderLayout from './app/app-header-layout';
import { SharedData, type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { toast } from 'sonner';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => {
    const { flash } = usePage<SharedData>().props;

    useEffect(() => {
        if (flash.success) {
            toast.success('Success', {
                description: flash.success,
            });
        }

        if (flash.error) {
            toast.error('Error', {
                description: flash.error,
            });
            console.log(flash.error);
        }
    }, [flash]);

    return (
        // <AppHeaderLayout breadcrumbs={breadcrumbs} {...props}>
        //     {children}
        //     <Toaster />
        // </AppHeaderLayout>
        <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
            {children}
            <Toaster />
        </AppLayoutTemplate>
    );
};
