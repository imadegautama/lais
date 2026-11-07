import { Check, MessageCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ChoiceGroup } from '@/components/app/choice-group';
import { DemoDataBadge } from '@/components/app/demo-data-badge';
import { Money } from '@/components/app/money';
import { formatQty, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { DemoExample } from './examples';
import { CONFIRM, examples } from './examples';

const STOCK_SHOWN = 3;

/**
 * "Satu chat, semua tercatat": pick a message and see what it does to the
 * books. Played from examples.ts, so it never calls the model.
 */
export function ChatDemo() {
    const [id, setId] = useState<DemoExample['id']>('jual');
    const example = examples.find((item) => item.id === id) ?? examples[0];

    return (
        <div className="flex min-w-0 flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p id="demo-pilih" className="text-sm font-medium">
                    Pilih pesan, lihat yang tercatat
                </p>
                <DemoDataBadge />
            </div>
            <ChoiceGroup
                wrap
                labelledBy="demo-pilih"
                value={id}
                options={examples.map(({ id: value, chip }) => ({
                    value,
                    label: chip,
                }))}
                onChange={setId}
            />
            <p className="sr-only" aria-live="polite">
                {example.summary}
            </p>

            <div className="grid grid-cols-2 gap-3 md:grid-cols-[minmax(0,1fr)_17rem_minmax(0,1fr)] md:grid-rows-2 md:gap-x-0 md:gap-y-4">
                <Phone
                    example={example}
                    className="col-span-2 mx-auto w-full max-w-[17rem] md:col-span-1 md:col-start-2 md:row-span-2 md:row-start-1"
                />
                <Impact
                    title="Stok"
                    className="col-span-2 md:col-span-1 md:col-start-1 md:row-start-1 md:-mr-3 md:self-end"
                >
                    <StockChanges stock={example.stock} />
                </Impact>
                <Impact
                    title={example.cash.label}
                    className="md:col-start-3 md:row-start-1 md:-ml-3 md:self-end"
                >
                    <Money
                        value={Math.abs(example.cash.amount)}
                        direction={example.cash.amount < 0 ? 'out' : 'in'}
                    />
                </Impact>
                <Impact
                    title={example.profit.label}
                    className="md:col-start-1 md:row-start-2 md:-mr-3 md:self-start"
                >
                    {example.profit.amount === 0 ? (
                        <p className="text-base font-semibold">Tetap</p>
                    ) : (
                        <Money value={example.profit.amount} direction="in" />
                    )}
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {example.profit.note}
                    </p>
                </Impact>
                <Impact
                    title="Jurnal"
                    className="col-span-2 md:col-span-1 md:col-start-3 md:row-start-2 md:-ml-3 md:self-start"
                >
                    <Journal lines={example.journal} />
                </Impact>
            </div>
        </div>
    );
}

function Phone({
    example,
    className,
}: {
    example: DemoExample;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative z-0 overflow-hidden rounded-[2.25rem] border-[6px] border-foreground bg-card shadow-toast',
                className,
            )}
        >
            <div className="flex items-center gap-2 border-b px-3 pt-4 pb-2.5">
                <span
                    aria-hidden
                    className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-soft text-brand-strong"
                >
                    <MessageCircle className="size-4" />
                </span>
                <span className="min-w-0">
                    <span className="block text-sm leading-tight font-semibold">
                        Bot Lais
                    </span>
                    <span className="block text-xs leading-tight text-muted-foreground">
                        di Telegram
                    </span>
                </span>
            </div>
            <div
                key={example.id}
                className="flex h-[23rem] flex-col justify-end gap-2 px-3 py-3 motion-safe:animate-in motion-safe:duration-200 motion-safe:fade-in"
            >
                <Bubble from="owner">{example.message}</Bubble>
                {example.draft && (
                    <Bubble from="bot">
                        <p className="font-semibold">{example.draft.title}</p>
                        {example.draft.lines.map((line) => (
                            <p key={line}>• {line}</p>
                        ))}
                        <p>
                            {example.draft.totalLabel}:{' '}
                            <span className="font-semibold">
                                {formatRupiah(example.draft.total)}
                            </span>
                        </p>
                        <p>{example.draft.note}</p>
                        <p className="mt-1.5">{CONFIRM}</p>
                    </Bubble>
                )}
                {example.saved && (
                    <>
                        <Bubble from="owner">1</Bubble>
                        <Bubble from="bot">{example.saved}</Bubble>
                    </>
                )}
                {example.answer && (
                    <Bubble from="bot">
                        {example.answer.map((line) => (
                            <p key={line}>{line}</p>
                        ))}
                    </Bubble>
                )}
            </div>
        </div>
    );
}

function Bubble({
    from,
    children,
}: {
    from: 'owner' | 'bot';
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'max-w-[92%] rounded-2xl px-3 py-2 text-xs leading-relaxed',
                from === 'owner'
                    ? 'self-end rounded-br-md bg-brand-soft'
                    : 'self-start rounded-bl-md bg-secondary',
            )}
        >
            {children}
        </div>
    );
}

function Impact({
    title,
    className,
    children,
}: {
    title: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'relative z-10 min-w-0 rounded-xl border bg-card p-3 shadow-tile',
                className,
            )}
        >
            <p className="mb-1 text-xs text-muted-foreground">{title}</p>
            {children}
        </div>
    );
}

function StockChanges({ stock }: { stock: DemoExample['stock'] }) {
    if (stock.length === 0) {
        return <p className="text-sm font-medium">Tidak berubah</p>;
    }

    const rest = stock.length - STOCK_SHOWN;

    return (
        <ul className="flex flex-col gap-0.5 text-sm">
            {stock.slice(0, STOCK_SHOWN).map(({ name, qty, unit }) => (
                <li key={name} className="flex justify-between gap-2">
                    <span className="min-w-0 truncate">{name}</span>
                    <span className="num shrink-0 font-medium">
                        {qty < 0 ? '−' : '+'}
                        {formatQty(Math.abs(qty), unit)}
                    </span>
                </li>
            ))}
            {rest > 0 && (
                <li className="text-xs text-muted-foreground">
                    dan {rest} lainnya
                </li>
            )}
        </ul>
    );
}

function Journal({ lines }: { lines: DemoExample['journal'] }) {
    if (lines.length === 0) {
        return (
            <p className="text-sm font-medium">
                Bertanya tidak mengubah pembukuan
            </p>
        );
    }

    return (
        <>
            <ul className="flex flex-col gap-0.5 text-xs">
                {lines.map(({ account, debit, credit }) => (
                    <li
                        key={account}
                        className={cn(
                            'flex justify-between gap-2',
                            credit > 0 && 'pl-3 text-muted-foreground',
                        )}
                    >
                        <span className="min-w-0 truncate">{account}</span>
                        <Money
                            value={debit || credit}
                            size="sm"
                            className="shrink-0 text-xs font-normal"
                        />
                    </li>
                ))}
            </ul>
            <p className="mt-1.5 flex items-center gap-1 text-xs font-medium text-success">
                <Check className="size-3.5" aria-hidden />
                Seimbang
            </p>
        </>
    );
}
