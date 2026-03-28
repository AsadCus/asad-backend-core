import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { DatePickerField } from '@/components/date-picker';
import { useMemo, useRef, useState } from 'react';
import type {
    SignatureStampLayoutConfig,
    SignatureStampPlacement,
    SignatureStampPlacementPreset,
} from './types';

interface SignatureStampLayoutSectionProps {
    customSignatureStampLayout: SignatureStampLayoutConfig;
    onCustomSignatureStampLayoutChange: (
        value: SignatureStampLayoutConfig,
    ) => void;
    onCustomSignatureDataChange: (value: string | null) => void;
    // Passed so preview can show the stamp/signature images already uploaded in Global Branding
    stampPreviewPath: string | null;
    signaturePreviewPath: string | null;
    customSignatureData: string | null;
    errors: Record<string, string | undefined>;
}

const PRESET_LAYOUTS: Record<
    'percent' | 'px',
    Record<SignatureStampPlacementPreset, { stamp: SignatureStampPlacement; signature: SignatureStampPlacement }>
> = {
    percent: {
        left_side: {
            stamp: { x: 2, y: 20, width: 24, height: 52, z: 1 },
            signature: { x: 28, y: 24, width: 28, height: 46, z: 2 },
        },
        right_side: {
            stamp: { x: 28, y: 20, width: 24, height: 52, z: 2 },
            signature: { x: 2, y: 24, width: 28, height: 46, z: 1 },
        },
        stack_each_other: {
            stamp: { x: 8, y: 18, width: 28, height: 54, z: 1 },
            signature: { x: 12, y: 22, width: 30, height: 46, z: 2 },
        },
        up_side: {
            stamp: { x: 6, y: 4, width: 26, height: 38, z: 2 },
            signature: { x: 6, y: 44, width: 30, height: 38, z: 1 },
        },
        down_side: {
            stamp: { x: 6, y: 44, width: 26, height: 38, z: 2 },
            signature: { x: 6, y: 4, width: 30, height: 38, z: 1 },
        },
    },
    px: {
        left_side: {
            stamp: { x: 8, y: 36, width: 112, height: 80, z: 1 },
            signature: { x: 126, y: 42, width: 132, height: 72, z: 2 },
        },
        right_side: {
            stamp: { x: 126, y: 36, width: 108, height: 80, z: 2 },
            signature: { x: 8, y: 42, width: 130, height: 72, z: 1 },
        },
        stack_each_other: {
            stamp: { x: 16, y: 36, width: 124, height: 84, z: 1 },
            signature: { x: 32, y: 44, width: 138, height: 72, z: 2 },
        },
        up_side: {
            stamp: { x: 8, y: 8, width: 112, height: 62, z: 2 },
            signature: { x: 8, y: 78, width: 140, height: 62, z: 1 },
        },
        down_side: {
            stamp: { x: 8, y: 78, width: 112, height: 62, z: 2 },
            signature: { x: 8, y: 8, width: 140, height: 62, z: 1 },
        },
    },
};

function applyPlacementPreset(
    layout: SignatureStampLayoutConfig,
    placement: SignatureStampPlacementPreset,
): SignatureStampLayoutConfig {
    const preset = PRESET_LAYOUTS[layout.unit][placement];

    return {
        ...layout,
        placement,
        stamp: preset.stamp,
        signature: preset.signature,
    };
}

