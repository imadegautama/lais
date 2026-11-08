import { Link, usePage } from '@inertiajs/react';
import { LogoWordmark } from '@/components/logo-wordmark';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

export function LandingHeader({ hasShop }: { hasShop: boolean }) {
    const { auth } = usePage().props;

    return (
        <header className="absolute inset-x-0 top-0 z-20 px-2 pt-2 md:px-4 md:pt-4">
            <div className="mx-auto flex max-w-[1280px] items-center justify-between gap-3 px-4 py-4 md:px-8">
                <Link
                    href={home()}
                    className="inline-flex items-center rounded-sm pointer-coarse:min-h-11"
                >
                    <LogoWordmark className="h-7" />
                </Link>
                <nav
                    aria-label="Halaman ini"
                    className="hidden items-center gap-6 text-sm font-medium lg:flex"
                >
                    <a href="#cara-kerja" className="hover:underline">
                        Cara kerja
                    </a>
                    {hasShop && (
                        <a href="#toko-contoh" className="hover:underline">
                            Demo Toko
                        </a>
                    )}
                </nav>
                <div className="flex items-center gap-1.5">
                    {auth.user ? (
                        <Button asChild>
                            <Link href={dashboard()}>Buka dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild variant="ghost">
                                <Link href={login()}>Masuk</Link>
                            </Button>
                            <Button asChild>
                                <Link href={register()}>Daftar gratis</Link>
                            </Button>
                        </>
                    )}
                </div>
            </div>
        </header>
    );
}
