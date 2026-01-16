import { formatCurrency } from '@/lib/utils';
import { forwardRef } from 'react';
import { ScheduleSchema } from '../schema';

interface Props {
    schedule: ScheduleSchema;
}

const SchedulePreview = forwardRef<HTMLDivElement, Props>(
    ({ schedule }, ref) => {
        return (
            <div
                ref={ref}
                className="w-[800px] bg-white p-8 text-gray-900"
                style={{ fontFamily: 'Arial, sans-serif' }}
            >
                {/* Header */}
                <div className="mb-4 flex items-center justify-between">
                    <div className="flex-shrink-0">
                        <img
                            src="/logo_agency.png"
                            alt="Urban Care Logo"
                            className="h-[80px] w-64 object-contain"
                        />
                    </div>
                    <div className="text-right text-xs leading-snug">
                        <p className="mb-1 text-sm font-bold">
                            Urban Care Employment Agency
                        </p>
                        <p>931 Yishun Central 1</p>
                        <p>#01-109, Singapore 760931</p>
                        <div className="mt-1 font-bold">
                            {/* <p>REGISTRATION NO. R25128539</p> */}
                            <p>LICENSE NO. 25C2708</p>
                        </div>
                    </div>
                </div>

                {/* Title Bar */}
                <div
                    style={{ backgroundColor: '#40A09DD4', fontSize: '14px' }}
                    className="mb-2 p-1 text-center font-bold tracking-wide text-white"
                >
                    SCHEDULE OF SALARY PAYMENT AND LOAN REPAYMENT
                </div>

                {/* Info Section */}
                <div className="mb-2 grid grid-cols-2 gap-2">
                    <table
                        className="w-full border-collapse"
                        style={{ fontSize: '12px' }}
                    >
                        <tbody>
                            <tr>
                                <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                    Name of Employer
                                </td>
                                <td className="border border-gray-900 p-1">
                                    {schedule.quotation?.customer?.user?.name ||
                                        '-'}
                                </td>
                            </tr>
                            <tr>
                                <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                    Name of MDW
                                </td>
                                <td className="border border-gray-900 p-1">
                                    {schedule.quotation?.maid?.name || '-'}
                                </td>
                            </tr>
                            <tr>
                                <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                    Commencement Date
                                </td>
                                <td className="border border-gray-900 p-1">
                                    {schedule.quotation?.order?.handover_date ||
                                        '-'}
                                </td>
                            </tr>
                            <tr>
                                <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                    Rest Day of the week
                                </td>
                                <td className="border border-gray-900 p-1">
                                    {schedule.rest_day_of_the_week || '-'}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <table
                        className="w-full border-collapse"
                        style={{ fontSize: '12px' }}
                    >
                        <tbody>
                            <tr>
                                <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                    Order Number
                                </td>
                                <td className="border border-gray-900 p-1">
                                    {schedule.quotation?.order?.order_number ||
                                        '-'}
                                </td>
                            </tr>
                            <tr>
                                <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                    Passport No
                                </td>
                                <td className="border border-gray-900 p-1">
                                    {schedule.quotation?.maid
                                        ?.passport_number || '-'}
                                </td>
                            </tr>
                            <tr>
                                <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                    Total Placement Fee
                                </td>
                                <td className="border border-gray-900 p-1">
                                    {schedule.loan_amount
                                        ? `${formatCurrency(schedule.loan_amount)}`
                                        : '$0.00'}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {/* Small Info Boxes */}
                <div className="mb-2 grid grid-cols-2 gap-2">
                    {/* Left Side */}
                    <div className="flex flex-col gap-2">
                        <table
                            className="border-collapse"
                            style={{ fontSize: '12px' }}
                        >
                            <tbody>
                                <tr>
                                    <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                        Monthly Salary
                                    </td>
                                    <td className="border border-gray-900 p-1">
                                        {schedule.monthly_salary
                                            ? `${formatCurrency(schedule.monthly_salary)}`
                                            : '$0.00'}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                        Compensation Off in Lieu
                                    </td>
                                    <td className="border border-gray-900 p-1">
                                        {schedule.compensation_off_in_lieu
                                            ? `${formatCurrency(schedule.compensation_off_in_lieu)}`
                                            : '$0.00'}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    {/* Right Side */}
                    <div className="flex flex-col gap-2">
                        <table
                            className="border-collapse"
                            style={{ fontSize: '12px' }}
                        >
                            <tbody>
                                <tr>
                                    <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                        Loan Duration (Mth)
                                    </td>
                                    <td className="border border-gray-900 p-1">
                                        {schedule.loan_duration_months || '0'}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="w-2/5 border border-gray-900 bg-gray-100 p-1 font-bold">
                                        No. of rest day per month
                                    </td>
                                    <td className="border border-gray-900 p-1">
                                        {schedule.rest_days_per_month || '0'}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Schedule Table */}
                <table
                    className="w-full border-collapse text-center"
                    style={{ fontSize: '12px' }}
                >
                    <thead>
                        <tr className="bg-gray-100">
                            <th
                                rowSpan={2}
                                className="border border-gray-900 p-1 font-bold"
                            >
                                No.
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Day
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Mth/Year
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Basic Salary
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Off Day
                                <br />
                                Compensation
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Monthly Loan
                                <br />
                                Repayment
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Total amount
                                <br />
                                received by MDW
                            </th>
                            <th
                                rowSpan={2}
                                className="border border-gray-900 p-1 font-bold"
                            >
                                Employer
                                <br />
                                Signature
                            </th>
                            <th
                                rowSpan={2}
                                className="border border-gray-900 p-1 font-bold"
                            >
                                MDW
                                <br />
                                Signature
                            </th>
                        </tr>
                        <tr className="bg-gray-100">
                            <th className="border border-gray-900 p-1 font-bold">
                                Hari
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Bln/Tahun
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Gaji
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Gaji Libur
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Hutang
                            </th>
                            <th className="border border-gray-900 p-1 font-bold">
                                Uang Saku/Gaji
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {(schedule.breakdown || [])
                            .slice(0, 24)
                            .map((row, index) => {
                                return (
                                    <tr key={index}>
                                        <td className="border border-gray-900 px-1 py-2">
                                            {row.month}
                                        </td>
                                        <td className="border border-gray-900 px-1 py-2">
                                            {row.day || '-'}
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {row.month_name || '-'}
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {row.salary
                                                ? `${formatCurrency(row.salary)}`
                                                : '$0.00'}
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {row.compensation_off
                                                ? `${formatCurrency(row.compensation_off)}`
                                                : '$0.00'}
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {row.loan_payment
                                                ? `${formatCurrency(row.loan_payment)}`
                                                : '$0.00'}
                                        </td>
                                        <td className="border border-gray-900 p-2">
                                            {row.total_payment
                                                ? `${formatCurrency(row.total_payment)}`
                                                : '$0.00'}
                                        </td>
                                        <td className="w-24 border border-gray-900 p-2"></td>
                                        <td className="w-24 border border-gray-900 p-2"></td>
                                    </tr>
                                );
                            })}
                    </tbody>
                </table>
            </div>
        );
    },
);

SchedulePreview.displayName = 'SchedulePreview';

export default SchedulePreview;
export { SchedulePreview };
