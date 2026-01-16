import { FormSection } from '@/components/form-section';
import QuotationItemTableForm from '@/pages/quotations/items/form';
import { QuotationItemSchema } from '@/pages/quotations/items/schema';
import React from 'react';

interface QuotationItemsSectionProps {
    items: QuotationItemSchema[];
    onChange: (items: QuotationItemSchema[]) => void;
    renderError: (path: string) => React.ReactNode;
    isView?: boolean;
    status: 'incomplete' | 'complete' | 'error';
}

export default function QuotationItemsSection({
    items,
    onChange,
    renderError,
    isView = false,
    status,
}: QuotationItemsSectionProps) {
    return (
        <FormSection
            value="quotation_items"
            title="Quotation Items"
            description="List of chargeable items for this quotation"
            status={status}
            required
        >
            <div id="section-quotation-items" className="space-y-6">
                <QuotationItemTableForm
                    items={items}
                    onChange={onChange}
                    renderError={renderError}
                    disabled={isView}
                />
            </div>
        </FormSection>
    );
}
