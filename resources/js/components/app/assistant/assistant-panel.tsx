import { Link, usePage } from '@inertiajs/react';
import {
    CircleAlert,
    CircleCheck,
    ImagePlus,
    MessageCircleQuestion,
    SendHorizontal,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import {
    useAssistant,
    type AssistantFormName,
    type AssistantTurn,
    type DraftCard,
} from '@/components/app/assistant/assistant-provider';
import { Money } from '@/components/app/money';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useIsMobile } from '@/hooks/use-mobile';
import { formatQty, formatRupiah } from '@/lib/format';
import { chatParts } from '@/lib/chat-text';
import { photoToDataUrl } from '@/lib/image';
import { cn } from '@/lib/utils';
import { isReport, reportQuestions } from './report-questions';

const title = 'Tanya AI';
const description =
    'Tanya apa saja soal usahamu, atau ceritakan yang mau dicatat. Jawabannya dari pembukuanmu sendiri.';

const SLOW =
    'Masih memproses. Kalimat dengan resep atau foto nota butuh waktu lebih lama, biasanya kurang dari setengah menit.';

// Owners adapt these to their own menu; picking one only fills the input.
const examples: Record<AssistantFormName | 'none', string[]> = {
    transaksi: [
        'jual 3 nasi goreng, bayar QRIS',
        'beli beras 5 kg 70rb tunai',
        'uang pribadi 200rb dicatat di mana?',
    ],
    produk: [
        'tambah menu mie goreng 14rb, pakai telur 1 dan beras 1 ons',
        'tambah bahan gula pasir, stok 2 kg, modal 15rb per kg',
    ],
    none: ['jual 3 nasi goreng, bayar QRIS', 'beli beras 5 kg 70rb tunai'],
};

/** Questions about the business itself, on every page. */
const questions = [
    'untung bulan ini berapa?',
    'apa itu HPP?',
    'utang dicatat di mana?',
    'es teh worth it nggak?',
];

