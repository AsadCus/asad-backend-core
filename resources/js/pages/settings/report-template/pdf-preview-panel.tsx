import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
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

    // Stable JSON strings for dependency tracking — avoids stale closure on object refs
    const customLayoutJson = useMemo(
        () => JSON.stringify(custom_signature_stamp_layout),
        [custom_signature_stamp_layout],
    );
    const moduleTemplateJson = useMemo(
        () => JSON.stringify(module_templates[selectedModule]),
        [module_templates, selectedModule],
    );

    const fetchPreview = useCallback(() => {
        setLoading(true);
        setError(null);

        const xsrfToken = getCookie('XSRF-TOKEN');

        // Parse the stable JSON strings back to objects for the request body
        const parsedLayout = JSON.parse(customLayoutJson) as SignatureStampLayoutConfig;
        const allModuleTemplates = JSON.parse(
            // We need all templates, not just the selected one
            // Re-stringify the whole map using the stable per-module json we track
            JSON.stringify(module_templates),
        ) as Record<string, ModuleTemplate>;

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

    return (
        <div className="flex w-full flex-col overflow-hidden rounded-xl border bg-white shadow-md">
            {/* Header bar */}
            <div className="flex items-center justify-between border-b bg-muted/40 px-4 py-2.5">
                <div className="flex items-center gap-2">
                    <span className="h-2 w-2 animate-pulse rounded-full bg-green-500" />
                    <span className="text-sm font-medium text-muted-foreground">Live Preview</span>
                </div>
                <span
                    className={`rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider ${
                        loading
                            ? 'bg-amber-100 text-amber-700'
                            : isLandscape
                              ? 'bg-blue-100 text-blue-700'
                              : 'bg-slate-100 text-slate-600'
                    }`}
                >
                    {loading ? 'Updating…' : isLandscape ? 'Landscape' : 'Portrait'}
                </span>
            </div>

            {/* Fixed-height preview area — iframe scrolls internally, no giant whitespace */}
            <div className="relative h-[72vh] w-full bg-muted/10">
                {loading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/90">
                        <div className="flex flex-col items-center gap-3">
                            <div className="h-8 w-8 animate-spin rounded-full border-[3px] border-primary border-t-transparent" />
                            <span className="text-sm text-muted-foreground">Generating preview…</span>
                        </div>
                    </div>
                )}

                {error && !loading && (
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-muted/10 p-6">
                        <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-center">
                            <p className="text-sm font-medium text-red-600">Preview Unavailable</p>
                            <p className="mt-1 text-xs text-red-400">{error}</p>
                        </div>
                    </div>
                )}

                {html !== null && (
                    <iframe
                        srcDoc={html}
                        className="h-full w-full border-0"
                        title="PDF Preview"
                        sandbox="allow-same-origin"
                        style={{ background: 'white' }}
                    />
                )}
            </div>
        </div>
    );
}
