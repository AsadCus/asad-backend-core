import { formatCurrency } from '@/lib/utils';
import { forwardRef } from 'react';
import { AgreementSchema } from '../schema';

interface BrandingData {
    company_name: string;
    company_address: string;
    company_phone?: string;
    company_email?: string;
    logo_url?: string;
    brand_color?: string;
    module_templates?: {
        agreements?: {
            footer_text?: string;
            show_stamp?: boolean;
            show_signature?: boolean;
        };
    };
}

interface Props {
    agreement: AgreementSchema;
    branding?: BrandingData | null;
}

const AgreementPreview = forwardRef<HTMLDivElement, Props>(
    ({ agreement, branding }, ref) => {
        // Use branding data with fallbacks
        const companyName = branding?.company_name || 'Urban Care Employment Agency';
        const companyAddress = branding?.company_address || '931 Yishun Central 1\n#01-109, Singapore 760931';
        const titleColor = branding?.brand_color || '#c05427';
        const logoUrl = branding?.logo_url ?? '/logo_agency.png'; // Use ?? to handle empty strings
        const companyPhone = branding?.company_phone || '';
        const companyEmail = branding?.company_email || '';
        const invoices = agreement.placement_fee_invoices
            ? agreement.placement_fee_invoices
            : [];

        return (
            <div
                ref={ref}
                className="w-[800px] bg-white p-8 text-sm text-gray-900"
                style={{ fontFamily: 'Arial, sans-serif' }}
            >
                {/* Header */}
                <div className="mb-4 flex items-center justify-between">
                    <div className="flex-shrink-0">
                        <img
                            src={logoUrl}
                            alt="Company Logo"
                            className="h-[90px] w-64 object-contain"
                        />
                    </div>
                    <div className="text-right text-sm leading-snug">
                        <p className="mb-1 text-base font-bold">
                            {companyName}
                        </p>
                        {companyAddress.split('\n').map((line, idx) => (
                            <p key={idx}>{line}</p>
                        ))}
                        {(companyPhone || companyEmail) && (
                            <div className="mt-1">
                                {companyPhone && <p>Tel: {companyPhone}</p>}
                                {companyEmail && <p>Email: {companyEmail}</p>}
                            </div>
                        )}
                        <div className="mt-1 font-bold">
                            {agreement.sales_registration_number && (
                                <p>
                                    REGISTRATION NO.{' '}
                                    {agreement.sales_registration_number}
                                </p>
                            )}
                            <p>LICENCE NO. 25C2708</p>
                        </div>
                    </div>
                </div>

                {/* Title Bar */}
                <div
                    style={{ backgroundColor: titleColor }}
                    className="mb-4 py-2 text-center text-sm font-bold text-white"
                >
                    Agreement for Installment Payment between Employer &
                    Employment Agency
                </div>

                {/* Info Section - Left Column */}
                <div className="mb-4 grid grid-cols-2 gap-4">
                    <div>
                        <div className="mb-4">
                            <table className="w-full border-collapse text-sm">
                                <tbody>
                                    <tr>
                                        <td className="border border-gray-900 bg-gray-200 p-2 font-bold">
                                            Name of Employer
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {agreement.customer_name || '-'}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="border border-gray-900 bg-gray-200 p-2 font-bold">
                                            NRIC/Passport No.
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {agreement.customer_nric || '-'}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Info Section - Right Column */}
                    <div>
                        <div className="mb-4">
                            <table className="w-full border-collapse text-sm">
                                <tbody>
                                    <tr>
                                        <td className="border border-gray-900 bg-gray-200 p-2 font-bold">
                                            Order Number
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {agreement.quotation?.order
                                                ?.order_number || '-'}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="border border-gray-900 bg-gray-200 p-2 font-bold">
                                            Agreement Date
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {agreement.agreement_date || '-'}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="border border-gray-900 bg-gray-200 p-2 font-bold">
                                            Total Placement Fee
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {`${formatCurrency(agreement.loan_amount)}`}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="border border-gray-900 bg-gray-200 p-2 font-bold">
                                            Monthly Salary
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {agreement.monthly_salary
                                                ? `${formatCurrency(agreement.monthly_salary)}`
                                                : '-'}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {/* Agreement Text */}
                <div className="mb-3 text-justify text-sm leading-relaxed">
                    This installment payment agreement, hereinafter known as the
                    "Agreement," is entered into on the date above, by and
                    between the employer (name as above) and Urban Care
                    Employment Agency, 931 Yishun Central 1, #01-109 Singapore
                    760931 (collectively referred to as the "Parties").
                </div>

                <div className="mb-3 text-justify text-sm leading-relaxed">
                    In consideration of the mutual promises in this agreement,
                    which receipts and sufficiency hereby are acknowledge, the
                    Parties further agree to the terms as follows:
                </div>

                <div className="mb-3 text-justify text-sm leading-relaxed">
                    The Employment Agency hereby agrees to accept the Employer
                    balance payment of the Total Placement Fee stated above of
                    the Migrant Domestic Worker (MDW) stated above.
                </div>

                {/* Payment Schedule Table */}
                <div className="mb-4">
                    <div className="mb-2 text-sm font-bold">
                        Payment Schedule
                    </div>
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr className="bg-gray-200">
                                <th className="border border-gray-900 p-2 text-center font-bold">
                                    No.
                                </th>
                                <th className="border border-gray-900 p-2 text-center font-bold">
                                    Monthly Placement Fee
                                </th>
                                <th className="border border-gray-900 p-2 text-center font-bold">
                                    Payment Due Date
                                </th>
                                <th className="border border-gray-900 p-2 text-center font-bold">
                                    Remarks
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.map((invoice, index) => {
                                const amount = `${formatCurrency(invoice.amount)}`;

                                return (
                                    <tr key={invoice.id || index}>
                                        <td className="border border-gray-900 p-2 text-center">
                                            {index + 1}
                                        </td>
                                        <td className="border border-gray-900 p-2 text-center">
                                            {amount}
                                        </td>
                                        <td className="border border-gray-900 p-2 text-center">
                                            {invoice.due_date}
                                        </td>
                                        <td className="border border-gray-900 p-2 text-center">
                                            {invoice.description || ''}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Payment Term */}
                <div className="mb-2 text-sm font-bold">Payment Term</div>
                <div className="mb-3 text-justify text-sm leading-relaxed">
                    This agreement shall commence on Agreement date stated above
                    and continue every twentieth (20th) day of each succeeding
                    month until the outstanding balance is paid in full by the
                    Employer. The payment will be paid by Paynow (UEN S3496387X)
                    or Bank Transfer to DBS Business Current Account
                    072-131956-0. The employer can pay more than but not less
                    than the agreed monthly installments.
                </div>

                {/* Consequences */}
                <div className="mb-2 text-sm font-bold">Consequences</div>
                <div className="mb-3 text-justify text-sm leading-relaxed">
                    If the Employer fails to pay on the agreed due date, the
                    Employment Agency may consider an extension of three (3)
                    business days. Please ensure payments are made promptly to
                    avoid disruption of services.
                </div>

                <div className="mb-4 text-sm">
                    <strong>Late payment interest amount:</strong>{' '}
                    {agreement.late_payment_interest_amount
                        ? `${formatCurrency(agreement.late_payment_interest_amount)}`
                        : '-'}
                </div>

                {/* Stamp Section */}
                {branding?.module_templates?.agreements?.show_stamp && branding?.stamp_url && (
                    <div className="mt-6 text-center">
                        <img
                            src={branding.stamp_url}
                            alt="Company Stamp"
                            className="mx-auto inline-block max-h-24 object-contain"
                        />
                    </div>
                )}

                {/* Signature Section */}
                <div className="mt-8 grid grid-cols-2 gap-8 text-sm">
                    <div>
                        <div className="mb-12 h-16 border-b-2 border-gray-900"></div>
                        <div className="font-bold">
                            Employer Signature / Name
                        </div>
                        <div className="text-sm">
                            {agreement.customer_name || ''}
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="mb-12 ml-auto h-16 border-b-2 border-gray-900"></div>
                        <div className="font-bold">
                            {companyName}
                        </div>
                        <div className="text-sm">Licence No. 25C2708</div>
                        
                        {/* Authorised Signature from Branding */}
                        {branding?.module_templates?.agreements?.show_signature && branding?.signature_url && (
                            <div className="mt-4">
                                <p className="mb-2 text-xs">Authorised Signature</p>
                                <img
                                    src={branding.signature_url}
                                    alt="Authorised Signature"
                                    className="mx-auto inline-block max-h-16 object-contain"
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
        );
    },
);

AgreementPreview.displayName = 'AgreementPreview';

export default AgreementPreview;
