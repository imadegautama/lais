import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';
import { readiness, STEPS } from './onboarding';

/**
 * "Buku usahamu N% siap": the setup's progress as a meter and a row of steps
 * that turn green when done (UI-PAGES B5). Replaces the plain Stepper here.
 */
export function SetupProgress({ current }: { current: number }) {
    const percent = readiness(current);

    return (
        <div className="flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-card">
            <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <p className="text-sm font-semibold">
                    Buku usahamu <span className="num">{percent}%</span> siap
                </p>
                <p className="text-xs text-muted-foreground">
                    Langkah {current + 1} dari {STEPS.length}
                </p>
            </div>
            <div
                role="progressbar"
                aria-label="Kesiapan buku usaha"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
                className="h-2 overflow-hidden rounded-full bg-secondary"
            >
                {/* Scaled, not resized, so the fill moves on transform alone. */}
                <div
                    className="h-full w-full origin-left rounded-full bg-brand-strong transition-transform duration-500 ease-out motion-reduce:transition-none"
                    style={{ transform: `scaleX(${percent / 100})` }}
                />
            </div>
            <ol className="flex flex-wrap gap-1.5">
                {STEPS.map((step, index) => {
                    const done = index < current;

                    return (
                        <li
                            key={step.key}
                            aria-current={
                                index === current ? 'step' : undefined
                            }
                            className={cn(
                                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium',
                                done && 'bg-success-soft text-success',
                                index === current &&
                                    'bg-brand-soft text-primary-text',
                                index > current &&
                                    'bg-secondary text-muted-foreground',
                            )}
                        >
                            {done ? (
                                <Check className="size-3" aria-hidden />
                            ) : (
                                <span className="num">{index + 1}</span>
                            )}
                            {step.label}
                            {done && (
                                <span className="sr-only"> (selesai)</span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
