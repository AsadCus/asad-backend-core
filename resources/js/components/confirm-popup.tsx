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
import { useState } from 'react';

type ConfirmDialogVariant = 'primary' | 'secondary' | 'warning' | 'destructive';

type ConfirmDialogOptions = {
    title: string;
    message: string;
    confirmText: string;
    cancelText: string;
    variant: ConfirmDialogVariant;
    onConfirm: () => void;
};

export default function useConfirmDialog() {
    const [isOpen, setIsOpen] = useState(false);
    const [options, setOptions] = useState<ConfirmDialogOptions>({
        title: 'Are you sure?',
        message: 'This action cannot be undone.',
        confirmText: 'Yes',
        cancelText: 'Cancel',
        variant: 'destructive',
        onConfirm: () => {},
    });

    const variantClasses: Record<ConfirmDialogVariant, string> = {
        primary: 'bg-primary text-white hover:bg-primary/80',
        secondary: 'bg-secondary text-white hover:bg-secondary/80',
        warning: 'bg-yellow-500 text-white hover:bg-yellow-600',
        destructive: 'bg-destructive text-white hover:bg-destructive/80',
    };

    const confirm = ({
        title = 'Are you sure?',
        message = 'This action cannot be undone.',
        confirmText = 'Yes',
        cancelText = 'Cancel',
        variant = 'destructive',
        onConfirm = () => {},
    }) => {
        setOptions({
            title,
            message,
            confirmText,
            cancelText,
            variant,
            onConfirm,
        } as ConfirmDialogOptions);
        setIsOpen(true);
    };

    const ConfirmDialog = () => (
        <AlertDialog open={isOpen} onOpenChange={setIsOpen}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{options.title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {options.message}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel onClick={() => setIsOpen(false)}>
                        {options.cancelText}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={() => {
                            options.onConfirm();
                            setIsOpen(false);
                        }}
                        className={variantClasses[options.variant]}
                    >
                        {options.confirmText}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );

    return { confirm, ConfirmDialog };
}
