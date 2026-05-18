import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { dashboard } from '@/routes';
import { router } from '@inertiajs/react';
import { BookOpen, LayoutGrid, Settings, Users } from 'lucide-react';
import * as React from 'react';

export function CommandMenu({
    open,
    setOpen,
}: {
    open: boolean;
    setOpen: (open: boolean) => void;
}) {
    React.useEffect(() => {
        const down = (e: KeyboardEvent) => {
            if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setOpen(open => !open);
            }
        };

        document.addEventListener('keydown', down);
        return () => document.removeEventListener('keydown', down);
    }, [setOpen]);

    const runCommand = React.useCallback(
        (command: () => void) => {
            setOpen(false);
            command();
        },
        [setOpen],
    );

    return (
        <CommandDialog open={open} onOpenChange={setOpen}>
            <CommandInput placeholder="Type a command or search..." />
            <CommandList>
                <CommandEmpty>No results found.</CommandEmpty>
                <CommandGroup heading="Navigation">
                    <CommandItem
                        onSelect={() => runCommand(() => router.visit(dashboard()))}
                    >
                        <LayoutGrid className="mr-2 h-4 w-4" />
                        <span>Dashboard</span>
                    </CommandItem>
                    <CommandItem
                        onSelect={() => runCommand(() => router.visit('/support/documentation'))}
                    >
                        <BookOpen className="mr-2 h-4 w-4" />
                        <span>Documentation</span>
                    </CommandItem>
                </CommandGroup>
                <CommandGroup heading="Settings">
                    <CommandItem
                        onSelect={() => runCommand(() => router.visit('/settings/profile'))}
                    >
                        <Settings className="mr-2 h-4 w-4" />
                        <span>Profile Settings</span>
                    </CommandItem>
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    );
}
