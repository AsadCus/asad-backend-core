import type { PdfPreviewProps } from './types';

export function PdfPreview({
    titleColor,
    footerText,
    showStamp,
    showSignature,
    showSignatureStampName,
    showSignatureStampDate,
    signatureStampLayout,
    customSignatureStampLayout,
    documentType,
    companyName,
    companyPhone,
    companyEmail,
    companyAddress,
    logoPreview,
    stampPreview,
    signaturePreview,
    customStampPreview,
    customSignaturePreview,
}: PdfPreviewProps) {
    const labels = customSignatureStampLayout.labels;
    const placement = customSignatureStampLayout.placement ?? 'left_side';
    const showUnitNameDate =
        signatureStampLayout === 'custom' &&
        showSignatureStampName &&
        showSignatureStampDate;
    const fullNameText = showUnitNameDate ? labels.full_name : '';
    const dateText = showUnitNameDate ? labels.date : '';

    const isVertical = placement === 'up_side' || placement === 'down_side';
    const isStacked = placement === 'stack_each_other';
    const signatureFirst = placement === 'down_side' || placement === 'right_side';
    const imgH = isVertical ? '24px' : '32px';

    const stampEl = (preview: string | null) => {
        if (preview) {
            return <img src={preview} alt="Stamp" style={{ height: imgH, width: 'auto', objectFit: 'contain', opacity: 0.8, display: 'block' }} />;
        }
        return (
            <div style={{ height: imgH, width: '40px', display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px dashed #d1d5db', borderRadius: '4px', fontSize: '7px', color: '#0369a1' }}>
                Stamp
            </div>
        );
    };

    const sigEl = (preview: string | null) => {
        if (preview) {
            return <img src={preview} alt="Signature" style={{ height: imgH, width: 'auto', objectFit: 'contain', opacity: 0.8, display: 'block' }} />;
        }
        return (
            <div style={{ height: imgH, width: '48px', display: 'flex', alignItems: 'center', justifyContent: 'center', border: '1px dashed #d1d5db', borderRadius: '4px', fontSize: '7px', color: '#059669' }}>
                Signature
            </div>
        );
    };

    return (
        <div className="w-full overflow-hidden rounded-lg border bg-white shadow-sm">
            <div className="flex items-center justify-between border-b bg-muted/50 px-3 py-2">
                <span className="text-sm font-medium text-muted-foreground">PDF Preview</span>
                <span className="text-sm text-muted-foreground italic">Sample only</span>
            </div>

            <div className="overflow-x-auto p-4">
                <div
                    className="min-w-[320px] space-y-3"
                    style={{ fontFamily: 'Arial, sans-serif', fontSize: '9px' }}
                >
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex-shrink-0">
                            {logoPreview ? (
                                <img
                                    src={logoPreview}
                                    alt="Logo"
                                    className="h-10 w-auto object-contain"
                                />
                            ) : (
                                <div className="flex h-10 w-20 items-center justify-center rounded bg-gray-100 text-[8px] text-gray-400">
                                    Your Logo
                                </div>
                            )}
                        </div>
                        <div className="text-right text-[8px] leading-tight text-gray-600">
                            <div className="text-[9px] font-bold text-gray-800">
                                {companyName || 'Company Name'}
                            </div>
                            <div className="whitespace-pre-line">
                                {companyAddress || 'Company Address, Singapore'}
                            </div>
                            {companyPhone && <div className="mt-0.5">Tel: {companyPhone}</div>}
                            {companyEmail && <div>Email: {companyEmail}</div>}
                            <div className="mt-0.5 font-semibold">LICENCE NO. 25C2708</div>
                        </div>
                    </div>

                    <div
                        className="py-1.5 text-center text-[9px] font-bold tracking-widest text-white"
                        style={{ backgroundColor: titleColor || '#c05427' }}
                    >
                        {documentType || 'DOCUMENT'}
                    </div>

                    <div className="flex flex-col gap-2 text-[8px] sm:flex-row sm:gap-4">
                        <div className="flex-1 space-y-0.5">
                            <div className="flex">
                                <span className="w-14 font-semibold">Customer</span>
                                <span>: Sample Customer</span>
                            </div>
                            <div className="flex">
                                <span className="w-14 font-semibold">Address</span>
                                <span>: 123 Sample St</span>
                            </div>
                        </div>
                        <div className="space-y-0.5">
                            <div className="flex">
                                <span className="w-20 font-semibold">Doc Number</span>
                                <span>: DOC-2025-0001</span>
                            </div>
                            <div className="flex">
                                <span className="w-20 font-semibold">Date</span>
                                <span>: 01/01/2026</span>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-0.5 border-y border-gray-800 py-1.5 text-[8px]">
                        <div className="flex justify-between border-b border-gray-800 pb-0.5 font-semibold">
                            <span>Item Description</span>
                            <span>Amount</span>
                        </div>
                        <div className="flex justify-between">
                            <span>1. Sample Item</span>
                            <span>SGD 1,000.00</span>
                        </div>
                    </div>

                    <div className="text-right text-[9px] font-bold">Total: SGD 1,000.00</div>

                    <div className="space-y-1.5 border-t pt-2 text-[8px] text-gray-600">
                        {footerText ? (
                            <p className="leading-tight whitespace-pre-wrap">{footerText}</p>
                        ) : (
                            <p className="leading-tight text-gray-400 italic">
                                Footer text will appear here...
                            </p>
                        )}

                        {(showStamp || showSignature) && signatureStampLayout === 'default' && (
                            <div className="mt-2" style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start' }}>
                                <div style={{ display: 'flex', alignItems: 'flex-end', gap: '8px' }}>
                                    {showStamp && stampEl(stampPreview)}
                                    {showSignature && sigEl(signaturePreview)}
                                </div>
                                {(fullNameText || dateText) && (
                                    <div style={{ display: 'flex', gap: '8px', marginTop: '2px', fontSize: '7px', color: '#6b7280' }}>
                                        {fullNameText && <span>{fullNameText}</span>}
                                        {dateText && <span>{dateText}</span>}
                                    </div>
                                )}
                            </div>
                        )}

                        {(showStamp || showSignature) && signatureStampLayout === 'custom' && (
                            <div className="mt-2">
                                <div style={isStacked ? { position: 'relative', width: '80px', height: '32px' } : { display: 'flex', flexDirection: isVertical ? 'column' : 'row', alignItems: 'flex-start', gap: '8px', width: 'fit-content' }}>
                                    {signatureFirst ? (
                                        <>
                                            {showSignature && (
                                                <div style={isStacked ? { position: 'absolute', top: 0, left: 0, zIndex: 2 } : {}}>
                                                    {sigEl(customSignaturePreview)}
                                                </div>
                                            )}
                                            {showStamp && (
                                                <div style={isStacked ? { position: 'absolute', top: 0, left: 0, zIndex: 1 } : {}}>
                                                    {stampEl(customStampPreview)}
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            {showStamp && (
                                                <div style={isStacked ? { position: 'absolute', top: 0, left: 0, zIndex: 1 } : {}}>
                                                    {stampEl(customStampPreview)}
                                                </div>
                                            )}
                                            {showSignature && (
                                                <div style={isStacked ? { position: 'absolute', top: 0, left: 0, zIndex: 2 } : {}}>
                                                    {sigEl(customSignaturePreview)}
                                                </div>
                                            )}
                                        </>
                                    )}
                                </div>
                                {(fullNameText || dateText) && (
                                    <div style={{ display: 'flex', gap: '8px', marginTop: isStacked ? '4px' : '2px', fontSize: '7px', color: '#6b7280' }}>
                                        {fullNameText && <span>{fullNameText}</span>}
                                        {dateText && <span>{dateText}</span>}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
