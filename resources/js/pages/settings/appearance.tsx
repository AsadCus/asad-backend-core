import AppearanceTabs from '@/components/appearance-tabs';
import ColorThemeSwitcher from '@/components/color-theme-switcher';
import HeadingSmall from '@/components/heading-small';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, Moon, RotateCcw, Sun } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: editAppearance().url,
    },
];

export interface AppearanceSetting {
    settings: {
        auth_bg: string;
        auth_card_bg: string;
        primary_color?: string;
        border_radius?: string;
    };
}

// Preset Color Palettes
const presetColors = {
    card_light: [
        { color: '#ffffff', name: 'White' },
        { color: '#f9fafb', name: 'Gray 50' },
        { color: '#f3f4f6', name: 'Gray 100' },
        { color: '#fef3c7', name: 'Amber 100' },
        { color: '#dbeafe', name: 'Blue 100' },
    ],
    card_dark: [
        { color: '#1f2937', name: 'Gray 800' },
        { color: '#111827', name: 'Gray 900' },
        { color: '#030712', name: 'Gray 950' },
        { color: '#1e293b', name: 'Slate 800' },
        { color: '#18181b', name: 'Zinc 900' },
    ],
    backgrounds: [
        { color: '#f3f4f6', name: 'Gray' },
        { color: '#dbeafe', name: 'Blue' },
        { color: '#fef3c7', name: 'Amber' },
        { color: '#dcfce7', name: 'Green' },
    ],
    gradients: [
        {
            color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            name: 'Purple',
        },
        {
            color: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
            name: 'Pink',
        },
        {
            color: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            name: 'Blue',
        },
        {
            color: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            name: 'Green',
        },
    ],
};

// Primary color presets
const primaryColors = [
    { color: '#3b82f6', name: 'Blue' },
    { color: '#8b5cf6', name: 'Purple' },
    { color: '#ec4899', name: 'Pink' },
    { color: '#10b981', name: 'Green' },
    { color: '#f59e0b', name: 'Amber' },
    { color: '#ef4444', name: 'Red' },
];

// Border radius presets
const borderRadiusOptions = [
    { value: '0rem', name: 'None', example: 'rounded-none' },
    { value: '0.25rem', name: 'Small', example: 'rounded-sm' },
    { value: '0.5rem', name: 'Medium', example: 'rounded-md' },
    { value: '0.75rem', name: 'Large', example: 'rounded-lg' },
    { value: '1rem', name: 'XL', example: 'rounded-xl' },
];

