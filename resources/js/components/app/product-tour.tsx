import {
    Joyride,
    STATUS,
    type Step,
    type TooltipRenderProps,
} from 'react-joyride';
import { Button } from '@/components/ui/button';

const SEEN_KEY = 'lais-tur-dilihat';

/** Seen once per device; storage can be blocked, so a failure means "not seen". */
export function tourSeen(): boolean {
    try {
        return window.localStorage.getItem(SEEN_KEY) === '1';
    } catch {
        return false;
    }
}

function markSeen(): void {
    try {
        window.localStorage.setItem(SEEN_KEY, '1');
    } catch {
        // Private mode: the tour may show again, nothing else breaks.
    }
}

/** The first match on screen: the sidebar and the bottom nav carry the same link at different sizes. */
const onScreen = (selector: string) => () =>
    Array.from(document.querySelectorAll<HTMLElement>(selector)).find(
        (element) => element.getClientRects().length > 0,
    ) ?? null;

const steps: Step[] = [
    {
        target: onScreen('[data-tour="catat"]'),
        title: 'Catat apa saja',
        content:
            'Penjualan, belanja, atau biaya. Isi form singkat, atau tulis seperti chat biasa.',
        skipBeacon: true,
    },
    {
        target: onScreen('[data-tour="tanya-ai"]'),
        title: 'Bingung? Tanya AI',
        content:
            'Tanya dicatat di mana, atau minta dibuatkan catatannya. AI hanya menyiapkan draf, kamu yang menyimpan.',
    },
    {
        target: onScreen('a[href$="/pesanan"]'),
        title: 'Pesanan dan toko online',
        content:
            'Pesanan dari link tokomu masuk di sini, lengkap dengan stok dan pembayarannya.',
    },
    {
        target: onScreen('a[href$="/laporan"]'),
        title: 'Laporan tersusun sendiri',
        content:
            'Laba rugi, neraca, dan daftar akun terisi dari catatanmu, tanpa rumus.',
    },
    {
        // The heading, not the whole card: on a phone the card is taller than the screen.
        target: onScreen('#misi h2'),
        title: 'Misi pertama',
        content:
            'Selesaikan satu per satu. Misi tercentang sendiri begitu datanya masuk.',
    },
];

function Tooltip({
    step,
    index,
    size,
    isLastStep,
    backProps,
    primaryProps,
    skipProps,
    tooltipProps,
}: TooltipRenderProps) {
    return (
        <div
            {...tooltipProps}
            className="w-[min(20rem,calc(100vw-2rem))] rounded-xl border bg-card p-4 text-card-foreground shadow-lg"
        >
            <p className="num text-xs font-medium text-muted-foreground">
                {index + 1} dari {size}
            </p>
            {step.title && (
                <h2 className="mt-1 text-base font-semibold">{step.title}</h2>
            )}
            <div className="mt-1 text-sm text-muted-foreground">
                {step.content}
            </div>
            <div className="mt-4 flex items-center justify-between gap-2">
                <Button type="button" variant="ghost" size="sm" {...skipProps}>
                    Lewati
                </Button>
                <div className="flex gap-2">
                    {index > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            {...backProps}
                        >
                            Kembali
                        </Button>
                    )}
                    <Button type="button" size="sm" {...primaryProps}>
                        {isLastStep ? 'Selesai' : 'Lanjut'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

/**
 * The dashboard tour (UI-PAGES C1): where to record, ask, sell, and read.
 * Rendered only while running, so the server render never touches it.
 */
export function ProductTour({
    run,
    onDone,
}: {
    run: boolean;
    onDone: () => void;
}) {
    if (!run) {
        return null;
    }

    // Only the stops this screen shows (Laporan sits in the menu on a phone), so "1 dari N" stays true.
    const shown = steps.filter(
        (step) => typeof step.target !== 'function' || step.target() !== null,
    );

    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    // Joyride paints the overlay as an SVG fill, which cannot read a CSS variable: take the token's value.
    const ink =
        getComputedStyle(document.documentElement)
            .getPropertyValue('--foreground')
            .trim() || '#000000';

    return (
        <Joyride
            run
            continuous
            scrollToFirstStep
            steps={shown}
            tooltipComponent={Tooltip}
            locale={{
                back: 'Kembali',
                close: 'Tutup',
                last: 'Selesai',
                next: 'Lanjut',
                skip: 'Lewati',
                open: 'Buka',
            }}
            options={{
                zIndex: 60,
                // shortcut: the token is a 6-digit hex, so "73" adds 45% alpha; parse it if the token's format changes.
                overlayColor: `${ink}73`,
                spotlightRadius: 12,
                // Clear of the sticky header, so a heading is not scrolled under it.
                scrollOffset: 88,
                scrollDuration: still ? 0 : 300,
            }}
            onEvent={(data) => {
                if (
                    data.status === STATUS.FINISHED ||
                    data.status === STATUS.SKIPPED
                ) {
                    markSeen();
                    onDone();
                }
            }}
        />
    );
}
