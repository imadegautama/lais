import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { PeriodPicker } from '@/components/app/period-picker';
import { Button } from '@/components/ui/button';
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
    children,
}: {
    /** Omit to show `children` (the neraca's date) instead of the period. */
    frame?: ReportFrame;
    /** The overview has nothing worth printing. */
    printable?: boolean;
    children?: ReactNode;
}) {
    const { loading, failed, visit } = useReportQuery();

    return (
        <div className="flex flex-col items-start gap-2 md:items-end print:hidden">
            <div className="flex flex-wrap items-end gap-2">
                {frame ? (
                    <PeriodPicker
                        periods={frame.periods}
                        value={frame.period.key}
                        onChange={(key: PeriodKey) => visit({ periode: key })}
                        disabled={loading}
                    />
                ) : (
                    children
                )}
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
            {failed && (
                <p role="alert" className="text-sm text-destructive">
                    Laporan gagal dimuat. Periksa internet, lalu pilih
                    periodenya lagi.
                </p>
            )}
        </div>
    );
}
