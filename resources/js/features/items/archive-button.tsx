import { router } from '@inertiajs/react';
import { Archive } from 'lucide-react';
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
import { destroy } from '@/routes/items';

/** Archive an item after a confirmation; its history stays. */
export function ArchiveButton({ id, name }: { id: number; name: string }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="text-destructive hover:text-destructive lg:ml-auto"
                >
                    <Archive aria-hidden />
                    Arsipkan
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Arsipkan {name}?</DialogTitle>
                    <DialogDescription>
                        {name} hilang dari daftar produk dan halaman toko.
                        Catatan penjualan dan stok lamanya tetap tersimpan.
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
                        {busy ? 'Mengarsipkan…' : 'Arsipkan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
