import { Head, router, usePoll } from '@inertiajs/react';
import { BellRing, CircleCheck, MessageCircle, Unlink } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { SectionCard } from '@/components/app/section-card';
import { SwitchRow } from '@/components/app/switch-row';
import { Button } from '@/components/ui/button';
import { displayPhone } from '@/features/orders/phone';
import { formatDate } from '@/lib/format';
import { settings } from '@/routes';
import { code, edit, summary, unlink } from '@/routes/whatsapp';

type BotSettingsProps = {
    /** The chat app the bot runs on (BOT_CHANNEL). */
    channel: string;
    /** How owners find the bot; null until it is set up. */
    contact: string | null;
    /** The owner's number, on WhatsApp only. */
    linked: { phone: string | null; at: string } | null;
    /** The code waiting to be sent to the bot, and the link that sends it. */
    pending: { code: string; expiresAt: string; url: string | null } | null;
    dailySummary: boolean;
};

/**
 * Pengaturan > Bot (UI-PAGES C20). The owner links their chat account by
 * sending a code to the bot from their own phone; the page watches for it.
 */
export default function BotSettings({
    channel,
    contact,
    linked,
    pending,
    dailySummary,
}: BotSettingsProps) {
    const id = useId();
    const [busy, setBusy] = useState(false);
    // Waits for the code to arrive at the bot, then the page shows the link.
    const { start, stop } = usePoll(
        5_000,
        { only: ['linked', 'pending'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (pending && !linked) {
            start();
        } else {
            stop();
        }
    }, [pending, linked, start, stop]);

    const message = pending ? `LAIS ${pending.code}` : '';

    return (
        <>
            <Head title="Bot" />
            <SectionCard
                title={`Bot ${channel}`}
                description="Catat transaksi, kelola pesanan, dan terima ringkasan malam lewat chat dengan bot Lais."
                icon={MessageCircle}
            >
                {linked ? (
                    <div className="flex flex-col gap-3">
                        <p className="flex items-center gap-2 text-sm font-semibold text-success">
                            <CircleCheck className="size-4" aria-hidden />
                            Terhubung
                        </p>
                        <p className="text-sm">
                            {linked.phone
                                ? displayPhone(linked.phone)
                                : `Akun ${channel} kamu`}{' '}
                            <span className="text-muted-foreground">
                                sejak {formatDate(linked.at, 'short')}
                            </span>
                        </p>
                        {contact && (
                            <p className="text-sm text-muted-foreground">
                                Chat bot di {contact}, misalnya "jual 3 nasi
                                goreng tunai".
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="w-fit"
                            onClick={() =>
                                router.delete(unlink.url(), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            <Unlink aria-hidden />
                            Putuskan
                        </Button>
                    </div>
                ) : !contact ? (
                    <p
                        role="status"
                        className="rounded-lg bg-warning-soft px-4 py-3 text-sm text-warning"
                    >
                        Bot {channel} belum disiapkan di server ini. Langkahnya
                        ada di docs/{channel.toUpperCase()}.md.
                    </p>
                ) : (
                    <div className="flex flex-col gap-3">
                        <ol className="flex list-decimal flex-col gap-1 pl-5 text-sm">
                            <li>Buat kode di bawah.</li>
                            <li>
                                Ketuk Buka {channel} dari HP kamu, lalu{' '}
                                {channel === 'Telegram'
                                    ? 'tekan Mulai di chat bot'
                                    : 'kirim kodenya ke bot'}{' '}
                                {contact}.
                            </li>
                            <li>
                                Halaman ini berubah sendiri setelah terhubung.
                            </li>
                        </ol>
                        {pending ? (
                            <div className="flex flex-col gap-3">
                                <p
                                    id={`${id}-code`}
                                    className="num text-2xl font-bold tracking-wide"
                                >
                                    {message}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Berlaku sampai{' '}
                                    {formatDate(pending.expiresAt, 'time')}.
                                    Menunggu pesan dari kamu…
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {pending.url && (
                                        <Button asChild>
                                            <a
                                                href={pending.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                aria-describedby={`${id}-code`}
                                            >
                                                <MessageCircle aria-hidden />
                                                Buka {channel}
                                            </a>
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={busy}
                                        onClick={() =>
                                            router.post(code.url(), undefined, {
                                                preserveScroll: true,
                                                onStart: () => setBusy(true),
                                                onFinish: () => setBusy(false),
                                            })
                                        }
                                    >
                                        Buat kode baru
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <Button
                                type="button"
                                className="w-fit"
                                disabled={busy}
                                onClick={() =>
                                    router.post(code.url(), undefined, {
                                        preserveScroll: true,
                                        onStart: () => setBusy(true),
                                        onFinish: () => setBusy(false),
                                    })
                                }
                            >
                                {busy ? 'Membuat kode…' : 'Buat kode'}
                            </Button>
                        )}
                    </div>
                )}
            </SectionCard>

            <SectionCard
                title="Ringkasan harian"
                description="Kabar usaha tiap malam, dikirim bot ke chat kamu."
                icon={BellRing}
            >
                <SwitchRow
                    id={`${id}-summary`}
                    label="Ringkasan harian jam 21.00"
                    hint="Penjualan, laba, kas, pesanan belum selesai, dan stok yang mau habis, dikirim ke chat bot kamu."
                    checked={dailySummary}
                    onChange={(on) =>
                        router.patch(
                            summary.url(),
                            { daily_summary: on },
                            { preserveScroll: true },
                        )
                    }
                />
            </SectionCard>
        </>
    );
}

BotSettings.layout = {
    breadcrumbs: [
        { title: 'Pengaturan', href: settings() },
        { title: 'Bot', href: edit() },
    ],
};
