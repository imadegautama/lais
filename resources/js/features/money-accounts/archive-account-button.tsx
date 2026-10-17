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
import { destroy } from '@/routes/money-accounts';
import type { MoneyAccountOption } from '@/types/domain';

/**
 * Archive a rekening after a confirmation. The server refuses while money is
 * still in it (move it out with Pindah dana first) and keeps the last one.
 */
export function ArchiveAccountButton({
    account,
}: {
    account: MoneyAccountOption;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-destructive hover:text-destructive"
                    // Every row has one, so each says which rekening it archives.
                    aria-label={`Arsipkan ${account.name}`}
                >
                    <Archive aria-hidden />
                    Arsipkan
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Arsipkan {account.name}?</DialogTitle>
                    <DialogDescription>
                        {account.name} tidak bisa dipilih lagi saat mencatat.
                        Catatan lamanya tetap tersimpan dan tetap menyebut
                        namanya.
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
                            router.delete(destroy.url(account.id), {
                                preserveScroll: true,
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
