'use client';

import * as React from 'react';
import { Area, AreaChart, CartesianGrid, XAxis } from 'recharts';

import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useIsMobile } from '@/hooks/use-mobile';

export const description = 'An interactive area chart';

const chartConfig = {
    visitors: {
        label: 'Visitors',
    },
    customers: {
        label: 'Customers',
        color: 'var(--primary)',
    },
    maids: {
        label: 'Maids',
        color: 'var(--primary)',
    },
} satisfies ChartConfig;

interface ChartAreaInteractiveProps {
    chartData?: {
        customers: {
            '90d': { date: string; count: number; label: string }[];
            '30d': { date: string; count: number; label: string }[];
            '7d': { date: string; count: number; label: string }[];
        };
        maids: {
            '90d': { date: string; count: number; label: string }[];
            '30d': { date: string; count: number; label: string }[];
            '7d': { date: string; count: number; label: string }[];
        };
    };
}

export function ChartAreaInteractive({ chartData }: ChartAreaInteractiveProps) {
    const isMobile = useIsMobile();
    const [timeRange, setTimeRange] = React.useState('90d');

    React.useEffect(() => {
        if (isMobile) {
            setTimeRange('7d');
        }
    }, [isMobile]);

    // Use real data based on selected time range
    const processedData = React.useMemo(() => {
        if (chartData?.customers && chartData?.maids) {
            const customerData =
                chartData.customers[
                    timeRange as keyof typeof chartData.customers
                ] || [];
            const maidData =
                chartData.maids[timeRange as keyof typeof chartData.maids] ||
                [];

            // Combine customer and maid data by date
            const dataMap = new Map();

            customerData.forEach((item) => {
                dataMap.set(item.date, {
                    date: item.date,
                    customers: item.count,
                    maids: 0,
                });
            });

            maidData.forEach((item) => {
                const existing = dataMap.get(item.date) || {
                    date: item.date,
                    customers: 0,
                    maids: 0,
                };
                existing.maids = item.count;
                dataMap.set(item.date, existing);
            });

            return Array.from(dataMap.values()).sort(
                (a, b) =>
                    new Date(a.date).getTime() - new Date(b.date).getTime(),
            );
        }

        return [];
    }, [chartData, timeRange]);

    return (
        <Card className="@container/card">
            <CardHeader>
                <CardTitle>Total Customers</CardTitle>
                <CardDescription>
                    <span className="hidden @[540px]/card:block">
                        Total for the last 3 months
                    </span>
                    <span className="@[540px]/card:hidden">Last 3 months</span>
                </CardDescription>
                <CardAction>
                    <ToggleGroup
                        type="single"
                        value={timeRange}
                        onValueChange={setTimeRange}
                        variant="outline"
                        className="hidden *:data-[slot=toggle-group-item]:!px-4 @[767px]/card:flex"
                    >
                        <ToggleGroupItem value="90d">
                            Last 3 months
                        </ToggleGroupItem>
                        <ToggleGroupItem value="30d">
                            Last 30 days
                        </ToggleGroupItem>
                        <ToggleGroupItem value="7d">
                            Last 7 days
                        </ToggleGroupItem>
                    </ToggleGroup>
                    <Select value={timeRange} onValueChange={setTimeRange}>
                        <SelectTrigger
                            className="flex w-40 **:data-[slot=select-value]:block **:data-[slot=select-value]:truncate @[767px]/card:hidden"
                            size="sm"
                            aria-label="Select a value"
                        >
                            <SelectValue placeholder="Last 3 months" />
                        </SelectTrigger>
                        <SelectContent className="rounded-xl">
                            <SelectItem value="90d" className="rounded-lg">
                                Last 3 months
                            </SelectItem>
                            <SelectItem value="30d" className="rounded-lg">
                                Last 30 days
                            </SelectItem>
                            <SelectItem value="7d" className="rounded-lg">
                                Last 7 days
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </CardAction>
            </CardHeader>
            <CardContent className="px-2 pt-4 sm:px-6 sm:pt-6">
                <ChartContainer
                    config={chartConfig}
                    className="aspect-auto h-[250px] w-full"
                >
                    <AreaChart data={processedData}>
                        <defs>
                            <linearGradient
                                id="fillCustomers"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="5%"
                                    stopColor="var(--color-customers)"
                                    stopOpacity={1.0}
                                />
                                <stop
                                    offset="95%"
                                    stopColor="var(--color-customers)"
                                    stopOpacity={0.1}
                                />
                            </linearGradient>
                            <linearGradient
                                id="fillMaids"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="5%"
                                    stopColor="var(--color-maids)"
                                    stopOpacity={0.8}
                                />
                                <stop
                                    offset="95%"
                                    stopColor="var(--color-maids)"
                                    stopOpacity={0.1}
                                />
                            </linearGradient>
                        </defs>
                        <CartesianGrid vertical={false} />
                        <XAxis
                            dataKey="date"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            minTickGap={32}
                            tickFormatter={(value) => {
                                const date = new Date(value);
                                return date.toLocaleDateString('en-US', {
                                    month: 'short',
                                    day: 'numeric',
                                });
                            }}
                        />
                        <ChartTooltip
                            cursor={false}
                            defaultIndex={isMobile ? -1 : 10}
                            content={
                                <ChartTooltipContent
                                    labelFormatter={(value) => {
                                        return new Date(
                                            value,
                                        ).toLocaleDateString('en-US', {
                                            month: 'short',
                                            day: 'numeric',
                                        });
                                    }}
                                    indicator="dot"
                                />
                            }
                        />
                        <Area
                            dataKey="maids"
                            type="natural"
                            fill="url(#fillMaids)"
                            stroke="var(--color-maids)"
                            stackId="a"
                        />
                        <Area
                            dataKey="customers"
                            type="natural"
                            fill="url(#fillCustomers)"
                            stroke="var(--color-customers)"
                            stackId="a"
                        />
                    </AreaChart>
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
