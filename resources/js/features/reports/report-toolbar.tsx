import { router } from '@inertiajs/react';
import { MessageCircleQuestion, Printer } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { useAssistant } from '@/components/app/assistant/assistant-provider';
import { PeriodPicker } from '@/components/app/period-picker';
import { Button } from '@/components/ui/button';
import { DateRangeFilter } from '@/features/reports/date-range-filter';
import type { ReportFrame } from '@/types/domain';
import type { Period as PeriodKey } from '@/types/enums';

/** Reload the open report with other query values, keeping the scroll. */
export function useReportQuery() {
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    const visit = (query: Record<string, string>) =>
        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                setFailed(false);
                setLoading(true);
            },
            onHttpException: () => {
                setFailed(true);

                return false;
            },
            onNetworkError: () => {
                setFailed(true);

                return false;
            },
            onFinish: () => setLoading(false),
        });

    return { loading, failed, visit };
}

/** The period (or date) for the report, and printing it; hidden on paper. */
export function ReportToolbar({
    frame,
    printable = true,
    extra = {},
    report,
    children,
}: {
    /** Omit to show `children` (the neraca's date) instead of the period. */
    frame?: ReportFrame;
    /** The overview has nothing worth printing. */
    printable?: boolean;
    /** Other filters of the page, kept when the dates change. */
    extra?: Record<string, string>;
    /** The page's own useReportQuery, when it also reloads the report itself. */
    report?: ReturnType<typeof useReportQuery>;
    children?: ReactNode;
}) {
    const own = useReportQuery();
    const { loading, failed, visit } = report ?? own;
    const assistant = useAssistant();

    return (
        <div className="flex flex-col items-start gap-2 md:items-end print:hidden">
            <div className="flex flex-wrap items-end gap-2">
                {frame ? (
                    <>
                        <PeriodPicker
                            periods={frame.periods}
                            value={frame.period.key ?? ''}
                            onChange={(key: PeriodKey | '') =>
                                key && visit({ ...extra, periode: key })
                            }
                            disabled={loading}
                        />
                        <DateRangeFilter
                            frame={frame}
                            disabled={loading}
                            onApply={(dari, sampai) =>
                                visit({ ...extra, dari, sampai })
                            }
                        />
                    </>
                ) : (
                    children
                )}
                {/* The panel knows which report and period are open (assistant-provider). */}
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => assistant.setOpen(true)}
                >
                    <MessageCircleQuestion
                        className="text-brand-strong"
                        aria-hidden
                    />
                    Tanya AI
                </Button>
                {printable && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => window.print()}
                    >
                        <Printer aria-hidden />
                        Cetak / simpan PDF
                    </Button>
                )}
            </div>
            {loading && (
                <p role="status" className="text-sm text-muted-foreground">
                    Memuat laporan…
                </p>
            )}
            {failed && (
                <p role="alert" className="text-sm text-destructive">
                    Laporan gagal dimuat. Periksa internet, lalu pilih
                    periodenya lagi.
                </p>
            )}
        </div>
    );
}
