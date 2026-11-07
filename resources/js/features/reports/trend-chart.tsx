import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    ReferenceLine,
    XAxis,
} from 'recharts';
import { Money } from '@/components/app/money';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { formatDate, formatRupiah, formatRupiahCompact } from '@/lib/format';

/** The same figures as text, so the chart is never the only place they live. */
function FiguresTable({
    caption,
    rows,
}: {
    caption: string;
    rows: Array<{ label: string; value: number }>;
}) {
    return (
        <table className="sr-only">
            <caption>{caption}</caption>
            <tbody>
                {rows.map((row) => (
                    <tr key={row.label}>
                        <th scope="row">{row.label}</th>
                        <td>{formatRupiah(row.value)}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

type BarValueProps = {
    x?: number | string;
    y?: number | string;
    width?: number | string;
    height?: number | string;
    value?: unknown;
};

/** The value at the tip of a bar, on one line: Recharts' own label wraps to the bar's width. */
function BarValue({ x, y, width, height, value }: BarValueProps) {
    const amount = Number(value ?? 0);

    if (amount === 0) {
        return null;
    }

    const center = Number(x ?? 0) + Number(width ?? 0) / 2;
    const top = Number(y ?? 0);
    const bottom = top + Number(height ?? 0);

    return (
        <text
            x={center}
            y={
                amount < 0
                    ? Math.max(top, bottom) + 13
                    : Math.min(top, bottom) - 6
            }
            textAnchor="middle"
            className="fill-foreground text-xs"
        >
            {formatRupiahCompact(amount)}
        </text>
    );
}

const monthConfig = {
    value: { label: 'Laba bersih', color: 'var(--chart-2)' },
} satisfies ChartConfig;

/**
 * Months side by side with one highlighted, so the eye lands on the month the
 * sentence above names (the latest unless told otherwise). Bars below the line
 * are losses.
 */
export function MonthlyBars({
    data,
    caption,
    name = 'Laba bersih',
    highlight = data.at(-1)?.month,
}: {
    data: Array<{ month: string; label: string; value: number }>;
    caption: string;
    name?: string;
    highlight?: string;
}) {
    return (
        <div className="print:hidden">
            <FiguresTable caption={caption} rows={data} />
            <ChartContainer
                config={{ value: { ...monthConfig.value, label: name } }}
                className="aspect-auto h-48 w-full"
            >
                <BarChart
                    accessibilityLayer
                    data={data}
                    margin={{ left: 4, right: 4, top: 20, bottom: 16 }}
                >
                    <ReferenceLine y={0} stroke="var(--border)" />
                    <XAxis
                        dataKey="label"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        interval={0}
                    />
                    <ChartTooltip
                        cursor={{ fill: 'var(--secondary)' }}
                        content={
                            <ChartTooltipContent
                                hideIndicator
                                formatter={(value) => (
                                    <span className="num font-medium">
                                        {formatRupiah(Number(value))}
                                    </span>
                                )}
                            />
                        }
                    />
                    <Bar
                        dataKey="value"
                        maxBarSize={24}
                        radius={[4, 4, 0, 0]}
                        isAnimationActive={false}
                    >
                        {data.map((point) => (
                            <Cell
                                key={point.month}
                                fill={
                                    point.month === highlight
                                        ? 'var(--chart-1)'
                                        : 'var(--chart-2)'
                                }
                            />
                        ))}
                        <LabelList dataKey="value" content={BarValue} />
                    </Bar>
                </BarChart>
            </ChartContainer>
        </div>
    );
}

const balanceConfig = {
    balance: { label: 'Saldo', color: 'var(--chart-1)' },
} satisfies ChartConfig;

/** How a balance moved over the days of the range, answered in words first. */
export function BalanceArea({
    data,
    opening,
    caption,
}: {
    data: Array<{ date: string; balance: number }>;
    opening: number;
    caption: string;
}) {
    const closing = data.at(-1)?.balance ?? opening;
    const dayLabel = (date: string) =>
        formatDate(`${date}T12:00:00`).replace(/ \d{4}$/, '');

    return (
        <div className="print:hidden">
            <p className="mb-2 text-sm">
                {closing === opening ? (
                    <>
                        Saldo kembali ke <Money value={closing} size="sm" />.
                    </>
                ) : (
                    <>
                        Saldo {closing < opening ? 'turun' : 'naik'} dari{' '}
                        <Money value={opening} size="sm" /> menjadi{' '}
                        <span className="whitespace-nowrap">
                            <Money
                                value={closing}
                                size="sm"
                                className="font-semibold"
                            />
                            .
                        </span>
                    </>
                )}
            </p>
            <FiguresTable
                caption={caption}
                rows={data.map((point) => ({
                    label: dayLabel(point.date),
                    value: point.balance,
                }))}
            />
            <ChartContainer
                config={balanceConfig}
                className="aspect-auto h-44 w-full"
            >
                <AreaChart
                    accessibilityLayer
                    data={data}
                    margin={{ left: 4, right: 12, top: 8, bottom: 0 }}
                >
                    <CartesianGrid vertical={false} strokeOpacity={0.5} />
                    <XAxis
                        dataKey="date"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        minTickGap={40}
                        tickFormatter={dayLabel}
                    />
                    <ChartTooltip
                        content={
                            <ChartTooltipContent
                                labelFormatter={(value) =>
                                    dayLabel(String(value))
                                }
                                formatter={(value) => (
                                    <span className="num font-medium">
                                        {formatRupiah(Number(value))}
                                    </span>
                                )}
                            />
                        }
                    />
                    <Area
                        dataKey="balance"
                        type="monotone"
                        stroke="var(--chart-1)"
                        strokeWidth={2}
                        fill="var(--chart-1)"
                        fillOpacity={0.12}
                        isAnimationActive={false}
                    />
                </AreaChart>
            </ChartContainer>
        </div>
    );
}
