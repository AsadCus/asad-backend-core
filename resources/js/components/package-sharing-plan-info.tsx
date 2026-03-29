import { cn } from '@/lib/utils';

interface PackageSharingPlanInfoProps {
    packageName?: string | null;
    singlePrice?: number | string | null;
    doublePrice?: number | string | null;
    triplePrice?: number | string | null;
    quadPrice?: number | string | null;
    className?: string;
    packageLabel?: string;
}

function formatPrice(value?: number | string | null): string {
    return Number(value ?? 0).toFixed(2);
}

export default function PackageSharingPlanInfo({
    packageName,
    singlePrice,
    doublePrice,
    triplePrice,
    quadPrice,
    className,
    packageLabel = 'Package',
}: PackageSharingPlanInfoProps) {
    return (
        <div
            className={cn(
                'grid w-full items-center gap-3 rounded-md border p-3',
                className,
            )}
        >
            <div className="space-y-1 text-base">
                {packageName && (
                    <div className="flex items-center justify-between gap-3 border-b pb-2 font-medium">
                        <span>{packageLabel}</span>
                        <span>{packageName}</span>
                    </div>
                )}

                <div className="flex items-center justify-between gap-3">
                    <span>Single</span>
                    <span>${formatPrice(singlePrice)}</span>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <span>Double</span>
                    <span>${formatPrice(doublePrice)}</span>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <span>Triple</span>
                    <span>${formatPrice(triplePrice)}</span>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <span>Quad</span>
                    <span>${formatPrice(quadPrice)}</span>
                </div>
            </div>
        </div>
    );
}
