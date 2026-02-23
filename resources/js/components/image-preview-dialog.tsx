import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Download,
    RotateCw,
    ZoomIn as ZoomInIcon,
    ZoomOut,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface ImagePreviewDialogBaseProps {
    imageSrc: string;
    imageAlt: string;
    title?: string;
}

interface ControlledProps extends ImagePreviewDialogBaseProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    thumbnailSize?: never;
    rounded?: never;
    children?: never;
}

interface ThumbnailProps extends ImagePreviewDialogBaseProps {
    open?: never;
    onOpenChange?: never;
    thumbnailSize?: number;
    rounded?: string;
    children?: React.ReactNode;
}

type ImagePreviewDialogProps = ControlledProps | ThumbnailProps;

export function ImagePreviewDialog(props: ImagePreviewDialogProps) {
    const { imageSrc, imageAlt, title } = props;

    const isThumbnailMode = props.open === undefined;

    // State
    const [scale, setScale] = useState(1);
    const [rotation, setRotation] = useState(0);
    const [position, setPosition] = useState({ x: 0, y: 0 });
    const [isDragging, setIsDragging] = useState(false);
    const [dragStart, setDragStart] = useState({ x: 0, y: 0 });
    const [pinchDistance, setPinchDistance] = useState<number | null>(null);
    const [imageSize, setImageSize] = useState({ width: 0, height: 0 });
    const [interactionElement, setInteractionElement] =
        useState<HTMLDivElement | null>(null);

    const clampPosition = useCallback(
        (
            nextPosition: { x: number; y: number },
            nextScale = scale,
            nextRotation = rotation,
        ) => {
            if (!interactionElement || !imageSize.width || !imageSize.height) {
                return nextPosition;
            }

            const containerWidth = interactionElement.clientWidth;
            const containerHeight = interactionElement.clientHeight;

            if (!containerWidth || !containerHeight) {
                return nextPosition;
            }

            const fitRatio = Math.min(
                containerWidth / imageSize.width,
                containerHeight / imageSize.height,
            );

            const baseWidth = imageSize.width * fitRatio;
            const baseHeight = imageSize.height * fitRatio;

            const normalizedRotation = ((nextRotation % 360) + 360) % 360;
            const isQuarterTurn =
                normalizedRotation === 90 || normalizedRotation === 270;

            const scaledWidth =
                (isQuarterTurn ? baseHeight : baseWidth) * nextScale;
            const scaledHeight =
                (isQuarterTurn ? baseWidth : baseHeight) * nextScale;

            const maxX = Math.max(0, (scaledWidth - containerWidth) / 2);
            const maxY = Math.max(0, (scaledHeight - containerHeight) / 2);

            return {
                x: Math.min(maxX, Math.max(-maxX, nextPosition.x)),
                y: Math.min(maxY, Math.max(-maxY, nextPosition.y)),
            };
        },
        [
            imageSize.height,
            imageSize.width,
            interactionElement,
            rotation,
            scale,
        ],
    );

    // Actions
    const handleZoomIn = () => setScale((prev) => Math.min(prev + 0.25, 5));
    const handleZoomOut = () => setScale((prev) => Math.max(prev - 0.25, 0.5));
    const handleRotate = () => setRotation((prev) => (prev + 90) % 360);

    const handleReset = () => {
        setScale(1);
        setRotation(0);
        setPosition({ x: 0, y: 0 });
    };

    const handleDownload = () => {
        const link = document.createElement('a');
        link.href = imageSrc;
        link.download = imageAlt || 'image';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // Dialog close
    const handleOpenChange = (open: boolean) => {
        if (!open) handleReset();
        if (!isThumbnailMode) {
            (props as ControlledProps).onOpenChange(open);
        }
    };

    // Mouse pan
    const handleMouseDown = (e: React.MouseEvent) => {
        e.preventDefault();
        setIsDragging(true);
        setDragStart({
            x: e.clientX - position.x,
            y: e.clientY - position.y,
        });
    };

    const handleMouseMove = (e: React.MouseEvent) => {
        if (isDragging) {
            const nextPosition = {
                x: e.clientX - dragStart.x,
                y: e.clientY - dragStart.y,
            };

            setPosition(clampPosition(nextPosition));
        }
    };

    const handleMouseUp = () => setIsDragging(false);

    // Touch pan + pinch
    const handleTouchStart = (e: React.TouchEvent) => {
        if (e.touches.length === 1) {
            setIsDragging(true);
            setDragStart({
                x: e.touches[0].clientX - position.x,
                y: e.touches[0].clientY - position.y,
            });
        } else if (e.touches.length === 2) {
            const distance = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY,
            );
            setPinchDistance(distance);
        }
    };

    const handleTouchMove = (e: React.TouchEvent) => {
        if (e.touches.length === 1 && isDragging) {
            const nextPosition = {
                x: e.touches[0].clientX - dragStart.x,
                y: e.touches[0].clientY - dragStart.y,
            };

            setPosition(clampPosition(nextPosition));
        } else if (e.touches.length === 2 && pinchDistance !== null) {
            const distance = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY,
            );
            const scaleChange = distance / pinchDistance;
            setScale((prev) => Math.min(Math.max(prev * scaleChange, 0.5), 5));
            setPinchDistance(distance);
        }
    };

    const handleTouchEnd = (e: React.TouchEvent) => {
        if (e.touches.length < 2) setPinchDistance(null);
        if (e.touches.length === 0) setIsDragging(false);
    };

    // Wheel zoom (non-passive)
    useEffect(() => {
        if (!interactionElement) return;

        const handleWheel = (event: WheelEvent) => {
            if (event.cancelable) {
                event.preventDefault();
            }

            setScale((prev) => {
                const next = event.deltaY < 0 ? prev + 0.2 : prev - 0.2;
                return Math.min(Math.max(next, 0.5), 5);
            });
        };

        interactionElement.addEventListener('wheel', handleWheel, {
            passive: false,
        });

        return () => {
            interactionElement.removeEventListener('wheel', handleWheel);
        };
    }, [interactionElement]);

    useEffect(() => {
        setPosition((prev) => clampPosition(prev));
    }, [clampPosition, interactionElement, imageSize, rotation, scale]);

    // Shared content
    const dialogContent = (
        <DialogContent className="max-h-[95%] max-w-[95%]">
            <DialogHeader>
                <DialogTitle>{title || imageAlt}</DialogTitle>
                <DialogDescription>
                    Use mouse or touch gestures to zoom, rotate, and pan the
                    image.
                </DialogDescription>
            </DialogHeader>

            <div className="flex flex-col gap-2 px-4">
                <div className="flex items-center justify-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={handleZoomOut}
                        disabled={scale <= 0.5}
                    >
                        <ZoomOut className="h-4 w-4" />
                    </Button>
                    <span className="min-w-15 text-center font-medium">
                        {Math.round(scale * 100)}%
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={handleZoomIn}
                        disabled={scale >= 5}
                    >
                        <ZoomInIcon className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={handleRotate}
                    >
                        <RotateCw className="h-4 w-4" />
                    </Button>
                    <Button variant="outline" onClick={handleReset}>
                        Reset
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={handleDownload}
                    >
                        <Download className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <div
                className="relative flex h-[70vh] min-h-[320px] w-full touch-none items-center justify-center overflow-hidden rounded-md bg-muted/30"
                ref={setInteractionElement}
                onMouseDown={handleMouseDown}
                onMouseMove={handleMouseMove}
                onMouseUp={handleMouseUp}
                onMouseLeave={handleMouseUp}
                onTouchStart={handleTouchStart}
                onTouchMove={handleTouchMove}
                onTouchEnd={handleTouchEnd}
                onTouchCancel={handleTouchEnd}
                onDoubleClick={handleReset}
            >
                <img
                    src={imageSrc}
                    alt={imageAlt}
                    className="max-h-full max-w-full object-contain select-none"
                    onLoad={(event) => {
                        const { naturalWidth, naturalHeight } =
                            event.currentTarget;
                        setImageSize({
                            width: naturalWidth,
                            height: naturalHeight,
                        });
                        setPosition({ x: 0, y: 0 });
                    }}
                    style={{
                        transform: `translate(${position.x}px, ${position.y}px) scale(${scale}) rotate(${rotation}deg)`,
                        transition:
                            isDragging || pinchDistance !== null
                                ? 'none'
                                : 'transform 0.2s ease',
                        cursor: isDragging ? 'grabbing' : 'grab',
                    }}
                    draggable={false}
                />
            </div>
        </DialogContent>
    );

    // Thumbnail mode
    if (isThumbnailMode) {
        const thumbnailSize = (props as ThumbnailProps).thumbnailSize ?? 200;
        const rounded = (props as ThumbnailProps).rounded ?? 'rounded-md';
        const children = (props as ThumbnailProps).children;

        return (
            <Dialog
                onOpenChange={(open) => {
                    if (!open) handleReset();
                }}
            >
                <DialogTrigger asChild>
                    {children ?? (
                        <div className="group relative flex cursor-pointer items-center justify-center">
                            <img
                                src={imageSrc}
                                alt={imageAlt}
                                className={`${rounded} border object-cover object-[center_0%] transition-transform duration-300 dark:border-white`}
                                style={{
                                    width: `${thumbnailSize}px`,
                                    height: `${thumbnailSize}px`,
                                }}
                            />
                            <div
                                className={`absolute inset-0 hidden items-center justify-center ${rounded} bg-black/40 group-hover:flex`}
                            >
                                <ZoomInIcon className="size-8 text-white" />
                            </div>
                        </div>
                    )}
                </DialogTrigger>
                {dialogContent}
            </Dialog>
        );
    }

    // Controlled mode
    return (
        <Dialog open={props.open} onOpenChange={handleOpenChange}>
            {dialogContent}
        </Dialog>
    );
}