export function SignatureStampLayoutSection({
    customSignatureStampLayout,
    onCustomSignatureStampLayoutChange,
    onCustomSignatureDataChange,
    stampPreviewPath,
    signaturePreviewPath,
    customSignatureData,
}: SignatureStampLayoutSectionProps) {
    const [isDrawing, setIsDrawing] = useState(false);
    const canvasRef = useRef<HTMLCanvasElement | null>(null);

    // Determine which image to show in the signature slot of the preview:
    // drawn data takes priority, then uploaded file path
    const signaturePreviewSrc = customSignatureData ?? signaturePreviewPath;

    const normalizedPreview = useMemo(() => {
        const toCssValue = (value: number, unit: 'percent' | 'px') =>
            unit === 'percent' ? `${value}%` : `${value}px`;

        return {
            stamp: {
                left: toCssValue(customSignatureStampLayout.stamp.x, customSignatureStampLayout.unit),
                top: toCssValue(customSignatureStampLayout.stamp.y, customSignatureStampLayout.unit),
                width: toCssValue(
                    customSignatureStampLayout.stamp.width,
                    customSignatureStampLayout.unit,
                ),
                height: toCssValue(
                    customSignatureStampLayout.stamp.height,
                    customSignatureStampLayout.unit,
                ),
                zIndex: customSignatureStampLayout.stamp.z,
            },
            signature: {
                left: toCssValue(customSignatureStampLayout.signature.x, customSignatureStampLayout.unit),
                top: toCssValue(customSignatureStampLayout.signature.y, customSignatureStampLayout.unit),
                width: toCssValue(
                    customSignatureStampLayout.signature.width,
                    customSignatureStampLayout.unit,
                ),
                height: toCssValue(
                    customSignatureStampLayout.signature.height,
                    customSignatureStampLayout.unit,
                ),
                zIndex: customSignatureStampLayout.signature.z,
            },
        };
    }, [customSignatureStampLayout]);

    const unitLabelText = [
        customSignatureStampLayout.labels.full_name,
        customSignatureStampLayout.labels.date,
    ]
        .filter((value) => value.trim() !== '')
        .join(', ');

    const drawSignatureData = () => {
        const canvas = canvasRef.current;
        if (!canvas) {
            return;
        }

        const dataUrl = canvas.toDataURL('image/png');
        onCustomSignatureDataChange(dataUrl);
    };

    const resolvePoint = (
        event:
            | React.MouseEvent<HTMLCanvasElement>
            | React.TouchEvent<HTMLCanvasElement>,
    ) => {
        const canvas = canvasRef.current;
        if (!canvas) {
            return { x: 0, y: 0 };
        }

        const rect = canvas.getBoundingClientRect();

        if ('touches' in event) {
            const touch = event.touches[0];
            return {
                x: touch.clientX - rect.left,
                y: touch.clientY - rect.top,
            };
        }

        return {
            x: event.clientX - rect.left,
            y: event.clientY - rect.top,
        };
    };

    const handleStartDraw = (
        event:
            | React.MouseEvent<HTMLCanvasElement>
            | React.TouchEvent<HTMLCanvasElement>,
    ) => {
        event.preventDefault();
        const canvas = canvasRef.current;
        if (!canvas) {
            return;
        }

        const context = canvas.getContext('2d');
        if (!context) {
            return;
        }

        const point = resolvePoint(event);
        const lineWidth = customSignatureStampLayout.signatureLineWidth ?? 2;
        context.beginPath();
        context.moveTo(point.x, point.y);
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.lineWidth = lineWidth;
        context.strokeStyle = '#111827';
        setIsDrawing(true);
    };

    const handleMoveDraw = (
        event:
            | React.MouseEvent<HTMLCanvasElement>
            | React.TouchEvent<HTMLCanvasElement>,
    ) => {
        if (!isDrawing) {
            return;
        }

        event.preventDefault();
        const canvas = canvasRef.current;
        if (!canvas) {
            return;
        }

        const context = canvas.getContext('2d');
        if (!context) {
            return;
        }

        const point = resolvePoint(event);
        context.lineTo(point.x, point.y);
        context.stroke();
    };

    const handleEndDraw = () => {
        if (!isDrawing) {
            return;
        }

        setIsDrawing(false);
        drawSignatureData();
    };

    const clearCanvas = () => {
        const canvas = canvasRef.current;
        if (!canvas) {
            return;
        }

        const context = canvas.getContext('2d');
        if (!context) {
            return;
        }

        context.clearRect(0, 0, canvas.width, canvas.height);
        onCustomSignatureDataChange(null);
    };

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:gap-12">
                {/* Left column: preview + draw signature */}
                <div className="flex flex-col gap-6">
                    {/* Live preview box */}
                    <div className="space-y-1.5">
                        <Label className="text-sm font-medium">Layout Preview</Label>
                        <div className="relative h-44 w-full overflow-hidden rounded-md border bg-muted/20">
                            <div className="absolute inset-0 border border-dashed border-muted-foreground/30" />

                            <div className="absolute left-2 top-2 rounded bg-black/70 px-2 py-0.5 text-[10px] text-white">
                                Preview Box
                            </div>

                            {/* Stamp slot */}
                            <div
                                className="absolute overflow-hidden"
                                style={normalizedPreview.stamp}
                            >
                                {stampPreviewPath ? (
                                    <img
                                        src={stampPreviewPath}
                                        alt="Stamp"
                                        className="h-full w-full object-contain"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-[10px] text-sky-700">
                                        Stamp
                                    </div>
                                )}
                            </div>

                            {unitLabelText && (
                                <div
                                    className="absolute text-center text-[10px] text-gray-700"
                                    style={{
                                        left: '5%',
                                        bottom: '2px',
                                        width: '90%',
                                        lineHeight: 1.2,
                                    }}
                                >
                                    {unitLabelText}
                                </div>
                            )}

                            {/* Signature slot */}
                            <div
                                className="absolute overflow-hidden"
                                style={normalizedPreview.signature}
                            >
                                {signaturePreviewSrc ? (
                                    <img
                                        src={signaturePreviewSrc}
                                        alt="Signature"
                                        className="h-full w-full object-contain"
                                    />
                                ) : (
                                    <div className="flex h-full w-full items-center justify-center text-[10px] text-emerald-700">
                                        Signature
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Draw Signature */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <Label className="text-base font-medium">Draw Signature</Label>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-9 px-4 text-sm"
                                onClick={clearCanvas}
                            >
                                Clear
                            </Button>
                        </div>
                        <canvas
                            ref={canvasRef}
                            width={560}
                            height={180}
                            className="w-full touch-none rounded-md border bg-white"
                            onMouseDown={handleStartDraw}
                            onMouseMove={handleMoveDraw}
                            onMouseUp={handleEndDraw}
                            onMouseLeave={handleEndDraw}
                            onTouchStart={handleStartDraw}
                            onTouchMove={handleMoveDraw}
                            onTouchEnd={handleEndDraw}
                        />
                        <p className="text-xs text-muted-foreground">
                            Sign in the canvas area. The drawing is saved automatically.
                        </p>
                    </div>
                </div>

                {/* Right column: unit, placement, name/date, line width */}
                <div className="flex flex-col gap-6">
                    {/* Unit */}
                    <div className="space-y-2">
                        <Label htmlFor="layout-unit" className="text-base font-medium">Unit</Label>
                        <select
                            id="layout-unit"
                            title="Layout unit"
                            value={customSignatureStampLayout.unit}
                            onChange={(e) =>
                                onCustomSignatureStampLayoutChange(
                                    applyPlacementPreset(
                                        {
                                            ...customSignatureStampLayout,
                                            unit: e.target.value as 'percent' | 'px',
                                        },
                                        customSignatureStampLayout.placement,
                                    ),
                                )
                            }
                            className="h-11 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        >
                            <option value="percent">percent</option>
                            <option value="px">px</option>
                        </select>
                    </div>

                    <Separator />

                    {/* Placement presets */}
                    <div className="space-y-3">
                        <Label className="text-base font-medium">Placement</Label>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            {(
                                [
                                    ['left_side', 'Left: Stamp, Right: Sign'],
                                    ['right_side', 'Right: Stamp, Left: Sign'],
                                    ['stack_each_other', 'Stack Each Other'],
                                    ['up_side', 'Up: Stamp, Down: Sign'],
                                    ['down_side', 'Down: Stamp, Up: Sign'],
                                ] as Array<[SignatureStampPlacementPreset, string]>
                            ).map(([key, label]) => (
                                <button
                                    key={key}
                                    type="button"
                                    className={`rounded-lg border px-4 py-3 text-left text-sm transition-all duration-200 ${
                                        customSignatureStampLayout.placement === key
                                            ? 'border-[#c05427] bg-[#c05427]/5 text-foreground font-medium ring-1 ring-[#c05427]'
                                            : 'border-input hover:border-muted-foreground/30 hover:bg-muted/30 text-muted-foreground'
                                    }`}
                                    onClick={() =>
                                        onCustomSignatureStampLayoutChange(
                                            applyPlacementPreset(
                                                customSignatureStampLayout,
                                                key,
                                            ),
                                        )
                                    }
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <Separator />

                    {/* Name and Date */}
                    <div className="space-y-3">
                        <Label className="text-base font-medium">Name and Date</Label>
                        <div className="space-y-4 rounded-xl border bg-card p-5 shadow-sm">
                            <div className="space-y-2">
                                <Label htmlFor="signature-stamp-full-name">Full Name</Label>
                                <Input
                                    id="signature-stamp-full-name"
                                    type="text"
                                    placeholder="e.g. John Doe"
                                    value={customSignatureStampLayout.labels.full_name}
                                    onChange={(e) =>
                                        onCustomSignatureStampLayoutChange({
                                            ...customSignatureStampLayout,
                                            labels: {
                                                ...customSignatureStampLayout.labels,
                                                full_name: e.target.value,
                                            },
                                        })
                                    }
                                    className="h-11"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="signature-date">Date</Label>
                                <DatePickerField
                                    id="signature-date"
                                    value={customSignatureStampLayout.labels.date}
                                    disabled={false}
                                    onChange={(value: string | null) =>
                                        onCustomSignatureStampLayoutChange({
                                            ...customSignatureStampLayout,
                                            labels: {
                                                ...customSignatureStampLayout.labels,
                                                date: value || '',
                                            },
                                        })
                                    }
                                />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Signature Line Width */}
                    <div className="space-y-3">
                        <Label className="text-base font-medium">Signature Line Width</Label>
                        <div className="grid grid-cols-4 gap-3">
                            {[
                                { label: 'Small', value: 1 },
                                { label: 'Medium', value: 2 },
                                { label: 'Large', value: 3.5 },
                                { label: 'XL', value: 5 },
                            ].map(({ label, value }) => (
                                <button
                                    key={value}
                                    type="button"
                                    className={`rounded-lg border px-3 py-2.5 text-sm transition-all duration-200 ${
                                        (customSignatureStampLayout.signatureLineWidth ?? 2) === value
                                            ? 'border-[#c05427] bg-[#c05427]/5 text-foreground font-medium ring-1 ring-[#c05427]'
                                            : 'border-input hover:border-muted-foreground/30 hover:bg-muted/30 text-muted-foreground'
                                    }`}
                                    onClick={() =>
                                        onCustomSignatureStampLayoutChange({
                                            ...customSignatureStampLayout,
                                            signatureLineWidth: value,
                                        })
                                    }
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Adjust the thickness of the signature line when drawing.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
