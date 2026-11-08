import { ArrowRight, Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { DemoDataBadge } from '@/components/app/demo-data-badge';
import { Money } from '@/components/app/money';
import { cn } from '@/lib/utils';
import type { SampleRecord } from './examples';
import { books, sampleRecords } from './examples';
import { heading, shell } from './landing-sections';
import { WordReveal } from './word-reveal';
import { isMouse } from './pointer';

/** How long each chat stays lit before the next one. */
const STEP_MS = 2800;

/** The joints where a line meets a card, as in the diagram reference. */
const jointRight =
    'after:absolute after:top-1/2 after:-right-1 after:size-2 after:-translate-y-1/2 after:rounded-full after:border after:bg-card';
const jointLeft =
    'after:absolute after:top-1/2 after:-left-1 after:size-2 after:-translate-y-1/2 after:rounded-full after:border after:bg-card';

/** Where row `index` of six sits, in the diagram's 0 to 100 height. */
function rowY(index: number): number {
    return ((index + 0.5) / books.length) * 100;
}

/**
 * Chats on the left, the AI in the middle, the books on the right: one chat
 * at a time lights its way to the book it belongs in (landing.css). Played
 * from examples.ts, so it never calls the model.
 */
export function ChatRouter() {
    const [step, setStep] = useState(0);
    const [hovered, setHovered] = useState(false);
    const [focused, setFocused] = useState(false);
    // A tap (not a scroll) holds it on touch screens; another tap lets it go.
    const [tapped, setTapped] = useState(false);
    const held = hovered || focused || tapped;
    const current = sampleRecords[step];
    const target = books.findIndex((book) => book.id === current.book);

    useEffect(() => {
        if (
            held ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        const timer = window.setInterval(
            () => setStep((now) => (now + 1) % sampleRecords.length),
            STEP_MS,
        );

        return () => window.clearInterval(timer);
    }, [held]);

    return (
        <section
            aria-labelledby="contoh-pesan"
            className={cn(shell, 'py-10 md:py-14')}
        >
            <h2
                id="contoh-pesan"
                data-reveal="words"
                className={cn(heading, 'mx-auto max-w-2xl text-center')}
            >
                <WordReveal text="Cukup tulis seperti biasa, Lais yang memilah." />
            </h2>
            <div className="mt-3 flex justify-center">
                <DemoDataBadge />
            </div>

            <div
                role="region"
                aria-label="Contoh chat yang dipilah AI ke buku yang tepat. Arahkan kursor ke sebuah chat, sentuh, atau fokus untuk berhenti."
                tabIndex={0}
                // Only keyboard focus holds it; a tap also focuses, and tapped handles that.
                onFocus={(event) =>
                    setFocused(event.currentTarget.matches(':focus-visible'))
                }
                onBlur={() => setFocused(false)}
                onClick={(event) => {
                    if (!isMouse(event.nativeEvent)) {
                        setTapped((now) => !now);
                    }
                }}
                className="mt-8 rounded-3xl outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 motion-reduce:hidden"
            >
                <Wide
                    step={step}
                    target={target}
                    // Only a chat holds it: the cursor rests on the wide
                    // diagram while scrolling, which would freeze it.
                    onPoint={(index) => {
                        if (index !== null) {
                            setStep(index);
                        }

                        setHovered(index !== null);
                    }}
                />
                <Narrow record={current} target={target} step={step} />
            </div>

            <ul className="sr-only motion-reduce:not-sr-only motion-reduce:mt-8 motion-reduce:grid motion-reduce:gap-4 motion-reduce:md:grid-cols-2">
                {sampleRecords.map((sample) => (
                    <li
                        key={sample.message}
                        className="flex flex-col items-start gap-1 text-sm"
                    >
                        <span className="rounded-2xl rounded-br-md bg-brand-soft px-3 py-1">
                            {sample.message}
                        </span>
                        <span className="flex flex-wrap items-center gap-x-2 pl-3">
                            <ArrowRight
                                className="size-4 shrink-0 text-muted-foreground"
                                aria-hidden
                            />
                            <span className="sr-only">masuk ke buku</span>
                            <span className="font-medium">
                                {
                                    books.find(
                                        (book) => book.id === sample.book,
                                    )?.label
                                }
                                : {sample.record}
                            </span>
                            <Money
                                value={sample.amount}
                                direction={sample.direction}
                                size="sm"
                            />
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** Three columns, joined by lines, from 768 px up. */
function Wide({
    step,
    target,
    onPoint,
}: {
    step: number;
    target: number;
    onPoint: (index: number | null) => void;
}) {
    return (
        <div aria-hidden className="hidden md:block">
            <div className="mb-3 grid grid-cols-[30%_1fr_30%] text-xs font-medium text-muted-foreground">
                <span>Chat masuk</span>
                <span className="text-center">Dibaca AI</span>
                <span>Masuk ke buku</span>
            </div>
            <div className="relative h-[27rem]">
                <svg
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                    className="absolute inset-0 size-full overflow-visible"
                >
                    {sampleRecords.map((sample, index) => (
                        <path
                            key={sample.message}
                            d={`M30 ${rowY(index)} C36 ${rowY(index)} 34 50 40 50`}
                            pathLength={1}
                            vectorEffect="non-scaling-stroke"
                            className={cn(
                                'landing-route',
                                index === step && 'is-active',
                            )}
                        />
                    ))}
                    {books.map((book, index) => (
                        <path
                            key={book.id}
                            d={`M60 50 C66 50 64 ${rowY(index)} 70 ${rowY(index)}`}
                            pathLength={1}
                            vectorEffect="non-scaling-stroke"
                            className={cn(
                                'landing-route landing-route-out',
                                index === target && 'is-active',
                            )}
                        />
                    ))}
                </svg>

                <ul
                    onMouseLeave={() => onPoint(null)}
                    className="absolute inset-y-0 left-0 grid w-[30%] grid-rows-6"
                >
                    {sampleRecords.map((sample, index) => (
                        <li
                            key={sample.message}
                            onMouseEnter={() => onPoint(index)}
                            className="flex items-center py-1"
                        >
                            <p
                                className={cn(
                                    'landing-node relative flex h-full w-full items-center rounded-2xl rounded-br-md border bg-card px-3 text-sm',
                                    jointRight,
                                    index === step && 'is-active',
                                )}
                            >
                                <span className="line-clamp-2">
                                    {sample.message}
                                </span>
                            </p>
                        </li>
                    ))}
                </ul>

                <div className="absolute top-1/2 left-[40%] w-[20%] -translate-y-1/2">
                    <AiNode
                        className={cn(
                            jointRight,
                            'before:absolute before:top-1/2 before:-left-1 before:size-2 before:-translate-y-1/2 before:rounded-full before:border before:bg-card',
                        )}
                    />
                </div>

                <ul className="absolute inset-y-0 right-0 grid w-[30%] grid-rows-6">
                    {books.map((book, index) => (
                        <li key={book.id} className="flex items-center py-1">
                            <Book
                                label={book.label}
                                record={
                                    index === target
                                        ? sampleRecords[step]
                                        : null
                                }
                                className={jointLeft}
                            />
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}

/** One chat at a time, falling through the AI into its book, on phones. */
function Narrow({
    record,
    target,
    step,
}: {
    record: SampleRecord;
    target: number;
    step: number;
}) {
    return (
        <div aria-hidden className="flex flex-col items-center md:hidden">
            <p
                key={step}
                className="landing-node landing-node-enter is-active flex min-h-[4.25rem] w-full items-center rounded-2xl rounded-br-md border bg-card px-3 py-3 text-sm"
            >
                {record.message}
            </p>
            <span className="landing-drop h-6 w-0.5" />
            <div className="w-56">
                <AiNode />
            </div>
            <span className="landing-drop h-6 w-0.5" />
            <ul className="grid w-full grid-cols-2 gap-2">
                {books.map((book, index) => (
                    <li key={book.id} className="h-20">
                        <Book
                            label={book.label}
                            record={index === target ? record : null}
                        />
                    </li>
                ))}
            </ul>
        </div>
    );
}

function AiNode({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                'relative flex flex-col items-center gap-1.5 rounded-2xl border bg-card px-3 py-4 text-center shadow-toast ring-4 ring-brand-soft',
                className,
            )}
        >
            <AppLogoIcon className="h-6" />
            <p className="text-sm font-semibold">Lais AI</p>
            <p className="flex items-center gap-1 text-xs text-muted-foreground">
                membaca maksudnya
                <span className="landing-reading flex gap-0.5">
                    <span />
                    <span />
                    <span />
                </span>
            </p>
        </div>
    );
}

/**
 * A book on the right. The one the chat belongs in lights up and shows
 * the record, a beat after the line reaches it (landing.css).
 */
function Book({
    label,
    record,
    className,
}: {
    label: string;
    record: SampleRecord | null;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'landing-book relative flex h-full w-full flex-col justify-center rounded-2xl border bg-card px-3',
                record !== null && 'is-active',
                className,
            )}
        >
            <p className="text-sm font-semibold">{label}</p>
            {record !== null && (
                <div className="landing-book-record text-xs">
                    <p className="flex items-center gap-1">
                        <Check
                            className="size-3.5 shrink-0 text-success"
                            aria-label="Tercatat"
                        />
                        <Money
                            value={record.amount}
                            direction={record.direction}
                            size="sm"
                            className="text-xs"
                        />
                    </p>
                    <p className="truncate text-muted-foreground">
                        {record.record}
                    </p>
                </div>
            )}
        </div>
    );
}
