import { formatCurrency } from '@/lib/utils';
import * as React from 'react';
import {
    ChartAreaInteractive,
    ChartDataPoint,
    DataConfig,
} from './chart-area-interactive';

interface IncomeData {
    label: string;
    date: string;
    amount: number | string;
}

interface IncomeByMonthChartProps {
    data: IncomeData[];
    fiscalYear?: string;
    isLoading?: boolean;
}

export function IncomeByMonthChart({
    data,
    // fiscalYear,
    isLoading = false,
}: IncomeByMonthChartProps) {
    const dataConfigs: DataConfig[] = [
        { key: 'amount', label: 'Income', color: 'var(--primary)' },
    ];

    const chartData: ChartDataPoint[] = React.useMemo(() => {
        return data.map((item) => ({
            date: item.date,
            amount: Number(item.amount) || 0,
        }));
    }, [data]);

    if (isLoading) {
        return (
            <div className="flex h-[300px] items-center justify-center rounded-lg border bg-card">
                <div className="text-muted-foreground">Loading...</div>
            </div>
        );
    }

    return (
        <ChartAreaInteractive
            title="Income by Month"
            description={''}
            data={chartData}
            dataConfigs={dataConfigs}
            chartType="bar"
            showFilters={false}
            height={240}
            valueFormatter={(value) => formatCurrency(value)}
        />
    );
}
