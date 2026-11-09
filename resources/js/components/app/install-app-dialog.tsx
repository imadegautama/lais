import { Share, SquarePlus } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { closeIosSteps, useIosSteps } from './install-app';

/**
 * Safari on an iPhone has no install prompt: these are the steps it takes.
 * Mounted once per layout; opened by installApp().
 */
export function InstallAppDialog() {
    const open = useIosSteps();

    return (
        <Dialog open={open} onOpenChange={(next) => !next && closeIosSteps()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Pasang Lais di iPhone</DialogTitle>
                    <DialogDescription>
                        Lais lalu terbuka dari layar utama, layar penuh seperti
                        aplikasi.
                    </DialogDescription>
                </DialogHeader>
                <ol className="flex list-decimal flex-col gap-3 pl-5 text-sm">
                    <li>
                        Ketuk{' '}
                        <Share
                            className="inline size-4 text-brand-strong"
                            aria-label="Bagikan"
                        />{' '}
                        <strong>Bagikan</strong> di bilah bawah Safari.
                    </li>
                    <li>
                        Pilih{' '}
                        <SquarePlus
                            className="inline size-4 text-brand-strong"
                            aria-hidden
                        />{' '}
                        <strong>Tambah ke Layar Utama</strong>.
                    </li>
                    <li>
                        Ketuk <strong>Tambah</strong> di pojok kanan atas.
                    </li>
                </ol>
            </DialogContent>
        </Dialog>
    );
}
