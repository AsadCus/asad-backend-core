import { cn } from '@/lib/utils';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ModuleTemplate, SignatureStampLayoutConfig } from './types';

interface LivePreviewProps {
    scaleMode: 'fit-width' | 'actual-size';
    selectedModule: string;
    brand_color: string;
    company_name: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    signature_stamp_layout: 'default' | 'custom';
    custom_signature_stamp_layout: SignatureStampLayoutConfig;
    module_templates: Record<string, ModuleTemplate>;
}

/** Read a cookie by name. */
function getCookie(name: string): string {
    const match = document.cookie.match(
        new RegExp('(?:^|; )' + name + '=([^;]*)'),
    );
    return match ? decodeURIComponent(match[1]) : '';
}

export function PdfPreview({
    scaleMode,
    selectedModule,
    brand_color,
    company_name,
    company_address,
    company_phone,
    company_email,
    signature_stamp_layout,
    custom_signature_stamp_layout,
    module_templates,
}: LivePreviewProps) {
    const [html, setHtml] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const frameRef = useRef<HTMLIFrameElement | null>(null);

    // Stable JSON strings for dependency tracking — avoids stale closure on object refs
    const customLayoutJson = useMemo(
        () => JSON.stringify(custom_signature_stamp_layout),
        [custom_signature_stamp_layout],
    );
    const fetchPreview = useCallback(() => {
        setLoading(true);
        setError(null);

        const xsrfToken = getCookie('XSRF-TOKEN');

        // Parse stable JSON string back to object for request body
        const parsedLayout = JSON.parse(
            customLayoutJson,
        ) as SignatureStampLayoutConfig;
        const allModuleTemplates = module_templates;

        fetch('/api/report-template/preview', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken,
            },
            body: JSON.stringify({
                module_key: selectedModule,
                brand_color,
                company_name,
                company_address,
                company_phone,
                company_email,
                signature_stamp_layout,
                custom_signature_stamp_layout: parsedLayout,
                module_templates: allModuleTemplates,
            }),
        })
            .then(async (res) => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then((data) => {
                setHtml(data.html ?? '');
                setError(null);
            })
            .catch(() => {
                setError('Preview is currently unavailable.');
            })
            .finally(() => {
                setLoading(false);
            });
    }, [
        selectedModule,
        brand_color,
        company_name,
        company_address,
        company_phone,
        company_email,
        signature_stamp_layout,
        customLayoutJson,
        module_templates,
    ]);

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(fetchPreview, 600);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [fetchPreview]);

    const isLandscape =
        selectedModule === 'manifest_namelist_course_items' ||
        selectedModule === 'manifest_room_check' ||
        selectedModule === 'ops_movement' ||
        selectedModule === 'ops_movement_budget' ||
        selectedModule === 'payment_summary';

    const previewFrameClass = isLandscape
        ? 'h-[860px] max-w-[1120px]'
        : 'h-[1120px] max-w-[820px]';

    const applyScale = useCallback(() => {
        const iframe = frameRef.current;
        const doc = iframe?.contentDocument;
        if (!iframe || !doc) {
            return;
        }

        const body = doc.body;
        const root = doc.documentElement;
        if (!body || !root) {
            return;
        }

        const contentWidth = Math.max(body.scrollWidth, root.scrollWidth);
        const contentHeight = Math.max(body.scrollHeight, root.scrollHeight);

        if (!contentWidth || !contentHeight) {
            return;
        }

        root.style.background = '#f3f4f6';
        root.style.padding = '16px';
        root.style.boxSizing = 'border-box';
        root.style.display = 'flex';
        root.style.justifyContent = 'center';
        root.style.overflow = 'hidden';

        body.style.transformOrigin = 'top left';
        body.style.margin = '0';
        body.style.overflow = 'hidden';

        if (scaleMode === 'fit-width') {
            const availableWidth = Math.max(320, iframe.clientWidth - 32);
            const scale = Math.max(
                0.55,
                Math.min(2, availableWidth / contentWidth),
            );
            body.style.transform = `scale(${scale})`;
            body.style.width = `${contentWidth}px`;
            iframe.style.height = `${Math.ceil(contentHeight * scale) + 24}px`;
            return;
        }

        body.style.transform = 'none';
        body.style.width = `${contentWidth}px`;
        iframe.style.height = `${Math.ceil(contentHeight) + 24}px`;
    }, [scaleMode]);

    useEffect(() => {
        if (!html) {
            return;
        }

        const handleResize = () => {
            applyScale();
        };

        window.addEventListener('resize', handleResize);
        const timer = setTimeout(applyScale, 0);

        return () => {
            window.removeEventListener('resize', handleResize);
            clearTimeout(timer);
        };
    }, [html, applyScale]);

    return (
        <div className="mx-auto w-full">
            <div
                className={cn(
                    'relative mx-auto w-full overflow-hidden rounded-lg border bg-white shadow-md',
                    scaleMode === 'fit-width'
                        ? 'h-auto max-w-none'
                        : previewFrameClass,
                )}
            >
                {loading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/85 backdrop-blur-sm">
                        <div className="flex flex-col items-center gap-3 rounded-2xl border bg-white px-6 py-5 shadow-lg">
                            <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                            <span className="text-sm font-medium text-muted-foreground">
                                Generating preview…
                            </span>
                        </div>
                    </div>
                )}

                {error && !loading && (
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-muted/10 p-6">
                        <div className="rounded-xl border-2 border-red-200 bg-red-50 p-6 text-center shadow-sm">
                            <p className="text-sm font-semibold text-red-600">
                                Preview Unavailable
                            </p>
                            <p className="mt-2 text-xs text-red-500">{error}</p>
                        </div>
                    </div>
                )}

                {html !== null && (
                    <iframe
                        ref={frameRef}
                        srcDoc={html}
                        onLoad={applyScale}
                        className={cn(
                            'w-full border-0',
                            scaleMode === 'fit-width'
                                ? 'min-h-[70vh]'
                                : 'h-full',
                        )}
                        title="PDF Preview"
                        sandbox="allow-same-origin"
                    />
                )}
            </div>
        </div>
    );
}
