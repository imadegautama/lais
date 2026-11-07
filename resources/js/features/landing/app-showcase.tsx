import { useState } from 'react';
import { ChoiceGroup } from '@/components/app/choice-group';
import { DemoDataBadge } from '@/components/app/demo-data-badge';
import { cn } from '@/lib/utils';
import { appScreens } from './examples';
import { heading } from './landing-sections';
import { delay, WordReveal } from './word-reveal';

type ScreenId = (typeof appScreens)[number]['id'];

/** The web app on a laptop, picked tab by tab. Nothing turns by itself. */
export function AppShowcase() {
    const [id, setId] = useState<ScreenId>('dashboard');
    const current =
        appScreens.find((screen) => screen.id === id) ?? appScreens[0];

    return (
        <section
            aria-labelledby="aplikasi"
            className="px-2 py-8 md:px-4 md:py-10"
        >
            <div className="mx-auto max-w-[1400px] rounded-3xl bg-landing-glow px-4 py-10 md:px-8 md:py-14">
                <div className="mx-auto max-w-2xl text-center">
                    <h2 id="aplikasi" data-reveal="words" className={heading}>
                        <WordReveal text="Semua angka usahamu di satu layar" />
                    </h2>
                    <p data-reveal="up" style={delay(200)} className="mt-3">
                        Yang dicatat lewat chat langsung tampil di web. Ini
                        tampilan aslinya.
                    </p>
                </div>
                <div
                    data-reveal="up"
                    style={delay(300)}
                    className="mx-auto mt-6 w-fit max-w-full"
                >
                    <ChoiceGroup
                        wrap
                        label="Halaman aplikasi"
                        value={id}
                        options={appScreens.map(({ id: value, label }) => ({
                            value,
                            label,
                        }))}
                        onChange={setId}
                    />
                </div>
                <div className="mt-4 flex flex-wrap items-center justify-center gap-x-3 gap-y-2 text-center">
                    <p aria-live="polite" className="text-sm">
                        {current.caption}
                    </p>
                    <DemoDataBadge />
                </div>

                <div data-reveal="up" className="mx-auto mt-8 max-w-5xl">
                    <div className="landing-laptop">
                        <div className="landing-laptop-lid">
                            <div className="landing-laptop-bezel">
                                <div className="relative aspect-[16/10] overflow-hidden rounded-sm bg-card">
                                    {appScreens.map((screen) => (
                                        <Screen
                                            key={screen.id}
                                            id={screen.id}
                                            alt={screen.alt}
                                            shown={screen.id === id}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                        <div aria-hidden className="landing-laptop-base" />
                    </div>
                </div>
            </div>
        </section>
    );
}

function Screen({
    id,
    alt,
    shown,
}: {
    id: ScreenId;
    alt: string;
    shown: boolean;
}) {
    const file = `/images/landing/app-${id}`;

    return (
        <img
            src={`${file}-1600.webp`}
            srcSet={`${file}-800.webp 800w, ${file}-1600.webp 1600w`}
            sizes="(min-width: 1024px) 900px, 90vw"
            width={1600}
            height={1000}
            loading="lazy"
            alt={shown ? alt : ''}
            aria-hidden={!shown}
            className={cn(
                'absolute inset-0 size-full object-cover transition-opacity duration-300',
                shown ? 'opacity-100' : 'opacity-0',
            )}
        />
    );
}
