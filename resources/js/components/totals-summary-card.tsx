import { cn, formatCurrency } from '@/lib/utils';

export interface TotalsSummaryRow {
    key: string;
    label: string;
    amount: number;
    labelClassName?: string;
    amountClassName?: string;
}

interface TotalsSummaryCardProps {
    subtotalAmount: number;
    rows?: TotalsSummaryRow[];
    grandTotalAmount: number;
    showExtensionTotal?: boolean;
    extensionTotalAmount?: number;
    className?: string;
}

export default function TotalsSummaryCard({
    subtotalAmount,
    rows = [],
    grandTotalAmount,
    showExtensionTotal = false,
    extensionTotalAmount = 0,
    className,
}: TotalsSummaryCardProps) {
    return (
        <div
            className={cn(
                'ml-auto w-full rounded-md border p-4 md:w-1/3',
                className,
            )}
        >
            <table className="w-full table-fixed text-base">
                <tbody className="[&>tr>td]:py-1.5">
                    <tr>
                        <td className="w-2/3 text-right font-semibold">
                            Sub Total
                        </td>
                        <td className="w-1/3 text-right font-medium">
                            {formatCurrency(subtotalAmount)}
                        </td>
                    </tr>

                    {rows.map((row) => (
                        <tr key={row.key}>
                            <td
                                className={cn('text-right', row.labelClassName)}
                            >
                                {row.label}
                            </td>
                            <td
                                className={cn(
                                    'text-right',
                                    row.amountClassName,
                                )}
                            >
                                {formatCurrency(Number(row.amount ?? 0))}
                            </td>
                        </tr>
                    ))}

                    {showExtensionTotal && (
                        <tr>
                            <td className="text-right font-semibold">
                                Extension Total
                            </td>
                            <td className="text-right font-medium">
                                {formatCurrency(extensionTotalAmount)}
                            </td>
                        </tr>
                    )}

                    <tr>
                        <td className="border-t pt-2 text-right text-base font-bold">
                            Grand Total
                        </td>
                        <td className="border-t pt-2 text-right text-lg font-bold text-primary">
                            {formatCurrency(grandTotalAmount)}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    );
}
