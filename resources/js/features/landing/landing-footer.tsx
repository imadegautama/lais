import { Link, usePage } from '@inertiajs/react';
import { login, privacy, register, terms } from '@/routes';

const link =
    'inline-flex items-center justify-center hover:text-foreground pointer-coarse:min-h-11 pointer-coarse:min-w-11';

export function LandingFooter({ hasShop }: { hasShop: boolean }) {
    const { auth } = usePage().props;

    return (
        <footer className="mx-auto flex w-full max-w-[1280px] flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-6 text-sm text-muted-foreground md:px-8">
            <p>© 2026 Lais</p>
            <nav aria-label="Tautan bawah" className="flex flex-wrap gap-x-5">
                <a href="#cara-kerja" className={link}>
                    Cara kerja
                </a>
                {hasShop && (
                    <a href="#toko-contoh" className={link}>
                        Toko contoh
                    </a>
                )}
                <Link href={privacy()} className={link}>
                    Privasi
                </Link>
                <Link href={terms()} className={link}>
                    Syarat
                </Link>
                {!auth.user && (
                    <>
                        <Link href={login()} className={link}>
                            Masuk
                        </Link>
                        <Link href={register()} className={link}>
                            Daftar gratis
                        </Link>
                    </>
                )}
            </nav>
        </footer>
    );
}