function Card({ card }: { card: DraftCard }) {
    return (
        <div className="flex flex-col gap-2">
            <p className="font-semibold">{card.title}</p>
            {card.lines.length > 0 && (
                <ul className="flex flex-col gap-1.5">
                    {card.lines.map((line, index) => (
                        <li
                            key={`${line.name}-${index}`}
                            className="flex items-start justify-between gap-3"
                        >
                            <span className="min-w-0">
                                <span className="block">{line.name}</span>
                                <span className="block text-xs text-muted-foreground">
                                    {line.qty !== null &&
                                        formatQty(line.qty, line.unit ?? '')}
                                    {line.price !== null && (
                                        <> × {formatRupiah(line.price)}</>
                                    )}
                                </span>
                            </span>
                            {line.amount !== null && (
                                <Money value={line.amount} size="sm" />
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {card.total && (
                <p
                    className={cn(
                        'flex items-baseline justify-between gap-3 font-semibold',
                        // The rule separates the lines from their total; without lines there is nothing to separate.
                        card.lines.length > 0 && 'border-t pt-2',
                    )}
                >
                    {card.total.label}
                    <Money value={card.total.amount} />
                </p>
            )}
            {card.notes.length > 0 && (
                <p className="text-xs text-muted-foreground">
                    {card.notes.join(' · ')}
                </p>
            )}
        </div>
    );
}

function Turn({ turn, index }: { turn: AssistantTurn; index: number }) {
    const {
        turns,
        send,
        retry,
        sending,
        draftTurn,
        draftHasForm,
        openInForm,
        takeOffer,
        setOpen,
    } = useAssistant();
    const isLast = index === turns.length - 1;

    if (turn.role === 'user') {
        return (
            <div className="ml-8 flex flex-col items-end gap-1.5 self-end">
                {turn.photo && (
                    <img
                        src={turn.photo}
                        alt="Foto nota"
                        className="max-h-48 max-w-full rounded-lg border object-contain"
                    />
                )}
                {turn.text && (
                    <p className="rounded-lg bg-secondary px-3 py-2 text-sm whitespace-pre-wrap">
                        {turn.text}
                    </p>
                )}
            </div>
        );
    }

    const offer = turn.guide?.offer ?? null;
    // A saved record and a guide's page both open as a button that closes the panel.
    const link = turn.saved ?? turn.link;

    return (
        <div
            className={cn(
                'mr-6 flex flex-col gap-3 self-start rounded-lg border bg-card px-3 py-2.5 text-sm shadow-card',
                // A draft card reads like a receipt: give its amounts the whole row.
                turn.card && 'self-stretch',
            )}
        >
            {turn.card && <Card card={turn.card} />}
            {turn.missing.length > 0 && (
                <div className="rounded-md bg-secondary px-3 py-2">
                    <p className="font-semibold">Masih kurang</p>
                    <ul className="mt-1 list-disc pl-5">
                        {turn.missing.map((item) => (
                            <li key={item}>{item}</li>
                        ))}
                    </ul>
                </div>
            )}
            {turn.failed ? (
                <p className="flex gap-2 text-destructive">
                    <CircleAlert
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden
                    />
                    {turn.text}
                </p>
            ) : turn.saved ? (
                <p className="flex gap-2 font-medium">
                    <CircleCheck
                        className="mt-0.5 size-4 shrink-0 text-success"
                        aria-hidden
                    />
                    {turn.text}
                </p>
            ) : (
                <ChatText text={turn.text} />
            )}
            {turn.guide && turn.guide.needs.length > 0 && (
                <div className="rounded-md bg-secondary px-3 py-2">
                    <p className="font-semibold">Yang perlu disiapkan</p>
                    <ul className="mt-1 list-disc pl-5">
                        {turn.guide.needs.map((need) => (
                            <li key={need}>{need}</li>
                        ))}
                    </ul>
                </div>
            )}
            {((isLast && offer) || link) && (
                <div className="flex flex-wrap gap-2">
                    {isLast && offer && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={sending}
                            onClick={() => takeOffer(index)}
                        >
                            {offer.label}
                        </Button>
                    )}
                    {link && (
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={link.href}
                                onClick={() => setOpen(false)}
                            >
                                {link.label}
                            </Link>
                        </Button>
                    )}
                </div>
            )}
            {/* Only the draft still waiting can be saved or moved to the form. */}
            {index === draftTurn && (
                <div className="flex flex-wrap gap-2">
                    {turn.confirm && (
                        <Button
                            type="button"
                            size="sm"
                            disabled={sending}
                            onClick={() => send('Simpan')}
                        >
                            Simpan
                        </Button>
                    )}
                    {draftHasForm && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={sending}
                            onClick={openInForm}
                        >
                            Ubah di form
                        </Button>
                    )}
                </div>
            )}
            {/* Only the latest question can still be answered. */}
            {isLast && turn.options.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {turn.options.map((option) => (
                        <Button
                            key={option}
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={sending}
                            onClick={() => send(option)}
                        >
                            {option}
                        </Button>
                    ))}
                </div>
            )}
            {isLast && turn.failed && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="self-start"
                    disabled={sending}
                    onClick={retry}
                >
                    Kirim lagi
                </Button>
            )}
        </div>
    );
}

function Conversation() {
    const { turns, sending, form, send, reset } = useAssistant();
    const { component } = usePage();
    const [text, setText] = useState('');
    const [photo, setPhoto] = useState<string | null>(null);
    const [photoError, setPhotoError] = useState<string | null>(null);
    const [slow, setSlow] = useState(false);
    const input = useRef<HTMLTextAreaElement>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const end = useRef<HTMLDivElement>(null);
    const last = turns.at(-1);

    useEffect(() => {
        end.current?.scrollIntoView({ block: 'end' });
    }, [turns, sending, slow]);

    // 9Router sometimes needs half a minute for a recipe or a receipt; say so before it looks stuck.
    useEffect(() => {
        if (!sending) {
            return;
        }

        const timer = setTimeout(() => setSlow(true), 10_000);

        return () => {
            clearTimeout(timer);
            setSlow(false);
        };
    }, [sending]);

    const submit = (event?: FormEvent) => {
        event?.preventDefault();

        if ((text.trim() === '' && !photo) || sending) {
            return;
        }

        send(text, photo ?? undefined);
        setText('');
        setPhoto(null);
    };

    const attach = (file: File | undefined) => {
        setPhotoError(null);

        if (file) {
            photoToDataUrl(file)
                .then(setPhoto)
                .catch(() =>
                    setPhotoError('Foto tidak bisa dibaca. Coba foto lain.'),
                );
        }
    };

    return (
        <>
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-4 py-4">
                {turns.length === 0 ? (
                    <div className="flex flex-col gap-4">
                        <div>
                            <p className="text-base font-semibold">
                                Yuk tanya AI
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Pilih contoh di bawah, atau tulis sendiri.
                                Pertanyaan lanjutan juga diingat.
                            </p>
                        </div>
                        {[
                            ...(isReport(component)
                                ? [
                                      {
                                          title: 'Tentang laporan ini',
                                          items: reportQuestions(component),
                                      },
                                  ]
                                : []),
                            { title: 'Catat', items: examples[form ?? 'none'] },
                            { title: 'Tanya', items: questions },
                        ].map((group) => (
                            <div
                                key={group.title}
                                className="flex flex-col gap-2"
                            >
                                <p className="text-xs font-medium text-muted-foreground">
                                    {group.title}
                                </p>
                                {group.items.map((example) => (
                                    <button
                                        key={example}
                                        type="button"
                                        onClick={() => {
                                            setText(example);
                                            input.current?.focus();
                                        }}
                                        className="min-h-11 rounded-lg border bg-card px-3 py-2 text-left text-sm shadow-button-soft hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        {example}
                                    </button>
                                ))}
                            </div>
                        ))}
                        <p className="text-sm text-muted-foreground">
                            Bisa juga kirim foto nota belanja lewat tombol foto.
                        </p>
                    </div>
                ) : (
                    turns.map((turn, index) => (
                        <Turn key={index} turn={turn} index={index} />
                    ))
                )}
                {sending && (
                    <div className="flex flex-col gap-1 text-sm text-muted-foreground">
                        <p className="flex items-center gap-2">
                            <Spinner aria-hidden role={undefined} />
                            {last?.role === 'user' && last.photo
                                ? 'AI sedang membaca nota…'
                                : 'AI sedang membaca…'}
                        </p>
                        {slow && <p className="pl-6">{SLOW}</p>}
                    </div>
                )}
                <div ref={end} />
            </div>

            <p className="sr-only" aria-live="polite">
                {sending
                    ? slow
                        ? SLOW
                        : 'AI sedang membaca pesanmu.'
                    : last?.role === 'assistant'
                      ? last.text
                      : ''}
            </p>

            <form
                onSubmit={submit}
                className="flex flex-col gap-2 border-t px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]"
            >
                {photo && (
                    <div className="flex items-center gap-2">
                        <img
                            src={photo}
                            alt="Foto nota yang akan dikirim"
                            className="size-14 rounded-md border object-cover"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setPhoto(null)}
                        >
                            <X aria-hidden />
                            Hapus foto
                        </Button>
                    </div>
                )}
                {photoError && (
                    <p role="alert" className="text-sm text-destructive">
                        {photoError}
                    </p>
                )}
                <Label htmlFor="assistant-message" className="sr-only">
                    Pesan untuk AI
                </Label>
                <Textarea
                    ref={input}
                    id="assistant-message"
                    value={text}
                    onChange={(event) => setText(event.target.value)}
                    // Enter sends, Shift+Enter starts a new line.
                    onKeyDown={(event) => {
                        if (
                            event.key === 'Enter' &&
                            !event.shiftKey &&
                            !event.nativeEvent.isComposing
                        ) {
                            submit(event);
                        }
                    }}
                    rows={2}
                    maxLength={1000}
                    placeholder="Tulis yang mau dicatat"
                    className="max-h-32 resize-none"
                    autoComplete="off"
                />
                <div className="flex items-center gap-2">
                    {turns.length > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={sending}
                            onClick={reset}
                        >
                            Mulai baru
                        </Button>
                    )}
                    <input
                        ref={fileInput}
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        tabIndex={-1}
                        aria-hidden
                        onChange={(event) => {
                            attach(event.target.files?.[0]);
                            // The same photo can be picked again after removing it.
                            event.target.value = '';
                        }}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="ml-auto"
                        disabled={sending}
                        aria-label="Lampirkan foto nota"
                        onClick={() => fileInput.current?.click()}
                    >
                        <ImagePlus aria-hidden />
                    </Button>
                    <Button
                        type="submit"
                        disabled={sending || (text.trim() === '' && !photo)}
                    >
                        <SendHorizontal aria-hidden />
                        Kirim
                    </Button>
                </div>
            </form>
        </>
    );
}

/**
 * The conversation: a drawer on phones and tablets; on desktop a side sheet
 * that does not block the page, so the form it fills stays in view.
 */
export function AssistantPanel() {
    const isMobile = useIsMobile();
    const { open, setOpen } = useAssistant();

    if (isMobile) {
        return (
            <Drawer open={open} onOpenChange={setOpen}>
                <DrawerContent className="max-h-[85dvh]">
                    <DrawerHeader className="text-left">
                        <DrawerTitle>{title}</DrawerTitle>
                        <DrawerDescription>{description}</DrawerDescription>
                    </DrawerHeader>
                    <Conversation />
                </DrawerContent>
            </Drawer>
        );
    }

    return (
        <Sheet open={open} onOpenChange={setOpen} modal={false}>
            <SheetContent
                className="w-full gap-0 sm:max-w-md"
                // Editing the form beside the panel must not close it.
                onInteractOutside={(event) => event.preventDefault()}
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    document.getElementById('assistant-message')?.focus();
                }}
            >
                <SheetHeader className="border-b pr-10">
                    <SheetTitle>{title}</SheetTitle>
                    <SheetDescription>{description}</SheetDescription>
                </SheetHeader>
                <Conversation />
            </SheetContent>
        </Sheet>
    );
}

