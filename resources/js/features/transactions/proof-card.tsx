import { ExternalLink, FileImage } from 'lucide-react';
import { SectionCard } from '@/components/app/section-card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/**
 * Bukti (UI-PAGES C4): the receipt or transfer screenshot, opened larger in
 * place. The URL is a route that checks the owner, never a public file.
 */
export function ProofCard({ url }: { url: string }) {
    return (
        <SectionCard title="Bukti" icon={FileImage}>
            <div className="flex items-start gap-4">
                <Dialog>
                    <DialogTrigger asChild>
                        <button
                            type="button"
                            aria-label="Perbesar bukti"
                            className="size-28 shrink-0 overflow-hidden rounded-lg border bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <img
                                src={url}
                                alt="Bukti transaksi"
                                loading="lazy"
                                className="size-full object-contain"
                            />
                        </button>
                    </DialogTrigger>
                    <DialogContent className="sm:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>Bukti transaksi</DialogTitle>
                            <DialogDescription>
                                Hanya bisa dibuka pemilik usaha.
                            </DialogDescription>
                        </DialogHeader>
                        <img
                            src={url}
                            alt="Bukti transaksi"
                            className="max-h-[70vh] w-full rounded-lg border bg-white object-contain"
                        />
                    </DialogContent>
                </Dialog>
                <Button variant="outline" size="sm" asChild>
                    <a href={url} target="_blank" rel="noreferrer">
                        <ExternalLink aria-hidden />
                        Buka ukuran asli
                    </a>
                </Button>
            </div>
        </SectionCard>
    );
}
