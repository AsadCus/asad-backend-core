import { useCallback, useEffect, useRef, useState } from 'react';
import type { ModuleTemplate, SignatureStampLayoutConfig } from './types';

interface LivePreviewProps {
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
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
}

export function PdfPreview({
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

    // Stable JSON strings for dependency tracking
    const customLayoutJson = JSON.stringify(custom_signature_stamp_layout);
    const moduleTemplateJson = JSON.stringify(module_templates[selectedModule]);

    const fetchPreview = useCallback(() => {
        setLoading(true);
        setError(null);

        // Use the XSRF-TOKEN cookie Laravel sets on each page
        const xsrfToken = getCookie('XSRF-TOKEN');

        fetch('/api/report-template/preview', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
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
                custom_signature_stamp_layout,
                module_templates,
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
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        selectedModule,
        brand_color,
        company_name,
        company_address,
        company_phone,
        company_email,
        signature_stamp_layout,
        customLayoutJson,
        moduleTemplateJson,
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
    const aspectRatioPadding = isLandscape ? '70.7%' : '141.4%';

    return (
        <div className="w-full overflow-hidden rounded-lg border bg-white shadow-sm transition-all duration-300">
            <div className="flex items-center justify-between border-b bg-muted/50 px-3 py-2">
                <span className="text-sm font-medium text-muted-foreground">PDF Preview</span>
                <span className="text-sm italic text-muted-foreground">
                    {loading ? 'Updating…' : (isLandscape ? 'Landscape view' : 'Portrait view')}
                </span>
            </div>

            {/* Dynamic A4 aspect ratio container */}
            <div className="relative w-full transition-all duration-300" style={{ paddingTop: aspectRatioPadding }}>
                {loading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/80">
                        <div className="flex flex-col items-center gap-2">
                            <div className="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                            <span className="text-xs text-muted-foreground">Loading preview…</span>
                        </div>
                    </div>
                )}

                {error && !loading && (
                    <div className="absolute inset-0 flex items-center justify-center bg-muted/10 p-4">
                        <p className="text-center text-sm text-red-500">{error}</p>
                    </div>
                )}

                {html !== null && (
                    <iframe
                        srcDoc={html}
                        className="absolute inset-0 h-full w-full border-0"
                        title="PDF Preview"
                        sandbox="allow-same-origin"
                        style={{ background: 'white' }}
                    />
                )}
            </div>
        </div>
    );
}