/** Floats above the bottom nav and any form action bar (app.css). */
/**
 * A reply as the chat writes it: its lines and blank lines kept (answers come
 * as a conclusion, then points), with *bold* figures and _italic_ words.
 */
function ChatText({ text }: { text: string }) {
    return (
        <p className="leading-relaxed whitespace-pre-line">
            {chatParts(text).map((part, index) =>
                part.kind === 'bold' ? (
                    <strong key={index} className="font-semibold">
                        {part.text}
                    </strong>
                ) : part.kind === 'italic' ? (
                    <em key={index}>{part.text}</em>
                ) : (
                    part.text
                ),
            )}
        </p>
    );
}

/** How long the button says "Yuk tanya AI" on arriving at a page. */
const NUDGE_MS = 5000;

const NUDGED_KEY = 'lais-tanya-ai-sapa';

/** Pages it already greeted on this tab, so it says it once per page, not on every visit. */
function nudgedPages(): string[] {
    try {
        return JSON.parse(
            window.sessionStorage.getItem(NUDGED_KEY) ?? '[]',
        ) as string[];
    } catch {
        return [];
    }
}

/**
 * The floating "Tanya AI": a small round button that stays out of the way,
 * which says "Yuk tanya AI" for a few seconds the first time a page opens
 * (on a report: "Yuk tanya AI soal laporan ini"), and again on hover or focus.
 */
