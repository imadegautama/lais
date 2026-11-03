import { router, useHttp } from '@inertiajs/react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { store } from '@/routes/assistant';

/** The forms "Tanya AI" drafts (App\Services\Ai\FormAssistant::FORMS). */
export type AssistantFormName = 'transaksi' | 'produk';

/** TransactionDraft::card() / ItemDraft::card(): what is about to be saved. */
export type DraftCard = {
    title: string;
    lines: Array<{
        name: string;
        qty: number | null;
        unit: string | null;
        price: number | null;
        amount: number | null;
    }>;
    total: { label: string; amount: number } | null;
    notes: string[];
};

type Link = { label: string; href: string };

/** A draft a guide prepared; it becomes the live draft only when the owner opens it. */
type Offer = {
    label: string;
    reply: string;
    form: AssistantFormName;
    values: object;
    card: DraftCard | null;
    missing: string[];
    confirm: boolean;
    target: string;
};

type AssistantReplyTurn = {
    role: 'assistant';
    text: string;
    card: DraftCard | null;
    missing: string[];
    options: string[];
    /** The draft can be saved as it is. */
    confirm: boolean;
    saved: Link | null;
    /** "Dicatat di mana?": what to prepare, and a draft to open (GuideTopic). */
    guide: { needs: string[]; offer: Offer | null } | null;
    /** A page the reply points to. */
    link: Link | null;
    failed?: boolean;
};

export type AssistantTurn =
    /** `photo`: a receipt photo as a data URI (lib/image.ts), shown as a thumbnail. */
    { role: 'user'; text: string; photo?: string } | AssistantReplyTurn;

/** AssistantController@store. `values` is the form's data shape, filled in. */
type AssistantReply = {
    reply: string;
    form: AssistantFormName | null;
    values: object | null;
    card: DraftCard | null;
    missing: string[];
    options: string[];
    confirm: boolean;
    target: string | null;
    saved: Link | null;
    guide: { topic: string; needs: string[]; offer: Offer | null } | null;
    link: Link | null;
};

/** The draft the owner was last shown, until it is saved or handed to a form. */
type Draft = {
    form: AssistantFormName;
    values: object;
    target: string;
    /** Index of the turn that shows it. */
    turn: number;
};

type Registration = {
    form: AssistantFormName;
    values: () => object;
    apply: (values: object) => void;
};

type AssistantContextValue = {
    open: boolean;
    setOpen: (open: boolean) => void;
    turns: AssistantTurn[];
    sending: boolean;
    /** The form open on this page, if it can be filled. */
    form: AssistantFormName | null;
    /** Which turn shows the live draft, or null. */
    draftTurn: number | null;
    send: (message: string, photo?: string) => void;
    retry: () => void;
    reset: () => void;
    openInForm: () => void;
    /** Opens the draft a guide turn prepared. */
    takeOffer: (turn: number) => void;
    register: (registration: Registration) => () => void;
};

const AssistantContext = createContext<AssistantContextValue | null>(null);

const UNAVAILABLE =
    'AI sedang tidak bisa dipakai. Isi form secara manual dulu, atau coba lagi sebentar lagi.';

function failureMessage(response: { status: number; data: string }): string {
    if (response.status === 429) {
        return 'Pesannya terlalu banyak dalam semenit. Tunggu sebentar, lalu kirim lagi.';
    }

    if (response.status === 419) {
        return 'Sesi sudah habis. Muat ulang halaman, lalu kirim lagi.';
    }

    try {
        return (
            (JSON.parse(response.data) as { message?: string }).message ??
            UNAVAILABLE
        );
    } catch {
        return UNAVAILABLE;
    }
}

function replyTurn(
    text: string,
    fields: Partial<AssistantReplyTurn> = {},
): AssistantReplyTurn {
    return {
        role: 'assistant',
        text,
        card: null,
        missing: [],
        options: [],
        confirm: false,
        saved: null,
        guide: null,
        link: null,
        ...fields,
    };
}

/**
 * "Tanya AI" for every app page (UI-PAGES C19). Lives in the persistent app
 * layout, so the conversation and its draft survive page visits. Saving
 * happens on the server when the owner confirms the draft ("Simpan" or
 * "iya"); "Ubah di form" hands the draft to the form instead.
 */
