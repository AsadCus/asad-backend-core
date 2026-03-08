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

interface ValidationAlertDialogItem {
    key: string;
    label: string;
}

interface ValidationAlertDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    items?: ValidationAlertDialogItem[];
    cancelText?: string;
    confirmText?: string;
    onConfirm?: () => void;
}

export function ValidationAlertDialog({
    open,
    onOpenChange,
    title,
    description,
    items = [],
    cancelText = 'Close',
    confirmText = 'Continue',
    onConfirm,
}: ValidationAlertDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>

                {items.length > 0 && (
                    <div className="rounded-md border bg-muted/40 p-3">
                        <ul className="list-inside list-disc space-y-1 text-sm">
                            {items.map((item) => (
                                <li key={item.key}>{item.label}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <AlertDialogFooter>
                    <AlertDialogCancel>{cancelText}</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm}>
                        {confirmText}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
