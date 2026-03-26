'use client';

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
import * as React from 'react';
import {
    Area,
    AreaChart,
    Bar,
    CartesianGrid,
    ComposedChart,
    LabelList,
    Scatter,
    XAxis,
} from 'recharts';

export interface FilterOption {
    value: string;
    label: string;
    mobileLabel?: string;
}

export interface DataConfig {
    key: string;
    label: string;
    color: string;
}

export interface ChartDataPoint {
    date: string;
    [key: string]: unknown;
}

export type ChartType = 'area' | 'bar';

interface ChartAreaInteractiveProps {
    title: string;
    description: string;
    data: ChartDataPoint[];
    dataConfigs: DataConfig[];
    filters?: FilterOption[];
    activeFilter?: string;
    onFilterChange?: (value: string) => void;
    defaultFilter?: string;
    chartType?: ChartType;
    showFilters?: boolean;
    height?: number;
    valueFormatter?: (value: number) => string;
}

export function ChartAreaInteractive({
    title,
    description,
    data,
    dataConfigs,
    filters = [],
    activeFilter = '',
    onFilterChange,
    chartType = 'area',
    showFilters = true,
    height = 250,
    valueFormatter,
}: ChartAreaInteractiveProps) {
    const isMobile = useIsMobile();

    const chartConfig = React.useMemo(() => {
        const config: ChartConfig = {};
        dataConfigs.forEach((dc) => {
            config[dc.key] = {
                label: dc.label,
                color: dc.color,
            };
        });
        return config;
    }, [dataConfigs]);

    const renderChart = () => {
        if (chartType === 'bar') {
            return (
                <ComposedChart data={data} margin={{ top: 32 }}>
                    <CartesianGrid vertical={true} />
                    <XAxis
                        dataKey="date"
                        tickLine={false}
                        axisLine={true}
                        tickMargin={8}
                        interval={0}
                        tick={{ fontSize: 14 }}
                        tickFormatter={(value: string) => {
                            const date = new Date(value);
                            if (date.getDate() === 1) {
                                return date.toLocaleDateString('en-US', {
                                    month: 'short',
                                });
                            }
                            return date.toLocaleDateString('en-US', {
                                day: 'numeric',
                                month: 'short',
                            });
                        }}
                    />
                    <ChartTooltip
                        cursor={true}
                        content={
                            <ChartTooltipContent
                                indicator="dot"
                                labelFormatter={(value) => {
                                    const dateValue =
                                        value instanceof Date ||
                                        typeof value === 'string' ||
                                        typeof value === 'number'
                                            ? value
                                            : null;

                                    if (dateValue === null) {
                                        return '';
                                    }

                                    return new Date(
                                        dateValue,
                                    ).toLocaleDateString('en-US', {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    });
                                }}
                                formatter={(value, name) => {
                                    const config = dataConfigs.find(
                                        (c) => c.key === name,
                                    );
                                    return (
                                        <div className="flex w-full items-center justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className="h-2.5 w-2.5 shrink-0 rounded-[2px]"
                                                    style={{
                                                        backgroundColor:
                                                            config?.color,
                                                    }}
                                                />
                                                <span className="text-muted-foreground">
                                                    {config?.label || name}
                                                </span>
                                            </div>
                                            <span className="font-mono font-medium text-foreground tabular-nums">
                                                {valueFormatter
                                                    ? valueFormatter(
                                                          Number(value),
                                                      )
                                                    : value}
                                            </span>
                                        </div>
                                    );
                                }}
                            />
                        }
                    />
                    {dataConfigs.map((config) => (
                        <React.Fragment key={config.key}>
                            <Bar
                                dataKey={config.key}
                                fill="#666"
                                radius={[0, 0, 0, 0]}
                                barSize={1}
                            >
                                <LabelList
                                    dataKey={config.key}
                                    position="top"
                                    offset={16}
                                    formatter={(value: React.ReactNode) =>
                                        valueFormatter && value !== undefined
                                            ? valueFormatter(Number(value))
                                            : value
                                    }
                                    fontSize={15}
                                    fontWeight="bold"
                                    fill="#000"
                                />
                            </Bar>
                            <Scatter
                                dataKey={config.key}
                                fill={config.color}
                                tooltipType="none"
                                shape={(
                                    props: React.SVGProps<SVGCircleElement> & {
                                        cx?: number;
                                        cy?: number;
                                    },
                                ) => {
                                    const { cx, cy } = props;
                                    return (
                                        <circle
                                            cx={cx}
                                            cy={cy}
                                            r={6}
                                            fill={config.color}
                                        />
                                    );
                                }}
                            />
                        </React.Fragment>
                    ))}
                </ComposedChart>
            );
        }

        // Default: Area chart
        return (
            <AreaChart data={data}>
                <defs>
                    {dataConfigs.map((config) => (
                        <linearGradient
                            key={config.key}
                            id={`fill${config.key}`}
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="5%"
                                stopColor={config.color}
                                stopOpacity={0.8}
                            />
                            <stop
                                offset="95%"
                                stopColor={config.color}
                                stopOpacity={0.1}
                            />
                        </linearGradient>
                    ))}
                </defs>
                <CartesianGrid vertical={true} />
                <XAxis
                    dataKey="date"
                    tickLine={true}
                    axisLine={false}
                    tickMargin={8}
                    minTickGap={32}
                    interval="preserveStartEnd"
                    tickFormatter={(value: string) => {
                        const date = new Date(value);
                        if (date.getDate() === 1) {
                            return date.toLocaleDateString('en-US', {
                                month: 'short',
                            });
                        }
                        return date.toLocaleDateString('en-US', {
                            day: 'numeric',
                            month: 'short',
                        });
                    }}
                />
                <ChartTooltip
                    cursor={true}
                    defaultIndex={isMobile ? -1 : 10}
                    content={
                        <ChartTooltipContent
                            labelFormatter={(value) => {
                                const dateValue =
                                    value instanceof Date ||
                                    typeof value === 'string' ||
                                    typeof value === 'number'
                                        ? value
                                        : null;

                                if (dateValue === null) {
                                    return '';
                                }

                                return new Date(dateValue).toLocaleDateString(
                                    'en-US',
                                    {
                                        day: 'numeric',
                                        month: 'short',
                                        year: 'numeric',
                                    },
                                );
                            }}
                            indicator="dot"
                        />
                    }
                />
                {dataConfigs.map((config) => (
                    <Area
                        key={config.key}
                        dataKey={config.key}
                        type="natural"
                        fill={`url(#fill${config.key})`}
                        stroke={config.color}
                    />
                ))}
            </AreaChart>
        );
    };

    return (
        <Card className="@container/card">
            <CardHeader>
                <CardTitle className="text-lg">{title}</CardTitle>
                <CardDescription>
                    <span className="hidden @[540px]/card:block">
                        {description}
                    </span>
                    <span className="@[540px]/card:hidden">{description}</span>
                </CardDescription>
                {showFilters && filters.length > 0 && onFilterChange && (
                    <CardAction>
                        <ToggleGroup
                            type="single"
                            value={activeFilter}
                            onValueChange={(value) => {
                                if (value) onFilterChange(value);
                            }}
                            variant="outline"
                            className="hidden *:data-[slot=toggle-group-item]:!px-4 @[767px]/card:flex"
                        >
                            {filters.map((filter) => (
                                <ToggleGroupItem
                                    key={filter.value}
                                    value={filter.value}
                                    aria-label={filter.label}
                                    className="@xl/card:min-w-28"
                                >
                                    <span className="hidden @xl/card:inline">
                                        {filter.label}
                                    </span>
                                    <span className="@xl/card:hidden">
                                        {filter.mobileLabel || filter.label}
                                    </span>
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <Select
                            value={activeFilter}
                            onValueChange={onFilterChange}
                        >
                            <SelectTrigger
                                className="w-[160px] rounded-lg @[767px]/card:hidden"
                                aria-label="Select a value"
                            >
                                <SelectValue placeholder="Select filter" />
                            </SelectTrigger>
                            <SelectContent className="rounded-xl">
                                {filters.map((filter) => (
                                    <SelectItem
                                        key={filter.value}
                                        value={filter.value}
                                        className="rounded-lg"
                                    >
                                        {filter.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </CardAction>
                )}
            </CardHeader>
            <CardContent className="px-2 pt-2 sm:px-4 sm:pt-4">
                <ChartContainer
                    config={chartConfig}
                    className={`aspect-auto w-full`}
                    style={{ height: `${height}px`, minHeight: `${height}px` }}
                >
                    {renderChart()}
                </ChartContainer>
            </CardContent>
        </Card>
    );
}