export function AssistantButton() {
    const { setOpen, open } = useAssistant();
    const { component } = usePage();
    const [nudging, setNudging] = useState(false);
    const [hovered, setHovered] = useState(false);
    const expanded = (nudging || hovered) && !open;

    useEffect(() => {
        const seen = nudgedPages();

        if (seen.includes(component)) {
            return;
        }

        try {
            window.sessionStorage.setItem(
                NUDGED_KEY,
                JSON.stringify([...seen, component]),
            );
        } catch {
            // Blocked storage: it greets again next time, nothing more.
        }

        const show = window.setTimeout(() => setNudging(true), 600);
        const hide = window.setTimeout(() => setNudging(false), 600 + NUDGE_MS);

        return () => {
            window.clearTimeout(show);
            window.clearTimeout(hide);
        };
    }, [component]);

    return (
        <Button
            type="button"
            variant="outline"
            data-tour="tanya-ai"
            aria-label={expanded ? undefined : 'Tanya AI'}
            onClick={() => setOpen(true)}
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
            onFocus={() => setHovered(true)}
            onBlur={() => setHovered(false)}
            className={cn(
                'fixed right-4 bottom-(--assistant-offset-bottom) z-30 size-11 rounded-full bg-card p-0 shadow-toast lg:right-6 print:hidden',
                expanded && 'w-auto pr-4 pl-3.5',
            )}
        >
            <MessageCircleQuestion
                className="size-5 text-brand-strong"
                aria-hidden
            />
            {expanded && (
                // Text only fades: MOTION 1 keeps the size change a plain swap.
                <span className="motion-safe:animate-in motion-safe:fade-in">
                    {isReport(component)
                        ? 'Yuk tanya AI soal laporan ini'
                        : 'Yuk tanya AI'}
                </span>
            )}
        </Button>
    );
}

/** Shown on a form "Tanya AI" filled, until the owner saves or leaves. */
export function AssistantFilledNote() {
    return (
        <p
            role="status"
            className="flex gap-2 rounded-lg bg-secondary px-3 py-2.5 text-sm"
        >
            <MessageCircleQuestion
                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden
            />
            Diisi Tanya AI. Periksa lagi sebelum menekan Simpan.
        </p>
    );
}
