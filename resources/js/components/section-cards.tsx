import {
    // IconTrendingDown,
    IconTrendingUp,
} from '@tabler/icons-react';

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface SectionCardsProps {
    widgets?: { title: string; value: string | number }[];
}

export function SectionCards({ widgets }: SectionCardsProps) {
    // Default widgets jika tidak ada data
    const defaultWidgets = [
        { title: 'Total Customers', value: 0 },
        { title: 'Active Maids', value: 0 },
        { title: 'Total Orders', value: 0 },
        { title: 'Total Revenue', value: '$0' },
    ];

    const displayWidgets = widgets || defaultWidgets;

    return (
        <div className="grid grid-cols-1 gap-4 *:data-[slot=card]:bg-gradient-to-t *:data-[slot=card]:from-primary/5 *:data-[slot=card]:to-card *:data-[slot=card]:shadow-xs @xl/main:grid-cols-2 @5xl/main:grid-cols-4 dark:*:data-[slot=card]:bg-card">
            {displayWidgets.map((widget, index) => (
                <Card key={index} className="@container/card">
                    <CardHeader>
                        <CardDescription>{widget.title}</CardDescription>
                        <CardTitle className="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">
                            {widget.value}
                        </CardTitle>
                        <CardAction>
                            <Badge variant="outline">
                                <IconTrendingUp />
                                +0%
                            </Badge>
                        </CardAction>
                    </CardHeader>
                    <CardFooter className="flex-col items-start gap-1.5 text-sm">
                        <div className="line-clamp-1 flex gap-2 font-medium">
                            Current data <IconTrendingUp className="size-4" />
                        </div>
                        <div className="text-muted-foreground">
                            Real-time statistics
                        </div>
                    </CardFooter>
                </Card>
            ))}
        </div>
    );
}
