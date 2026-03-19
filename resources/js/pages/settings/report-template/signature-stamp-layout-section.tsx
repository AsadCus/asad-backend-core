import { DatePickerField } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useMemo, useRef, useState } from 'react';
import type {
    FileUploadFieldProps,
    SignatureStampLayoutConfig,
    SignatureStampPlacement,
    SignatureStampPlacementPreset,
} from './types';

interface SignatureStampLayoutSectionProps {
    signatureStampLayout: 'default' | 'custom';
    onSignatureStampLayoutChange: (value: 'default' | 'custom') => void;
    customSignatureStampLayout: SignatureStampLayoutConfig;
    onCustomSignatureStampLayoutChange: (
        value: SignatureStampLayoutConfig,
    ) => void;
    onCustomSignatureDataChange: (value: string | null) => void;
    customStampPreview: string | null;
    customSignaturePreview: string | null;
    customStampPreviewFileName: string | null;
    customSignaturePreviewFileName: string | null;
    initialCustomStampPreview: string | null;
    initialCustomSignaturePreview: string | null;
    initialCustomStampPreviewFileName: string | null;
    initialCustomSignaturePreviewFileName: string | null;
    initialCustomStampDatabasePath: string | null;
    initialCustomSignatureDatabasePath: string | null;
    makeFileHandler: (
        field:
            | 'logo_file'
            | 'stamp_file'
            | 'signature_file'
            | 'custom_stamp_file'
            | 'custom_signature_file',
        setPreview: (v: string | null) => void,
        setPreviewFileName: (v: string | null) => void,
    ) => (e: React.ChangeEvent<HTMLInputElement>) => void;
    makeClearHandler: (
        field:
            | 'logo_file'
            | 'stamp_file'
            | 'signature_file'
            | 'custom_stamp_file'
            | 'custom_signature_file',
        setPreview: (v: string | null) => void,
        existingPreview: string | null,
        setPreviewFileName: (v: string | null) => void,
        existingFileName: string | null,
        pathKey:
            | 'logo_path'
            | 'stamp_path'
            | 'signature_path'
            | 'custom_stamp_path'
            | 'custom_signature_path',
        hasDatabaseFile: boolean,
    ) => () => void;
    setCustomStampPreview: (v: string | null) => void;
    setCustomSignaturePreview: (v: string | null) => void;
    setCustomStampPreviewFileName: (v: string | null) => void;
    setCustomSignaturePreviewFileName: (v: string | null) => void;
    FileUploadField: React.ComponentType<FileUploadFieldProps>;
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
    signatureStampLayout,
    onSignatureStampLayoutChange,
    customSignatureStampLayout,
    onCustomSignatureStampLayoutChange,
    onCustomSignatureDataChange,
    customStampPreview,
    customSignaturePreview,
    customStampPreviewFileName,
    customSignaturePreviewFileName,
    initialCustomStampPreview,
    initialCustomSignaturePreview,
    initialCustomStampPreviewFileName,
    initialCustomSignaturePreviewFileName,
    initialCustomStampDatabasePath,
    makeFileHandler,
    makeClearHandler,
    setCustomStampPreview,
    setCustomSignaturePreview,
    setCustomStampPreviewFileName,
    setCustomSignaturePreviewFileName,
    FileUploadField,
}: SignatureStampLayoutSectionProps) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [isDrawing, setIsDrawing] = useState(false);
    const canvasRef = useRef<HTMLCanvasElement | null>(null);

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

        const data = canvas.toDataURL('image/png');
        onCustomSignatureDataChange(data);
        setCustomSignaturePreview(data);
        setCustomSignaturePreviewFileName('drawn-signature.png');
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
        setCustomSignaturePreview(initialCustomSignaturePreview);
        setCustomSignaturePreviewFileName(initialCustomSignaturePreviewFileName);
    };

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div>
                <p className="text-base font-semibold">Signature and Stamp Layout</p>
                <p className="text-sm text-muted-foreground">
                    Choose default placement or configure a custom area.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    className={`rounded-md border px-4 py-3 text-left transition-colors ${
                        signatureStampLayout === 'default'
                            ? 'border-primary bg-primary/5'
                            : 'hover:bg-muted/40'
                    }`}
                    onClick={() => onSignatureStampLayoutChange('default')}
                >
                    <p className="text-sm font-semibold">Default</p>
                    <p className="text-xs text-muted-foreground">
                        Left stamp and right signature
                    </p>
                </button>

                <button
                    type="button"
                    className={`rounded-md border px-4 py-3 text-left transition-colors ${
                        signatureStampLayout === 'custom'
                            ? 'border-primary bg-primary/5'
                            : 'hover:bg-muted/40'
                    }`}
                    onClick={() => {
                        onSignatureStampLayoutChange('custom');
                        setDialogOpen(true);
                    }}
                >
                    <p className="text-sm font-semibold">Custom</p>
                    <p className="text-xs text-muted-foreground">
                        Preset-based free placement
                    </p>
                </button>
            </div>

            {signatureStampLayout === 'custom' && (
                <Button type="button" variant="outline" onClick={() => setDialogOpen(true)}>
                    Configure Custom Layout
                </Button>
            )}

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>Custom Signature and Stamp Layout</DialogTitle>
                        <DialogDescription>
                            Draw signature, upload custom stamp, choose placement, and manage shared name/date labels.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div className="space-y-4">
                            <FormField
                                label="Display Result"
                                fieldRequirementsProps={{
                                    hint: 'Preview of your custom coordinate layout',
                                }}
                            >
                                <div className="relative h-44 w-full overflow-hidden rounded-md border bg-muted/20">
                                    <div className="absolute inset-0 border border-dashed border-muted-foreground/30" />

                                    <div className="absolute left-2 top-2 rounded bg-black/70 px-2 py-0.5 text-[10px] text-white">
                                        Custom Box
                                    </div>

                                    <div
                                        className="absolute overflow-hidden"
                                        style={normalizedPreview.stamp}
                                    >
                                        {customStampPreview ? (
                                            <img
                                                src={customStampPreview}
                                                alt="Custom Stamp"
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

                                    <div
                                        className="absolute overflow-hidden"
                                        style={normalizedPreview.signature}
                                    >
                                        {customSignaturePreview ? (
                                            <img
                                                src={customSignaturePreview}
                                                alt="Custom Signature"
                                                className="h-full w-full object-contain"
                                            />
                                        ) : (
                                            <div className="flex h-full w-full items-center justify-center text-[10px] text-emerald-700">
                                                Signature
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </FormField>

                            <Separator />

                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <Label className="text-sm font-medium">Draw Signature</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
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

                            <Separator />

                            <FileUploadField
                                id="custom_stamp_file"
                                label="Custom Stamp"
                                hint="Upload a custom stamp for custom mode"
                                preview={customStampPreview}
                                previewFileName={customStampPreviewFileName}
                                previewAlt="Custom Stamp"
                                onChange={makeFileHandler(
                                    'custom_stamp_file',
                                    setCustomStampPreview,
                                    setCustomStampPreviewFileName,
                                )}
                                onClear={makeClearHandler(
                                    'custom_stamp_file',
                                    setCustomStampPreview,
                                    initialCustomStampPreview,
                                    setCustomStampPreviewFileName,
                                    initialCustomStampPreviewFileName,
                                    'custom_stamp_path',
                                    !!initialCustomStampDatabasePath,
                                )}
                            />

                            <div className="rounded-md border bg-muted/20 p-3 text-xs text-muted-foreground">
                                Current custom signature file:{' '}
                                <span className="font-medium text-foreground">
                                    {customSignaturePreviewFileName ??
                                        initialCustomSignaturePreviewFileName ??
                                        'Not set'}
                                </span>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="layout-unit">Unit</Label>
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
                                        className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                    >
                                        <option value="percent">percent</option>
                                        <option value="px">px</option>
                                    </select>
                                </div>
                            </div>

                            <Separator />

                            <div className="space-y-3">
                                <p className="text-sm font-semibold">Placement</p>
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
                                            className={`rounded-md border px-3 py-2 text-left text-sm transition-colors ${
                                                customSignatureStampLayout.placement === key
                                                    ? 'border-primary bg-primary/5'
                                                    : 'hover:bg-muted/40'
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

                            <div className="space-y-3">
                                <p className="text-sm font-semibold">Name and Date (Global)</p>
                                <div className="space-y-3 rounded-md border p-3">
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="space-y-1.5 sm:col-span-2">
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
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-1.5">
                                        <Label htmlFor="signature-date">Date</Label>
                                        <DatePickerField
                                            id="signature-date"
                                            value={customSignatureStampLayout.labels.date}
                                            disabled={false}
                                            onChange={(value) =>
                                                onCustomSignatureStampLayoutChange({
                                                    ...customSignatureStampLayout,
                                                    labels: {
                                                        ...customSignatureStampLayout.labels,
                                                        date: value,
                                                    },
                                                })
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <Label>Signature Line Width</Label>
                                    <div className="grid grid-cols-4 gap-2">
                                        {[
                                            { label: 'Small', value: 1 },
                                            { label: 'Medium', value: 2 },
                                            { label: 'Large', value: 3.5 },
                                            { label: 'XL', value: 5 },
                                        ].map(({ label, value }) => (
                                            <button
                                                key={value}
                                                type="button"
                                                className={`rounded-md border px-3 py-2 text-sm transition-colors ${
                                                    (customSignatureStampLayout.signatureLineWidth ?? 2) === value
                                                        ? 'border-primary bg-primary/5'
                                                        : 'hover:bg-muted/40'
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
                                        Adjust the thickness of the signature line
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}
