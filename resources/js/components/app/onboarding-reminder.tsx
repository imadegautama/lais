import { Link } from '@inertiajs/react';
import { Flag } from 'lucide-react';
import { IconChip } from '@/components/app/section-card';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/onboarding';

/** Shown until the books are opened: balances are only right once the saldo awal is in. */
export function OnboardingReminder() {
    return (
        <section className="flex flex-col gap-3 rounded-xl border bg-card p-4 text-card-foreground shadow-card sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-start gap-3">
                <IconChip icon={Flag} tone="focus" />
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-sm font-semibold">
                        Saldo awal belum diisi
                    </h2>
                    <p className="max-w-prose text-sm text-muted-foreground">
                        Isi uang, peralatan, stok, dan utang-piutang yang sudah
                        ada saat mulai, supaya saldo kas dan neraca sesuai
                        kenyataan.
                    </p>
                </div>
            </div>
            <Button
                variant="outline"
                asChild
                className="self-start sm:self-center"
            >
                <Link href={show()}>Lanjutkan pengisian</Link>
            </Button>
        </section>
    );
}
