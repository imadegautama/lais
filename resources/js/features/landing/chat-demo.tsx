import {
    BatteryFull,
    Check,
    ChevronLeft,
    Mic,
    Paperclip,
    Signal,
    Wifi,
} from 'lucide-react';
import type { CSSProperties, ReactNode } from 'react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { ChoiceGroup } from '@/components/app/choice-group';
import { DemoDataBadge } from '@/components/app/demo-data-badge';
import { Money } from '@/components/app/money';
import { formatQty, formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { DemoExample } from './examples';
import { CONFIRM, examples } from './examples';
import { delay } from './word-reveal';

const STOCK_SHOWN = 3;

/** When each bubble lands, in ms from the start of the play (landing.css). */
const PLAY = { draft: 280, reply: 700, saved: 880, cards: 1000, answered: 480 };

/**
 * "Satu chat, semua tercatat": pick a message and see what it does to the
 * books. Played from examples.ts, so it never calls the model.
 */
export function ChatDemo() {
    const [id, setId] = useState<DemoExample['id']>('jual');
    // The first play waits for the page to arrive; a pick plays at once.
    const [picked, setPicked] = useState(false);
    const example = examples.find((item) => item.id === id) ?? examples[0];
    const timing = {
        '--start': picked ? '0ms' : '400ms',
        '--cards': `${example.saved ? PLAY.cards : PLAY.answered}ms`,
    } as CSSProperties;
    const pick = (next: DemoExample['id']) => {
        setId(next);
        setPicked(true);
    };

    return (
        <div className="landing-demo-drift flex min-w-0 flex-col gap-4">
            <div
                data-intro
                style={delay(450)}
                className="flex flex-wrap items-center justify-between gap-2"
            >
                <p id="demo-pilih" className="text-sm font-medium">
                    Pilih pesan, lihat yang tercatat
                </p>
                <DemoDataBadge />
            </div>
            <div data-intro style={delay(520)}>
                <ChoiceGroup
                    wrap
                    labelledBy="demo-pilih"
                    value={id}
                    options={examples.map(({ id: value, chip }) => ({
                        value,
                        label: chip,
                    }))}
                    onChange={pick}
                />
            </div>
            <p className="sr-only" aria-live="polite">
                {example.summary}
            </p>

            <div
                data-reveal="play"
                style={timing}
                className="grid grid-cols-2 gap-3 md:grid-cols-[minmax(0,1fr)_17rem_minmax(0,1fr)] md:grid-rows-2 md:gap-x-0 md:gap-y-4"
            >
                <Phone
                    example={example}
                    className="col-span-2 mx-auto w-full max-w-[17rem] md:col-span-1 md:col-start-2 md:row-span-2 md:row-start-1"
                />
                <Impact
                    key={`${example.id}-0`}
                    side="left"
                    order={0}
                    title="Stok"
                    className="col-span-2 md:col-span-1 md:col-start-1 md:row-start-1 md:-mr-3 md:self-end"
                >
                    <StockChanges stock={example.stock} />
                </Impact>
                <Impact
                    key={`${example.id}-1`}
                    side="right"
                    order={1}
                    title={example.cash.label}
                    className="md:col-start-3 md:row-start-1 md:-ml-3 md:self-end"
                >
                    <Money
                        value={Math.abs(example.cash.amount)}
                        direction={example.cash.amount < 0 ? 'out' : 'in'}
                    />
                </Impact>
                <Impact
                    key={`${example.id}-2`}
                    side="left"
                    order={2}
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
                    key={`${example.id}-3`}
                    side="right"
                    order={3}
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
            data-intro
            style={delay(300)}
            className={cn('landing-phone z-0', className)}
        >
            <span aria-hidden className="landing-phone-key top-24 h-6" />
            <span aria-hidden className="landing-phone-key top-34 h-11" />
            <span aria-hidden className="landing-phone-key top-48 h-11" />
            <span
                aria-hidden
                className="landing-phone-key landing-phone-key-right top-38 h-16"
            />
            <div className="landing-phone-bezel">
                <div className="landing-phone-screen">
                    <StatusBar />
                    <div className="flex items-center gap-1.5 border-b bg-card px-2 pb-2">
                        <ChevronLeft
                            aria-hidden
                            className="size-5 shrink-0 text-brand-strong"
                        />
                        <span
                            aria-hidden
                            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-soft"
                        >
                            <AppLogoIcon className="h-4" />
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
                        className="flex h-[23.5rem] flex-col justify-end gap-2 bg-surface-warm px-3 py-3"
                    >
                        <Bubble from="owner" at={0}>
                            {example.message}
                        </Bubble>
                        {example.draft && (
                            <Bubble from="bot" at={PLAY.draft}>
                                <p className="font-semibold">
                                    {example.draft.title}
                                </p>
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
                                <Bubble from="owner" at={PLAY.reply}>
                                    1
                                </Bubble>
                                <Bubble from="bot" at={PLAY.saved}>
                                    {example.saved}
                                </Bubble>
                            </>
                        )}
                        {example.answer && (
                            <Bubble from="bot" at={PLAY.draft}>
                                {example.answer.map((line) => (
                                    <p key={line}>{line}</p>
                                ))}
                            </Bubble>
                        )}
                    </div>
                    <div aria-hidden className="bg-card px-2.5 pt-2 pb-2">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Paperclip className="size-4 shrink-0" />
                            <span className="min-w-0 flex-1 rounded-full border px-3 py-1 text-xs">
                                Tulis pesan
                            </span>
                            <Mic className="size-4 shrink-0" />
                        </div>
                        <span className="mx-auto mt-2.5 block h-1 w-24 rounded-full bg-foreground" />
                    </div>
                </div>
            </div>
        </div>
    );
}

/** The phone's own top bar; part of the picture, so nothing reads it out. */
function StatusBar() {
    return (
        <div
            aria-hidden
            className="relative flex h-9 items-center justify-between bg-card px-5 text-xs font-semibold"
        >
            <span className="num">09.41</span>
            <span className="absolute top-2 left-1/2 h-5 w-[4.5rem] -translate-x-1/2 rounded-full bg-foreground" />
            <span className="flex items-center gap-1">
                <Signal className="size-3" />
                <Wifi className="size-3" />
                <BatteryFull className="size-4" />
            </span>
        </div>
    );
}

function Bubble({
    from,
    at,
    children,
}: {
    from: 'owner' | 'bot';
    at: number;
    children: ReactNode;
}) {
    return (
        <div
            data-bubble
            style={{ '--at': `${at}ms` } as CSSProperties}
            className={cn(
                'max-w-[92%] rounded-2xl px-3 py-2 text-xs leading-relaxed',
                from === 'owner'
                    ? 'self-end rounded-br-md bg-brand-soft'
                    : 'self-start rounded-bl-md bg-card shadow-tile',
            )}
        >
            {children}
        </div>
    );
}

function Impact({
    side,
    order,
    title,
    className,
    children,
}: {
    side: 'left' | 'right';
    order: number;
    title: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            data-impact={side}
            style={{ '--order': order } as CSSProperties}
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