export function AssistantProvider({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const [turns, setTurns] = useState<AssistantTurn[]>([]);
    const [draft, setDraft] = useState<Draft | null>(null);
    const [form, setForm] = useState<AssistantFormName | null>(null);
    const registration = useRef<Registration | null>(null);
    const pending = useRef<{ form: AssistantFormName; values: object } | null>(
        null,
    );
    // Each send builds its whole body in transform(); the hook only carries it.
    const http = useHttp<{ message: string }, AssistantReply>({ message: '' });

    const register = useCallback((next: Registration) => {
        registration.current = next;
        setForm(next.form);

        if (pending.current?.form === next.form) {
            next.apply(pending.current.values);
            pending.current = null;
        }

        return () => {
            if (registration.current === next) {
                registration.current = null;
                setForm(null);
            }
        };
    }, []);

    const send = useCallback(
        async (
            message: string,
            photo: string | undefined,
            base: AssistantTurn[],
            shown: Draft | null,
        ) => {
            const text = message.trim();

            if (text === '' && !photo) {
                return;
            }

            const current = registration.current;
            let failure = UNAVAILABLE;
            setTurns([...base, { role: 'user', text, photo }]);
            http.transform(() => ({
                message: text,
                photo: photo ?? null,
                form: current?.form ?? null,
                history: base
                    .filter((turn) => !('failed' in turn && turn.failed))
                    // The photo is read once; later turns only need to know there was one.
                    .map((turn) => ({
                        role: turn.role,
                        text: turn.text || 'Foto nota',
                    }))
                    .slice(-12),
                current: current?.values() ?? {},
                draft: shown && { form: shown.form, values: shown.values },
            }));

            const reply = await http
                .post(store.url(), {
                    onError: (errors) => {
                        failure = errors.message ?? failure;
                    },
                    onHttpException: (response) => {
                        failure = failureMessage(response);
                    },
                    onNetworkError: () => {
                        failure =
                            'Tidak tersambung ke server. Periksa internet, lalu kirim lagi.';
                    },
                })
                .catch(() => undefined);

            if (!reply) {
                setTurns((all) => [
                    ...all,
                    replyTurn(failure, { failed: true }),
                ]);

                return;
            }

            setTurns((all) => [
                ...all,
                replyTurn(reply.reply, {
                    card: reply.card,
                    missing: reply.missing,
                    options: reply.options,
                    confirm: reply.confirm,
                    saved: reply.saved,
                    guide: reply.guide,
                    link: reply.link,
                }),
            ]);

            if (reply.saved !== null) {
                setDraft(null);
                // The page behind the panel shows the new record too.
                router.reload();
            } else if (
                reply.form !== null &&
                reply.values !== null &&
                reply.target !== null
            ) {
                setDraft({
                    form: reply.form,
                    values: reply.values,
                    target: reply.target,
                    turn: base.length + 1,
                });
            }
        },
        [http],
    );

    const value = useMemo<AssistantContextValue>(
        () => ({
            open,
            setOpen,
            turns,
            sending: http.processing,
            form,
            draftTurn: draft?.turn ?? null,
            send: (message, photo) => void send(message, photo, turns, draft),
            // The failed reply and the message before it make way for the new try.
            retry: () => {
                const last = turns.at(-2);

                if (last?.role === 'user') {
                    void send(last.text, last.photo, turns.slice(0, -2), draft);
                }
            },
            reset: () => {
                setTurns([]);
                setDraft(null);
            },
            openInForm: () => {
                if (draft === null) {
                    return;
                }

                if (registration.current?.form === draft.form) {
                    registration.current.apply(draft.values);
                } else {
                    pending.current = {
                        form: draft.form,
                        values: draft.values,
                    };
                    router.visit(draft.target);
                }

                // From here the form holds the draft, so the chat can no longer save it twice.
                setDraft(null);
                setTurns((all) => [
                    ...all,
                    replyTurn(
                        'Sudah saya isi ke form. Periksa lalu tekan Simpan di form.',
                    ),
                ]);
                setOpen(false);
            },
            // Shown at once, without asking the model again; saving still needs Simpan or "iya".
            takeOffer: (index) => {
                const turn = turns[index];
                const offer =
                    turn?.role === 'assistant' ? turn.guide?.offer : null;

                if (!offer) {
                    return;
                }

                setTurns([
                    ...turns,
                    replyTurn(offer.reply, {
                        card: offer.card,
                        missing: offer.missing,
                        confirm: offer.confirm,
                    }),
                ]);
                setDraft({
                    form: offer.form,
                    values: offer.values,
                    target: offer.target,
                    turn: turns.length,
                });
            },
            register,
        }),
        [open, turns, http.processing, form, draft, send, register],
    );

    return (
        <AssistantContext.Provider value={value}>
            {children}
        </AssistantContext.Provider>
    );
}

export function useAssistant(): AssistantContextValue {
    const context = useContext(AssistantContext);

    if (context === null) {
        throw new Error('useAssistant needs an AssistantProvider.');
    }

    return context;
}

/**
 * Lets "Tanya AI" read and fill this page's form. Returns how many times it
 * filled it: a key for the form, so inputs holding their own text redraw.
 */
export function useAssistantForm<T extends object>(
    form: AssistantFormName,
    values: T,
    apply: (values: T) => void,
    enabled = true,
): number {
    const register = useContext(AssistantContext)?.register;
    const [filled, setFilled] = useState(0);
    const latest = useRef({ values, apply });

    useEffect(() => {
        latest.current = { values, apply };
    });

    useEffect(() => {
        if (!register || !enabled) {
            return;
        }

        return register({
            form,
            values: () => latest.current.values,
            apply: (next) => {
                latest.current.apply(next as T);
                setFilled((count) => count + 1);
            },
        });
    }, [register, form, enabled]);

    return filled;
}