function ThemePreview({
    settings,
    mode,
}: AppearanceSetting & { mode?: 'light' | 'dark' }) {
    const isDark = mode === 'dark';
    const borderRadius = settings.border_radius || '0.5rem';

    return (
        <div
            className={`space-y-4 rounded-lg border p-4 ${
                isDark
                    ? 'border-gray-700 bg-gray-900'
                    : 'border-gray-300 bg-gray-100'
            }`}
        >
            <div className="mb-4 flex items-center justify-between">
                <span
                    className={`flex items-center gap-2 text-base font-medium ${
                        isDark ? 'text-white' : 'text-gray-700'
                    }`}
                >
                    {isDark ? (
                        <>
                            <Moon className="h-4 w-4" /> Dark Mode Preview
                        </>
                    ) : (
                        <>
                            <Sun className="h-4 w-4" /> Light Mode Preview
                        </>
                    )}
                </span>
            </div>

            <div
                className={`rounded-lg border p-6 ${
                    !settings.auth_bg
                        ? isDark
                            ? 'border-gray-700 bg-gray-900'
                            : 'border-gray-300 bg-gray-100'
                        : ''
                }`}
                style={{ background: settings.auth_bg || undefined }}
            >
                <div
                    className={`mb-4 text-base font-medium ${
                        isDark ? 'text-gray-100' : 'text-gray-700'
                    }`}
                >
                    Auth Page Background
                </div>

                <Card
                    className={`${isDark ? 'border-gray-600 bg-gray-800' : 'border-gray-300 bg-white'}`}
                    style={{
                        background: settings.auth_card_bg || undefined,
                        borderRadius: borderRadius,
                    }}
                >
                    <CardContent className="p-4">
                        <div className="space-y-3">
                            <div
                                className={`text-base font-semibold ${
                                    isDark ? 'text-white' : 'text-gray-900'
                                }`}
                            >
                                Login Card
                            </div>
                            <div className="space-y-2">
                                <div
                                    className={`h-8 border ${
                                        isDark
                                            ? 'border-gray-600 bg-gray-700'
                                            : 'border-gray-300 bg-white'
                                    }`}
                                    style={{ borderRadius: borderRadius }}
                                />
                                <div
                                    className={`h-8 border ${
                                        isDark
                                            ? 'border-gray-600 bg-gray-700'
                                            : 'border-gray-300 bg-white'
                                    }`}
                                    style={{ borderRadius: borderRadius }}
                                />
                                <div
                                    className="text-center text-base leading-8 font-medium text-white"
                                    style={{
                                        background:
                                            settings.primary_color || '#3b82f6',
                                        borderRadius: borderRadius,
                                        height: '32px',
                                    }}
                                >
                                    Login Button
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function ColorPresetButton({
    color,
    name,
    isSelected,
    onClick,
}: {
    color: string;
    name: string;
    isSelected: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`group relative flex h-16 w-full items-center justify-center overflow-hidden rounded-md border-2 transition-all ${
                isSelected
                    ? 'border-primary ring-2 ring-primary ring-offset-2'
                    : 'border-gray-300 hover:border-gray-400'
            }`}
            style={{ background: color }}
        >
            {isSelected && (
                <div className="absolute inset-0 flex items-center justify-center bg-black/20">
                    <Check className="h-6 w-6 text-white" />
                </div>
            )}
            <span className="absolute right-0 bottom-1 left-0 text-center text-sm font-medium text-black/60 group-hover:text-black/80">
                {name}
            </span>
        </button>
    );
}

export default function Appearance({
    settings,
    auth,
}: AppearanceSetting & { auth: { user: { roles?: { name: string }[] } } }) {
    const [form, setForm] = useState({
        auth_card_bg: settings?.auth_card_bg ?? '',
        auth_bg: settings?.auth_bg ?? '',
        primary_color: settings?.primary_color ?? '#3b82f6',
        border_radius: settings?.border_radius ?? '0.5rem',
    });

    const [previewMode, setPreviewMode] = useState<'light' | 'dark' | 'both'>(
        'both',
    );
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showSuccess, setShowSuccess] = useState(false);

    const isAdmin =
        auth?.user?.roles?.some((role) => role.name === 'admin') ?? false;

    const update = (key: string, value: string) =>
        setForm({ ...form, [key]: value });

    const resetToDefault = () => {
        setForm({
            auth_card_bg: '',
            auth_bg: '',
            primary_color: '#3b82f6',
            border_radius: '0.5rem',
        });
    };

    const submit = () => {
        setIsSubmitting(true);
        router.post('/settings/appearance', form, {
            onSuccess: () => {
                setShowSuccess(true);
                setTimeout(() => setShowSuccess(false), 3000);
            },
            onFinish: () => setIsSubmitting(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appearance settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Appearance settings"
                        description="Customize your appearance preferences"
                    />

                    {/* Success Message */}
                    {showSuccess && (
                        <Card className="border-green-200 bg-green-50">
                            <CardContent className="flex items-center gap-2 py-3">
                                <Check className="h-5 w-5 text-green-600" />
                                <span className="text-base font-medium text-green-800">
                                    Appearance settings saved successfully!
                                </span>
                            </CardContent>
                        </Card>
                    )}

                    {/* Theme Switcher */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-4">
                                <div>
                                    <h3 className="text-lg font-semibold">
                                        Theme
                                    </h3>
                                    <p className="text-base text-muted-foreground">
                                        Choose your preferred color theme (saved
                                        to your browser)
                                    </p>
                                </div>
                                <AppearanceTabs />
                                <div className="space-y-2">
                                    <Label>Color Theme</Label>
                                    <ColorThemeSwitcher />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Admin Settings */}
                    {isAdmin && (
                        <Card>
                            <CardContent className="pt-6">
                                <div className="space-y-6">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <h3 className="text-lg font-semibold">
                                                Global Appearance Settings
                                            </h3>
                                            <p className="text-base text-muted-foreground">
                                                Set default appearance for all
                                                users (saved to database)
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={resetToDefault}
                                            className="gap-2"
                                        >
                                            <RotateCcw className="h-4 w-4" />
                                            Reset
                                        </Button>
                                    </div>

                                    {/* Primary Brand Color */}
                                    <div className="space-y-3">
                                        <Label>Primary Brand Color</Label>
                                        <div className="grid grid-cols-6 gap-2">
                                            {primaryColors.map((preset) => (
                                                <ColorPresetButton
                                                    key={preset.color}
                                                    color={preset.color}
                                                    name={preset.name}
                                                    isSelected={
                                                        form.primary_color ===
                                                        preset.color
                                                    }
                                                    onClick={() =>
                                                        update(
                                                            'primary_color',
                                                            preset.color,
                                                        )
                                                    }
                                                />
                                            ))}
                                        </div>
                                        <div className="flex gap-2">
                                            <Input
                                                type="color"
                                                value={
                                                    form.primary_color ||
                                                    '#3b82f6'
                                                }
                                                onChange={(e) =>
                                                    update(
                                                        'primary_color',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 w-20 cursor-pointer"
                                            />
                                            <Input
                                                type="text"
                                                placeholder="#3b82f6"
                                                value={form.primary_color || ''}
                                                onChange={(e) =>
                                                    update(
                                                        'primary_color',
                                                        e.target.value,
                                                    )
                                                }
                                                className="flex-1"
                                            />
                                        </div>
                                    </div>

                                    {/* Border Radius */}
                                    <div className="space-y-3">
                                        <Label>Border Radius</Label>
                                        <div className="grid grid-cols-5 gap-2">
                                            {borderRadiusOptions.map(
                                                (option) => (
                                                    <Button
                                                        key={option.value}
                                                        type="button"
                                                        variant={
                                                            form.border_radius ===
                                                            option.value
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        size="sm"
                                                        onClick={() =>
                                                            update(
                                                                'border_radius',
                                                                option.value,
                                                            )
                                                        }
                                                        className="flex flex-col gap-1 py-3"
                                                    >
                                                        <div
                                                            className="h-6 w-6 border-2 border-current"
                                                            style={{
                                                                borderRadius:
                                                                    option.value,
                                                            }}
                                                        />
                                                        <span className="text-sm">
                                                            {option.name}
                                                        </span>
                                                    </Button>
                                                ),
                                            )}
                                        </div>
                                    </div>

                                    {/* Auth Card Background Presets */}
                                    <div className="space-y-3">
                                        <Label>Auth Card Background</Label>
                                        <div className="space-y-2">
                                            <p className="text-sm text-muted-foreground">
                                                Light theme presets
                                            </p>
                                            <div className="grid grid-cols-5 gap-2">
                                                {presetColors.card_light.map(
                                                    (preset) => (
                                                        <ColorPresetButton
                                                            key={preset.color}
                                                            color={preset.color}
                                                            name={preset.name}
                                                            isSelected={
                                                                form.auth_card_bg ===
                                                                preset.color
                                                            }
                                                            onClick={() =>
                                                                update(
                                                                    'auth_card_bg',
                                                                    preset.color,
                                                                )
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-sm text-muted-foreground">
                                                Dark theme presets
                                            </p>
                                            <div className="grid grid-cols-5 gap-2">
                                                {presetColors.card_dark.map(
                                                    (preset) => (
                                                        <ColorPresetButton
                                                            key={preset.color}
                                                            color={preset.color}
                                                            name={preset.name}
                                                            isSelected={
                                                                form.auth_card_bg ===
                                                                preset.color
                                                            }
                                                            onClick={() =>
                                                                update(
                                                                    'auth_card_bg',
                                                                    preset.color,
                                                                )
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            <Input
                                                type="color"
                                                value={
                                                    form.auth_card_bg ||
                                                    '#ffffff'
                                                }
                                                onChange={(e) =>
                                                    update(
                                                        'auth_card_bg',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 w-20 cursor-pointer"
                                            />
                                            <Input
                                                type="text"
                                                placeholder="#ffffff or rgb(255,255,255)"
                                                value={form.auth_card_bg || ''}
                                                onChange={(e) =>
                                                    update(
                                                        'auth_card_bg',
                                                        e.target.value,
                                                    )
                                                }
                                                className="flex-1"
                                            />
                                        </div>
                                    </div>

                                    {/* Auth Background */}
                                    <div className="space-y-3">
                                        <Label>Auth Page Background</Label>
                                        <div className="space-y-2">
                                            <p className="text-sm text-muted-foreground">
                                                Solid colors
                                            </p>
                                            <div className="grid grid-cols-4 gap-2">
                                                {presetColors.backgrounds.map(
                                                    (preset) => (
                                                        <ColorPresetButton
                                                            key={preset.color}
                                                            color={preset.color}
                                                            name={preset.name}
                                                            isSelected={
                                                                form.auth_bg ===
                                                                preset.color
                                                            }
                                                            onClick={() =>
                                                                update(
                                                                    'auth_bg',
                                                                    preset.color,
                                                                )
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <p className="text-sm text-muted-foreground">
                                                Gradients
                                            </p>
                                            <div className="grid grid-cols-4 gap-2">
                                                {presetColors.gradients.map(
                                                    (preset) => (
                                                        <ColorPresetButton
                                                            key={preset.color}
                                                            color={preset.color}
                                                            name={preset.name}
                                                            isSelected={
                                                                form.auth_bg ===
                                                                preset.color
                                                            }
                                                            onClick={() =>
                                                                update(
                                                                    'auth_bg',
                                                                    preset.color,
                                                                )
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            <Input
                                                type="color"
                                                value={
                                                    form.auth_bg || '#f3f4f6'
                                                }
                                                onChange={(e) =>
                                                    update(
                                                        'auth_bg',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-10 w-20 cursor-pointer"
                                            />
                                            <Input
                                                type="text"
                                                placeholder="#f3f4f6 or linear-gradient(...)"
                                                value={form.auth_bg || ''}
                                                onChange={(e) =>
                                                    update(
                                                        'auth_bg',
                                                        e.target.value,
                                                    )
                                                }
                                                className="flex-1"
                                            />
                                        </div>
                                    </div>

                                    {/* Save Button with Confirmation */}
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button
                                                className="w-full"
                                                disabled={isSubmitting}
                                            >
                                                {isSubmitting
                                                    ? 'Saving...'
                                                    : 'Save Appearance Settings'}
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>
                                                    Save appearance settings?
                                                </AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    This will apply the
                                                    appearance changes to all
                                                    users. Make sure you've
                                                    previewed the changes in
                                                    both light and dark modes.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>
                                                    Cancel
                                                </AlertDialogCancel>
                                                <AlertDialogAction
                                                    onClick={submit}
                                                >
                                                    Save Changes
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>

                                    {/* Preview Section */}
                                    <div className="space-y-4 border-t pt-6">
                                        <div className="flex items-center justify-between">
                                            <Label>Preview</Label>
                                            <div className="flex gap-1 rounded-md border p-1">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant={
                                                        previewMode === 'light'
                                                            ? 'default'
                                                            : 'ghost'
                                                    }
                                                    onClick={() =>
                                                        setPreviewMode('light')
                                                    }
                                                    className="gap-2"
                                                >
                                                    <Sun className="h-4 w-4" />
                                                    Light
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant={
                                                        previewMode === 'dark'
                                                            ? 'default'
                                                            : 'ghost'
                                                    }
                                                    onClick={() =>
                                                        setPreviewMode('dark')
                                                    }
                                                    className="gap-2"
                                                >
                                                    <Moon className="h-4 w-4" />
                                                    Dark
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant={
                                                        previewMode === 'both'
                                                            ? 'default'
                                                            : 'ghost'
                                                    }
                                                    onClick={() =>
                                                        setPreviewMode('both')
                                                    }
                                                >
                                                    Both
                                                </Button>
                                            </div>
                                        </div>

                                        <div className="mt-4">
                                            {previewMode === 'both' ? (
                                                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                                    <ThemePreview
                                                        settings={form}
                                                        mode="light"
                                                    />
                                                    <ThemePreview
                                                        settings={form}
                                                        mode="dark"
                                                    />
                                                </div>
                                            ) : (
                                                <ThemePreview
                                                    settings={form}
                                                    mode={previewMode}
                                                />
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Component Preview */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-4">
                                <div>
                                    <h3 className="text-lg font-semibold">
                                        Component Preview
                                    </h3>
                                    <p className="text-base text-muted-foreground">
                                        Preview of UI components with current
                                        theme
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        style={{
                                            background:
                                                form.primary_color || undefined,
                                        }}
                                    >
                                        Primary
                                    </Button>
                                    <Button variant={'secondary'}>
                                        Secondary
                                    </Button>
                                    <Button variant={'outline'}>Outline</Button>
                                    <Button variant={'ghost'}>Ghost</Button>
                                </div>
                                <Input
                                    placeholder="Input example"
                                    className="max-w-sm"
                                    style={{
                                        borderRadius:
                                            form.border_radius || undefined,
                                    }}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
