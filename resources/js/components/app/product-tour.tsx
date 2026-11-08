import {
    Joyride,
    STATUS,
    type Step,
    type TooltipRenderProps,
} from 'react-joyride';
import { Button } from '@/components/ui/button';

const shown = (element: HTMLElement) => element.getClientRects().length > 0;

/** The first match on screen: the sidebar and the bottom nav carry the same link at different sizes. */
export const onScreen = (selector: string) => () =>
    Array.from(document.querySelectorAll<HTMLElement>(selector)).find(shown) ??
    null;

/** An element on screen by its own text, such as the button "Buat kode". */
export const withText = (selector: string, text: string) => () =>
    Array.from(document.querySelectorAll<HTMLElement>(selector)).find(
        (element) => shown(element) && element.textContent?.trim() === text,
    ) ?? null;

/**
 * A SectionCard by its title, so a page needs no extra markup to be toured; `last` when two cards share it.
 * A card taller than half the screen gives its header instead, or a phone has no room left for the tooltip.
 */
export const card =
    (title: string, last = false) =>
    (): HTMLElement | null => {
        const headings = Array.from(
            document.querySelectorAll<HTMLElement>('section h2'),
        ).filter(
            (heading) =>
                shown(heading) && heading.textContent?.trim() === title,
        );
        const heading = last ? headings.at(-1) : headings[0];
        const section = heading?.closest('section');

        if (!heading || !section) {
            return null;
        }

        return section.offsetHeight > window.innerHeight / 2
            ? (section.querySelector('header') ?? heading)
            : section;
    };

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
 * A Joyride tour with the Lais tooltip. Rendered only while running, so the
 * server render never touches it. Only the stops this screen shows are kept
 * (Laporan sits in the menu on a phone), so "1 dari N" stays true.
 */
export function GuidedTour({
    steps: wanted,
    run,
    onDone,
}: {
    steps: Step[];
    run: boolean;
    onDone: () => void;
}) {
    if (!run) {
        return null;
    }

    const present = wanted.filter(
        (step) => typeof step.target !== 'function' || step.target() !== null,
    );

    if (present.length === 0) {
        return null;
    }

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
            // Every stop opens as a tooltip, also when the first planned one is not on this screen.
            steps={present.map((step) => ({ ...step, skipBeacon: true }))}
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
                    onDone();
                }
            }}
        />
    );
}
