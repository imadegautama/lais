import { Head, Link } from '@inertiajs/react';
import { Clock, FileQuestion, Lock, ServerCrash, Wrench } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { EmptyState } from '@/components/app/states';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

const pages = {
    403: {
        icon: Lock,
        title: 'Halaman ini bukan untuk akun kamu',
        description:
            'Kalau kamu merasa berhak membukanya, masuk dengan akun usaha yang benar.',
    },
    404: {
        icon: FileQuestion,
        title: 'Halaman tidak ditemukan',
        description:
            'Link-nya mungkin salah ketik, atau halamannya sudah tidak ada.',
    },
    419: {
        icon: Clock,
        title: 'Sesi kamu sudah habis',
        description:
            'Halaman terlalu lama dibiarkan terbuka. Muat ulang, lalu coba lagi.',
    },
    500: {
        icon: ServerCrash,
        title: 'Ada masalah di sistem kami',
        description:
            'Catatan yang sudah tersimpan tetap aman. Coba lagi sebentar lagi.',
    },
    503: {
        icon: Wrench,
        title: 'Lais sedang dirawat',
        description:
            'Sebentar lagi bisa dipakai kembali. Catatanmu tetap aman.',
    },
} as const;

type Status = keyof typeof pages;

/** One page for every error the owner or a customer can meet (UI-PAGES D1). */
export default function ErrorPage({ status }: { status: Status }) {
    const page = pages[status] ?? pages[500];

    return (
        <>
            <Head title={page.title} />
            <main className="flex min-h-dvh flex-col items-center justify-center gap-6 bg-background p-4">
                <AppLogo />
                <EmptyState
                    variant="page"
                    className="w-full max-w-md"
                    icon={page.icon}
                    title={page.title}
                    description={page.description}
                    action={
                        status === 419 ? (
                            <Button
                                type="button"
                                onClick={() => window.location.reload()}
                            >
                                Muat ulang halaman
                            </Button>
                        ) : (
                            <Button asChild>
                                <Link href={home()}>Kembali ke beranda</Link>
                            </Button>
                        )
                    }
                />
            </main>
        </>
    );
}
