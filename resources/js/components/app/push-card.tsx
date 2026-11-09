import { router } from '@inertiajs/react';
import { Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { SectionCard } from '@/components/app/section-card';
import { Button } from '@/components/ui/button';
import { vapidKeyBytes } from '@/lib/push';
import { destroy, store } from '@/routes/push';
import { useInstallWay } from './install-app';

type State = 'checking' | 'unsupported' | 'denied' | 'failed' | 'off' | 'on';

/** The service worker that shows the notifications (public/sw.js), registered when needed. */
async function worker(): Promise<ServiceWorkerRegistration> {
    await navigator.serviceWorker.register('/sw.js');

    return navigator.serviceWorker.ready;
}

/**
 * "Notifikasi di HP ini": web push for new orders on the browser the owner
 * is on, next to the bot. Each phone or browser is turned on by itself.
 */
export function PushCard({ pushKey }: { pushKey: string }) {
    const [state, setState] = useState<State>('checking');
    const [busy, setBusy] = useState(false);
    // An iPhone only takes notifications once Lais is on its home screen.
    const needsInstall = useInstallWay() === 'ios';

    useEffect(() => {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            setState('unsupported');

            return;
        }

        if (Notification.permission === 'denied') {
            setState('denied');

            return;
        }

        void navigator.serviceWorker
            .getRegistration('/sw.js')
            .then((registration) => registration?.pushManager.getSubscription())
            .then((subscription) => setState(subscription ? 'on' : 'off'))
            .catch(() => setState('off'));
    }, []);

    const turnOn = async () => {
        setBusy(true);

        try {
            if ((await Notification.requestPermission()) !== 'granted') {
                setState('denied');

                return;
            }

            const subscription = await (
                await worker()
            ).pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: vapidKeyBytes(pushKey),
            });
            const { endpoint, keys } = subscription.toJSON();
            router.post(
                store.url(),
                {
                    endpoint: endpoint ?? '',
                    keys: {
                        p256dh: keys?.p256dh ?? '',
                        auth: keys?.auth ?? '',
                    },
                },
                {
                    preserveScroll: true,
                    onSuccess: () => setState('on'),
                },
            );
        } catch {
            // Incognito windows and some browsers refuse push without saying so up front.
            setState('failed');
        } finally {
            setBusy(false);
        }
    };

    const turnOff = async () => {
        setBusy(true);

        try {
            const subscription = await (
                await worker()
            ).pushManager.getSubscription();
            await subscription?.unsubscribe();
            router.delete(destroy.url(), {
                data: { endpoint: subscription?.endpoint ?? '' },
                preserveScroll: true,
                onSuccess: () => setState('off'),
            });
        } finally {
            setBusy(false);
        }
    };

    return (
        <SectionCard
            title="Notifikasi di HP ini"
            description="Pesanan baru dari toko online muncul sebagai notifikasi, walaupun Lais sedang ditutup."
            icon={Smartphone}
        >
            <div className="flex flex-col gap-3 text-sm">
                {state === 'on' && (
                    <>
                        <p className="font-semibold text-success">
                            Aktif di perangkat ini
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            className="self-start"
                            disabled={busy}
                            onClick={() => void turnOff()}
                        >
                            Matikan
                        </Button>
                    </>
                )}
                {state === 'off' &&
                    (needsInstall ? (
                        <p className="text-muted-foreground">
                            Di iPhone, pasang Lais di layar utama dulu (menu
                            akun, Pasang Lais di HP), lalu buka dari sana dan
                            aktifkan di sini.
                        </p>
                    ) : (
                        <Button
                            type="button"
                            className="self-start"
                            disabled={busy}
                            onClick={() => void turnOn()}
                        >
                            {busy ? 'Mengaktifkan…' : 'Aktifkan notifikasi'}
                        </Button>
                    ))}
                {state === 'failed' && (
                    <p className="text-muted-foreground">
                        Browser ini menolak notifikasi. Coba buka Lais di Chrome
                        biasa (bukan mode penyamaran) atau dari aplikasi yang
                        sudah dipasang. Kabar pesanan tetap masuk lewat bot.
                    </p>
                )}
                {state === 'denied' && (
                    <p className="text-muted-foreground">
                        Izin notifikasi ditolak di browser ini. Izinkan dari
                        pengaturan situs browser, lalu muat ulang halaman ini.
                    </p>
                )}
                {state === 'unsupported' && (
                    <p className="text-muted-foreground">
                        Browser ini belum mendukung notifikasi. Kabar pesanan
                        tetap masuk lewat bot.
                    </p>
                )}
            </div>
        </SectionCard>
    );
}
