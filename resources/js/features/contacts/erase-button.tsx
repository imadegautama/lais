import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/contacts';

/** Remove a person's data after saying plainly what stays. */
export function EraseButton({ id, name }: { id: number; name: string }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="-ml-3 w-fit text-destructive hover:text-destructive"
                >
                    <Trash2 aria-hidden />
                    Hapus data kontak
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Hapus data {name}?</DialogTitle>
                    <DialogDescription>
                        Nama dan nomor WA-nya dihapus dari daftar kontak dan
                        pesanan, dan tidak bisa dikembalikan. Catatan
                        keuangannya tetap ada, jadi laporan tidak berubah. Nama
                        yang sudah tertulis di keterangan catatan lama dan log
                        aktivitas tidak ikut terhapus.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Batal
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={busy}
                        onClick={() =>
                            router.delete(destroy.url(id), {
                                onStart: () => setBusy(true),
                                onFinish: () => {
                                    setBusy(false);
                                    setOpen(false);
                                },
                            })
                        }
                    >
                        {busy ? 'Menghapus…' : 'Hapus data'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
