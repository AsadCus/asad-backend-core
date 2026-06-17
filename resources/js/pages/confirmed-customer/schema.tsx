import type { OptionType } from '@/types';
import type { CustomerConfirmationFormSchema } from '../customer/schema';
import type { EnquiryDetails } from '../enquiries/schema';
import type { PackageSchema } from '../packages/schema';

export type ClientValidationErrors = Record<string, string>;

export type CustomerConfirmationPublicEditLinkType = 'one_time' | 'continuous';

export const customerConfirmationPublicEditLinkLabels: Record<
    CustomerConfirmationPublicEditLinkType,
    string
> = {
    one_time: 'One-Time Link',
    continuous: 'Continuous Link',
};

export const confirmedCustomerPublicEditLinkConfig: {
    enabledLinkTypes: CustomerConfirmationPublicEditLinkType[];
    defaultLinkType: CustomerConfirmationPublicEditLinkType;
} = {
    // Keep only one-time link visible by default. Add "continuous" to re-enable.
    enabledLinkTypes: ['one_time'],
    defaultLinkType: 'one_time',
};

export interface LinkedPackageInfo {
    id: number;
    package_number?: string | null;
    name: string;
    status?: string;
    departure_date?: string | null;
    return_date?: string | null;
    total_seats?: number | null;
    seats_left?: number | null;
    visa_type?: string | null;
    vehicle_type?: string | null;
    ticket_type?: string | null;
    remarks?: string | null;
    price_single?: number | null;
    price_double?: number | null;
    price_triple?: number | null;
    price_quad?: number | null;
    child_with_bed_price?: number | null;
    child_no_bed_price?: number | null;
    infant_price?: number | null;
    country_name?: string | null;
}

export interface CustomerConfirmationFormProps {
    mode?: 'create' | 'edit' | 'view';
    enquiryId?: number;
    enquiryType?: string;
    enquiryDetails?: EnquiryDetails;
    prefillName?: string;
    prefillEmail?: string;
    prefillContact?: string;
    prefillPackageId?: number | null;
    isPublic?: boolean;
    publicSubmitUrl?: string;
    initialData?: CustomerConfirmationFormSchema;
    packageOptions?: OptionType[];
    countryOptions?: OptionType[];
    branchOptions?: OptionType[];
    packageData?: PackageSchema;
    onSuccess?: () => void;
    onCancel?: () => void;
}

export interface CustomerConfirmationPackageOption extends OptionType {
    package_number?: string;
    status?: string | null;
    departure_date?: string | null;
    return_date?: string | null;
    seats_left?: number | null;
    is_private?: boolean;
    is_selectable?: boolean;
}

export const publicTermsAndConditions = `Terms & Conditions

A. Payment
1. Deposit of S$500 per person is to be paid upon registration for the Umrah / Tour packages and full payment to be made 2 months prior to departure date.

2. Deposit of S$1000 or 50% of package price (whichever is higher) per person is to be paid upon registration for the Umrah / Tour packages during peak period.

3. Payments can be made by Cash, Cheque, Credit Card, NETS, Bank Transfer and PayNow. Official receipts will be issued after every payment.
     - Credit Card payment (service charge of 3% will be imposed)
     - NETS payment (service charge of 0.8% will be imposed)
     - PayNow (UEN 202333370R)

4. Unutilized services such as city tour, transportation, meals etc., will NOT be refunded.

5. If the minimum room size is not achieved for the requested package, Jemaah is required to pay for the package price difference.

6. Departures are subject to full payment, failing which the cancellation fee will be charged according to rules and conditions contained herein.

B. Cancellation
*** to confirm

C. Exclusion of Responsibility
Karva Travel Group Pte. Ltd. shall not be responsible for any accidents / incidents including losses / lateness of transfers, natural disasters including floods, fire, riots, war, storms, typhoons, earthquakes, tsunami or any other tragedies encountered during journey to and fro which involves bodily harm / damage of properties.

Pilgrims / Clients are not allowed to take any legal actions under any circumstances for any claims, damages, losses, injuries or whatsoever reasons Karva Travel Group Pte. Ltd. including:
    - From any matter that may arise.
    - From the cause of the said accidents / incidents in any respect whatsoever.

D. Changes
Karva Travel Group Pte. Ltd. is entitled under any circumstances to change price or schedule without giving prior notice.

E. Umrah Visa Application
Umrah Visa Application is subject to approval by the Saudi Foreign Ministry / Ministry of Home Affairs / Saudi Embassy in Singapore. All unsuccessful visa applications will be charged with cancellation fees. (See B)

F. Insurance
*
(1) As a licensing condition of the Singapore Tourism Board, we, Karva Travel Group Pte. Ltd., are required to inform you, the client (the applicant) to consider purchasing travel insurance - (a) Against any failure or disruption in our provision of the travel product arising out of any insolvency on our part; and (b) In favour of all travelers for whom payment or deposit is to be made for.
(2). You acknowledge the risk if you do not purchase travel insurance against insolvency.
Please acknowledge the accuracy of this form, below (under Section - G Declaration)
I decline to be insured for the journey.
I will arrange my travel insurance on my own accord.

G. Declaration
*
I certify that all information contained in this form is true and correct to the best of my knowledge and realize that false information or omission may lead to dismissal from the offered package agreement. I have understood the above terms and conditions and I agree to sign up for the above package.
I agree to take Emergency & Medical Assistance (EMA) - (Valid for Umrah Only)
I have read and agree to all the terms and conditions above.`;
