import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { type SharedData } from '@/types';
import { dashboard } from '@/routes';
import { router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Fingerprint,
    FileText,
    LayoutGrid,
    LockKeyhole,
    Palette,
    Search,
    Settings,
    UserCog,
} from 'lucide-react';
import * as React from 'react';

type CommandAction = {
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    onSelect: () => void;
};

export function CommandMenu({
    open,
    setOpen,
}: {
    open: boolean;
    setOpen: React.Dispatch<React.SetStateAction<boolean>>;
}) {
    const { auth } = usePage<SharedData>().props;
    const isSuperadmin = auth.roles.includes('superadmin');
    const isGhostSuperadmin = isSuperadmin && Boolean(auth.is_ghost_user);

    React.useEffect(() => {
        const down = (event: KeyboardEvent) => {
            if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                setOpen((current) => !current);
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

    const navigationActions: CommandAction[] = [
        {
            label: 'Dashboard',
            icon: LayoutGrid,
            onSelect: () => runCommand(() => router.visit(dashboard())),
        },
        {
            label: 'Documentation',
            icon: BookOpen,
            onSelect: () => runCommand(() => router.visit('/support/documentation')),
        },
    ];

    const accountActions: CommandAction[] = [
        {
            label: 'Profile Settings',
            icon: UserCog,
            onSelect: () => runCommand(() => router.visit('/settings/profile')),
        },
        {
            label: 'Password Settings',
            icon: LockKeyhole,
            onSelect: () => runCommand(() => router.visit('/settings/password')),
        },
        {
            label: 'Two-Factor Auth',
            icon: Fingerprint,
            onSelect: () => runCommand(() => router.visit('/settings/two-factor')),
        },
    ];

    const adminActions: CommandAction[] = [
        {
            label: 'Appearance',
            icon: Palette,
            onSelect: () => runCommand(() => router.visit('/settings/appearance')),
        },
    ];

    const ghostActions: CommandAction[] = [
        {
            label: 'Report Template',
            icon: FileText,
            onSelect: () => runCommand(() => router.visit('/settings/report-template')),
        },
        {
            label: 'Model Number Formats',
            icon: Settings,
            onSelect: () => runCommand(() => router.visit('/settings/model-number-formats')),
        },
    ];

    const helperActions: CommandAction[] = [
        {
            label: 'Open documentation and search modules',
            icon: Search,
            onSelect: () => runCommand(() => router.visit('/support/documentation')),
        },
    ];

    return (
        <CommandDialog open={open} onOpenChange={setOpen}>
            <CommandInput placeholder="Search navigation or settings..." />
            <CommandList>
                <CommandEmpty>No results found.</CommandEmpty>

                <CommandGroup heading="Navigation">
                    {navigationActions.map((action) => (
                        <CommandItem key={action.label} onSelect={action.onSelect}>
                            <action.icon className="mr-2 h-4 w-4" />
                            <span>{action.label}</span>
                        </CommandItem>
                    ))}
                </CommandGroup>

                <CommandGroup heading="Account">
                    {accountActions.map((action) => (
                        <CommandItem key={action.label} onSelect={action.onSelect}>
                            <action.icon className="mr-2 h-4 w-4" />
                            <span>{action.label}</span>
                        </CommandItem>
                    ))}
                </CommandGroup>

                {(isSuperadmin || isGhostSuperadmin) && (
                    <CommandGroup heading="Admin Settings">
                        {isSuperadmin && adminActions.map((action) => (
                            <CommandItem key={action.label} onSelect={action.onSelect}>
                                <action.icon className="mr-2 h-4 w-4" />
                                <span>{action.label}</span>
                            </CommandItem>
                        ))}

                        {isGhostSuperadmin && ghostActions.map((action) => (
                            <CommandItem key={action.label} onSelect={action.onSelect}>
                                <action.icon className="mr-2 h-4 w-4" />
                                <span>{action.label}</span>
                            </CommandItem>
                        ))}
                    </CommandGroup>
                )}

                <CommandGroup heading="Quick Helper">
                    {helperActions.map((action) => (
                        <CommandItem key={action.label} onSelect={action.onSelect}>
                            <action.icon className="mr-2 h-4 w-4" />
                            <span>{action.label}</span>
                        </CommandItem>
                    ))}
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    );
}
