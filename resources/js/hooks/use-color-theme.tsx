import { useCallback, useEffect, useState } from 'react';

export type ColorTheme =
    | 'default'
    | 'red'
    | 'rose'
    | 'orange'
    | 'yellow'
    | 'green'
    | 'blue'
    | 'violet';

const setCookie = (name: string, value: string, days = 365) => {
    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const applyColorTheme = (theme: ColorTheme) => {
    const root = document.documentElement;
    root.classList.remove(
        'theme-red',
        'theme-rose',
        'theme-orange',
        'theme-yellow',
        'theme-green',
        'theme-blue',
        'theme-violet',
    );

    if (theme !== 'default') {
        root.classList.add(`theme-${theme}`);
    }
};

export function initializeColorTheme() {
    try {
        const saved = localStorage.getItem('color-theme') as ColorTheme | null;
        const theme = saved || 'default';
        applyColorTheme(theme);
    } catch {
        applyColorTheme('default');
    }
}

export function useColorTheme() {
    const [colorTheme, setColorTheme] = useState<ColorTheme>('default');

    const updateColorTheme = useCallback((theme: ColorTheme) => {
        setColorTheme(theme);
        localStorage.setItem('color-theme', theme);
        setCookie('color-theme', theme);
        applyColorTheme(theme);
    }, []);

    useEffect(() => {
        const saved = localStorage.getItem('color-theme') as ColorTheme | null;
        applyColorTheme(saved || 'default');
        setColorTheme(saved || 'default');
    }, []);

    return { colorTheme, updateColorTheme } as const;
}
