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

    return (
        <div className="relative w-full overflow-hidden rounded-2xl border bg-white shadow-[0_20px_80px_rgba(15,23,42,0.14)] ring-1 ring-black/5">
            {/* Fixed-height preview area — iframe scrolls internally, no giant whitespace */}
            <div className="relative h-[78vh] w-full bg-neutral-100/60">
                {loading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/85 backdrop-blur-sm">
                        <div className="flex flex-col items-center gap-3 rounded-2xl border bg-white px-6 py-5 shadow-lg">
                            <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                            <span className="text-sm font-medium text-muted-foreground">Generating preview…</span>
                        </div>
                    </div>
                )}

                {error && !loading && (
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-muted/10 p-6">
                        <div className="rounded-xl border-2 border-red-200 bg-red-50 p-6 text-center shadow-sm">
                            <p className="text-sm font-semibold text-red-600">Preview Unavailable</p>
                            <p className="mt-2 text-xs text-red-500">{error}</p>
                        </div>
                    </div>
                )}

                {html !== null && (
                    <iframe
                        srcDoc={html}
                        className="h-full w-full border-0"
                        title="PDF Preview"
                        sandbox="allow-same-origin"
                    />
                )}
            </div>
        </div>
    );
}
