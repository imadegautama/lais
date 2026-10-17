import { Bar, BarChart, Cell, XAxis } from 'recharts';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { formatDate, formatRupiah } from '@/lib/format';

type SalesPoint = { date: string; sales: number };

const config = {
    sales: { label: 'Penjualan', color: 'var(--chart-2)' },
    best: { label: 'Hari terlaris', color: 'var(--chart-1)' },
} satisfies ChartConfig;

const dayOf = (date: string) => new Date(`${date}T12:00:00+07:00`);

/**
 * Daily sales with exactly one highlighted bar: the best day. The same
 * answer is written above the chart, so the chart is never the only
 * place the number lives.
 */
export function SalesBarChart({ data }: { data: SalesPoint[] }) {
    const best = data.reduce<SalesPoint | null>(
        (top, point) => (top === null || point.sales > top.sales ? point : top),
        null,
    );
    const average =
        data.length > 0
            ? data.reduce((sum, point) => sum + point.sales, 0) / data.length
            : 0;

    if (best === null || best.sales === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3">
            <p className="text-sm">
                Paling ramai{' '}
                <strong className="font-semibold">
                    {formatDate(dayOf(best.date), 'long').replace(
                        / \d{4}$/,
                        '',
                    )}
                </strong>{' '}
                dengan{' '}
                <strong className="num font-semibold">
                    {formatRupiah(best.sales)}
                </strong>
                . Rata-rata{' '}
                <span className="num">{formatRupiah(Math.round(average))}</span>{' '}
                per hari.
            </p>
            <ChartContainer config={config} className="aspect-auto h-52 w-full">
                <BarChart
                    accessibilityLayer
                    data={data}
                    margin={{ left: 0, right: 0, top: 4 }}
                >
                    <XAxis
                        dataKey="date"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        minTickGap={18}
                        tickFormatter={(value: string) =>
                            String(dayOf(value).getDate())
                        }
                    />
                    <ChartTooltip
                        cursor={false}
                        content={
                            <ChartTooltipContent
                                hideIndicator
                                labelFormatter={(_, payload) => {
                                    const date = payload?.[0]?.payload?.date as
                                        | string
                                        | undefined;

                                    return date
                                        ? formatDate(dayOf(date), 'long')
                                        : '';
                                }}
                                formatter={(value) => (
                                    <span className="num font-semibold">
                                        {formatRupiah(Number(value))}
                                    </span>
                                )}
                            />
                        }
                    />
                    <Bar
                        dataKey="sales"
                        radius={[5, 5, 2, 2]}
                        isAnimationActive={false}
                    >
                        {data.map((point) => (
                            <Cell
                                key={point.date}
                                fill={
                                    point.date === best.date
                                        ? 'var(--color-best)'
                                        : 'var(--color-sales)'
                                }
                            />
                        ))}
                    </Bar>
                </BarChart>
            </ChartContainer>
            <p className="flex items-center gap-2 text-xs text-muted-foreground">
                <span aria-hidden className="size-2.5 rounded-sm bg-chart-1" />
                Hari terlaris
                <span
                    aria-hidden
                    className="ml-2 size-2.5 rounded-sm bg-chart-2"
                />
                Hari lain
            </p>
        </div>
    );
}
