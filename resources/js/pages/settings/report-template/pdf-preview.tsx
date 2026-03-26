import type { PdfPreviewProps } from './types';

export function PdfPreview({
    titleColor,
    footerText,
    showStamp,
    showSignature,
    documentType,
    companyName,
    companyPhone,
    companyEmail,
    companyAddress,
    logoPreview,
    stampPreview,
    signaturePreview,
}: PdfPreviewProps) {
    return (
        <div className="w-full overflow-hidden rounded-lg border bg-white shadow-sm">
            <div className="flex items-center justify-between border-b bg-muted/50 px-3 py-2">
                <span className="text-sm font-medium text-muted-foreground">
                    PDF Preview
                </span>
                <span className="text-sm text-muted-foreground italic">
                    Sample only
                </span>
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
                        {companyPhone && (
                            <div className="mt-0.5">Tel: {companyPhone}</div>
                        )}
                        {companyEmail && <div>Email: {companyEmail}</div>}
                        <div className="mt-0.5 font-semibold">
                            LICENCE NO. 25C2708
                        </div>
                    </div>
                </div>
                <div
                    className="py-1.5 text-center text-[9px] font-bold tracking-widest text-white"
                    style={{ backgroundColor: titleColor || '#40A09D' }}
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
                            <span className="w-20 font-semibold">
                                Doc Number
                            </span>
                            <span>: DOC-2025-0001</span>
                        </div>
                        <div className="flex">
                            <span className="w-20 font-semibold">Date</span>
                            <span>: 01/01/2025</span>
                        </div>
                    </div>
                </div>
                <div className="space-y-0.5 border-t border-b border-gray-800 py-1.5 text-[8px]">
                    <div className="flex justify-between border-b border-gray-800 pb-0.5 font-semibold">
                        <span>Item Description</span>
                        <span>Amount</span>
                    </div>
                    <div className="flex justify-between">
                        <span>1. Sample Item</span>
                        <span>SGD 1,000.00</span>
                    </div>
                </div>
                <div className="text-right text-[9px] font-bold">
                    Total: SGD 1,000.00
                </div>
                <div className="space-y-1.5 border-t pt-2 text-[8px] text-gray-600">
                    {footerText ? (
                        <p className="leading-tight whitespace-pre-wrap">
                            {footerText}
                        </p>
                    ) : (
                        <p className="leading-tight text-gray-400 italic">
                            Footer text will appear here...
                        </p>
                    )}
                    {(showStamp || showSignature) && (
                        <div className="mt-2 flex gap-4">
                            {showStamp &&
                                (stampPreview ? (
                                    <img
                                        src={stampPreview}
                                        alt="Stamp"
                                        className="h-8 w-auto object-contain opacity-70"
                                    />
                                ) : (
                                    <div className="flex h-8 w-14 items-center justify-center rounded border border-dashed border-gray-300 text-[7px] text-gray-400">
                                        Stamp
                                    </div>
                                ))}
                            {showSignature && (
                                <div>
                                    {signaturePreview ? (
                                        <img
                                            src={signaturePreview}
                                            alt="Signature"
                                            className="h-8 w-auto object-contain opacity-70"
                                        />
                                    ) : (
                                        <div className="flex h-8 w-16 items-center justify-center rounded border border-dashed border-gray-300 text-[7px] text-gray-400">
                                            Signature
                                        </div>
                                    )}
                                    <div className="mt-0.5 text-[7px] text-gray-400">
                                        Authorised Signature
                                    </div>
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
