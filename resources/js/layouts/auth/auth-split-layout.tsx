import AppLogoIcon from '@/components/app-logo-icon';
import { Card, CardContent } from '@/components/ui/card';
import { home } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    title?: string;
    description?: string;
}

interface AppearanceSetting {
    auth_bg?: string;
    auth_card_bg?: string;
    primary_color?: string;
    border_radius?: string;
}

export default function AuthSplitLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    const { name, appearance } = usePage<
        SharedData & { appearance?: AppearanceSetting }
    >().props;

    return (
        <div
            className={`flex min-h-dvh flex-col ${!appearance?.auth_bg ? 'bg-gray-200 dark:bg-gray-600' : ''}`}
            style={{
                background: appearance?.auth_bg || undefined,
            }}
        >
            <div className="grid flex-grow grid-cols-1 lg:grid-cols-[2fr_1fr]">
                <div className="relative hidden h-full flex-col justify-center p-10 pb-0 lg:flex">
                    <div className="flex flex-1 items-center justify-center">
                        <img
                            src="/logo-primary.png"
                            // src="/placeholder.svg"
                            alt="Urban Care"
                            className="block max-h-[30vh] w-auto object-contain dark:hidden"
                        />
                        <img
                            // src="/logo-light.png"
                            src="/logo-primary.png"
                            alt="Urban Care Light"
                            className="hidden max-h-[30vh] w-auto object-contain dark:block"
                        />
                    </div>
                </div>
                <div className="relative flex h-full flex-col items-center justify-center p-8 pb-0 lg:p-10 lg:pb-0">
                    <Card
                        className="w-full max-w-md py-8 md:min-w-160 lg:py-10"
                        style={{
                            background: appearance?.auth_card_bg || undefined,
                        }}
                    >
                        <Link
                            href={home()}
                            className="relative z-20 flex items-center justify-center lg:hidden"
                        >
                            <AppLogoIcon className="h-10 fill-current text-black sm:h-12" />
                        </Link>
                        <CardContent className="mx-auto flex w-full flex-col justify-center space-y-6">
                            <div className="flex flex-col items-center gap-2 text-center">
                                <h1 className="text-xl font-medium">{title}</h1>
                                <p className="text-sm text-balance text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                            {children}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div
                className="flex items-center justify-center py-4 text-center text-xs text-gray-600 dark:text-gray-300"
                style={{
                    color: appearance?.primary_color
                        ? adjustColorBrightness(appearance.primary_color, -40)
                        : undefined,
                }}
            >
                <span className="mr-2">Copyright © 2025 by "{name}"</span>
                {/* <img src="/mams-logo.png" alt={name} className="hidden w-12" /> */}
            </div>
        </div>
    );
}

// Helper function to adjust color brightness for text readability
function adjustColorBrightness(color: string, percent: number): string {
    const num = parseInt(color.replace('#', ''), 16);
    const amt = Math.round(2.55 * percent);
    const R = (num >> 16) + amt;
    const G = ((num >> 8) & 0x00ff) + amt;
    const B = (num & 0x0000ff) + amt;
    return (
        '#' +
        (
            0x1000000 +
            (R < 255 ? (R < 1 ? 0 : R) : 255) * 0x10000 +
            (G < 255 ? (G < 1 ? 0 : G) : 255) * 0x100 +
            (B < 255 ? (B < 1 ? 0 : B) : 255)
        )
            .toString(16)
            .slice(1)
    );
}
