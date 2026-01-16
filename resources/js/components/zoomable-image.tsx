import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { VisuallyHidden } from '@radix-ui/react-visually-hidden';
import { Minus, Plus, ZoomIn } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from './ui/button';

interface ZoomableImageProps {
    src: string;
    alt?: string;
    thumbnailSize?: number;
    rounded?: string;
}

export const ZoomableImage: React.FC<ZoomableImageProps> = ({
    src,
    alt = 'Image',
    thumbnailSize = 200,
    rounded = 'rounded-md',
}) => {
    const [zoom, setZoom] = useState(1);
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const [startPos, setStartPos] = useState<{ x: number; y: number } | null>(
        null,
    );
    const [startDistance, setStartDistance] = useState<number | null>(null);
    const imgRef = useRef<HTMLImageElement | null>(null);

    // Mouse events
    const handleMouseDown = (e: React.MouseEvent) => {
        e.preventDefault();
        setStartPos({ x: e.clientX - offset.x, y: e.clientY - offset.y });
    };

    const handleMouseMove = (e: React.MouseEvent) => {
        if (!startPos) return;
        setOffset({ x: e.clientX - startPos.x, y: e.clientY - startPos.y });
    };

    const handleMouseUp = () => setStartPos(null);

    // Touch events
    const handleTouchStart = (e: React.TouchEvent) => {
        if (e.touches.length === 1) {
            setStartPos({
                x: e.touches[0].clientX - offset.x,
                y: e.touches[0].clientY - offset.y,
            });
        } else if (e.touches.length === 2) {
            const distance = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY,
            );
            setStartDistance(distance);
        }
    };

    const handleTouchMove = (e: React.TouchEvent) => {
        if (e.touches.length === 1 && startPos) {
            setOffset({
                x: e.touches[0].clientX - startPos.x,
                y: e.touches[0].clientY - startPos.y,
            });
        } else if (e.touches.length === 2 && startDistance) {
            const distance = Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY,
            );
            const scaleChange = distance / startDistance;
            setZoom((z) => Math.min(Math.max(z * scaleChange, 1), 5));
            setStartDistance(distance);
        }
    };

    const handleTouchEnd = (e: React.TouchEvent) => {
        if (e.touches.length < 2) setStartDistance(null);
        if (e.touches.length === 0) setStartPos(null);
    };

    return (
        <Dialog>
            <DialogTrigger asChild>
                <div className="group relative flex cursor-pointer items-center justify-center">
                    <img
                        src={src}
                        alt={alt}
                        className={`${rounded} border object-cover object-[center_0%] transition-transform duration-300 dark:border-white`}
                        style={{
                            width: `${thumbnailSize}px`,
                            height: `${thumbnailSize}px`,
                        }}
                    />
                    <div
                        className={`absolute inset-0 hidden items-center justify-center ${rounded} bg-black/40 group-hover:flex`}
                    >
                        <ZoomIn className="size-8 text-white" />
                    </div>
                </div>
            </DialogTrigger>

            <DialogContent className="flex max-h-[90%] items-center justify-center overflow-hidden border bg-transparent p-2 dark:border-white">
                <VisuallyHidden>
                    <DialogTitle>{alt}</DialogTitle>
                    <DialogDescription>
                        Full view of the selected image
                    </DialogDescription>
                </VisuallyHidden>

                <div
                    className="relative touch-none"
                    onMouseDown={handleMouseDown}
                    onMouseMove={handleMouseMove}
                    onMouseUp={handleMouseUp}
                    onMouseLeave={handleMouseUp}
                    onTouchStart={handleTouchStart}
                    onTouchMove={handleTouchMove}
                    onTouchEnd={handleTouchEnd}
                    onTouchCancel={handleTouchEnd}
                >
                    <img
                        ref={imgRef}
                        src={src}
                        alt={alt}
                        style={{
                            transform: `scale(${zoom}) translate(${offset.x / zoom}px, ${offset.y / zoom}px)`,
                            transition:
                                startPos || startDistance
                                    ? 'none'
                                    : 'transform 0.2s ease',
                            cursor: startPos ? 'grabbing' : 'grab',
                        }}
                        className="rounded-lg object-contain"
                    />

                    {/* Zoom controls */}
                    <div className="absolute right-2 bottom-2 flex gap-2">
                        <Button
                            type="button"
                            className="rounded bg-primary px-2 py-1"
                            onClick={() => setZoom((z) => Math.min(z + 0.2, 5))}
                        >
                            <Plus className="size-3" />
                        </Button>
                        <Button
                            type="button"
                            className="rounded bg-primary px-2 py-1"
                            onClick={() => setZoom((z) => Math.max(z - 0.2, 1))}
                        >
                            <Minus className="size-3" />
                        </Button>
                        <Button
                            type="button"
                            className="rounded bg-primary px-2 py-1"
                            onClick={() => {
                                setZoom(1);
                                setOffset({ x: 0, y: 0 });
                            }}
                        >
                            Reset
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
};
