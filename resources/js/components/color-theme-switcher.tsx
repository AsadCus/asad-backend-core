import { useColorTheme } from '@/hooks/use-color-theme';
import { cn } from '@/lib/utils';

const colorThemes = [
    { name: 'default', color: 'bg-[oklch(57.678%_0.15043_41.264)] dark:bg-white' },
    { name: 'red', color: 'bg-red-500' },
    { name: 'rose', color: 'bg-rose-500' },
    { name: 'orange', color: 'bg-orange-500' },
    { name: 'yellow', color: 'bg-yellow-500' },
    { name: 'green', color: 'bg-green-500' },
    { name: 'blue', color: 'bg-blue-500' },
    { name: 'violet', color: 'bg-violet-500' },
] as const;

type ColorThemeName = (typeof colorThemes)[number]['name'];

export default function ColorThemeSwitcher() {
    const { colorTheme, updateColorTheme } = useColorTheme();

    return (
        <div className="flex items-center gap-3">
            {colorThemes.map((theme) => (
                <button
                    key={theme.name}
                    onClick={() =>
                        updateColorTheme(theme.name as ColorThemeName)
                    }
                    className={cn(
                        'h-8 w-8 rounded-full border-2 border-transparent transition-all',
                        theme.color,
                        colorTheme === theme.name &&
                            'ring-2 ring-primary ring-offset-2 dark:ring-white',
                    )}
                    title={theme.name}
                />
            ))}
        </div>
    );
}
