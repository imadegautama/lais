import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Where the owner is in a multi-step form (onboarding). Phones and tablets
 * get one line, "Langkah 2 dari 6"; from lg every step shows, in one row.
 */
export function Stepper({
    steps,
    current,
}: {
    steps: ReadonlyArray<{ key: string; label: string }>;
    current: number;
}) {
    return (
        <nav aria-label="Langkah pengisian">
            <p className="text-sm text-muted-foreground lg:hidden">
                Langkah {current + 1} dari {steps.length}:{' '}
                <span className="font-semibold text-foreground">
                    {steps[current]?.label}
                </span>
            </p>
            <ol className="hidden items-center gap-x-5 lg:flex">
                {steps.map((step, index) => {
                    const done = index < current;

                    return (
                        <li
                            key={step.key}
                            aria-current={
                                index === current ? 'step' : undefined
                            }
                            className="flex items-center gap-2"
                        >
                            <span
                                className={cn(
                                    'flex size-7 items-center justify-center rounded-md border bg-card text-xs font-semibold',
                                    done &&
                                        'border-primary bg-primary text-primary-foreground',
                                    index === current &&
                                        'border-brand-strong text-foreground',
                                    index > current && 'text-muted-foreground',
                                )}
                            >
                                {done ? (
                                    <Check className="size-4" aria-hidden />
                                ) : (
                                    index + 1
                                )}
                                {done && (
                                    <span className="sr-only">selesai:</span>
                                )}
                            </span>
                            <span
                                className={cn(
                                    'text-sm',
                                    index === current
                                        ? 'font-semibold text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {step.label}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
