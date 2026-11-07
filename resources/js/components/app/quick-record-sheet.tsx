import { Link } from '@inertiajs/react';
import { ChevronRight, MessageCircle } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import {
    everydayTypes,
    transactionTypes,
    type EverydayType,
} from '@/components/app/badges';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import { useIsMobile } from '@/hooks/use-mobile';
import { create } from '@/routes/transactions';

const hints: Record<EverydayType, string> = {
    penjualan: 'Jualan tunai, QRIS, atau yang belum dibayar',
    pembelian: 'Bahan baku, barang dagangan, atau peralatan',
    biaya: 'Listrik, gas, sewa, upah karyawan',
    pemasukan_lain: 'Hibah, bunga bank, atau uang masuk selain penjualan',
    utang_piutang: 'Terima bayaran pelanggan atau bayar supplier',
    modal: 'Setor modal atau ambil uang untuk rumah',
    pindah_dana: 'Setor tunai ke bank, tarik tunai, atau antar rekening',
};

function Options({ onChoose }: { onChoose: () => void }) {
    return (
        <div className="flex flex-col gap-4">
            <ul className="flex flex-col divide-y rounded-lg border">
                {everydayTypes.map((type) => {
                    const { label, icon: Icon } = transactionTypes[type];

                    return (
                        <li key={type}>
                            <Link
                                href={create({ query: { jenis: type } })}
                                onClick={onChoose}
                                className="flex min-h-14 items-center gap-3 px-3 py-2.5 first:rounded-t-lg last:rounded-b-lg hover:bg-accent focus-visible:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-card text-brand-strong">
                                    <Icon className="size-[18px]" aria-hidden />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold">
                                        {label}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {hints[type]}
                                    </span>
                                </span>
                                <ChevronRight
                                    className="size-4 shrink-0 text-muted-foreground"
                                    aria-hidden
                                />
                            </Link>
                        </li>
                    );
                })}
            </ul>
            <p className="flex gap-2 rounded-lg bg-secondary px-3 py-2.5 text-sm">
                <MessageCircle
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-hidden
                />
                <span>
                    Bisa juga lewat chat: kirim{' '}
                    <q className="font-semibold">jual 3 nasgor 2 es teh qris</q>{' '}
                    ke bot Lais.
                </span>
            </p>
        </div>
    );
}

/** The "+ Catat" entry point. Drawer on phones, dialog on larger screens. */
export function QuickRecordSheet({ trigger }: { trigger: ReactNode }) {
    const isMobile = useIsMobile();
    // The sheet lives in the layout, so it has to close itself on navigation.
    const [open, setOpen] = useState(false);
    const title = 'Catat apa?';
    const description = 'Pilih jenis catatan.';

    if (isMobile) {
        return (
            <Drawer open={open} onOpenChange={setOpen}>
                <DrawerTrigger asChild>{trigger}</DrawerTrigger>
                <DrawerContent>
                    <DrawerHeader className="text-left">
                        <DrawerTitle>{title}</DrawerTitle>
                        <DrawerDescription>{description}</DrawerDescription>
                    </DrawerHeader>
                    <div className="px-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                        <Options onChoose={() => setOpen(false)} />
                    </div>
                </DrawerContent>
            </Drawer>
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Options onChoose={() => setOpen(false)} />
            </DialogContent>
        </Dialog>
    );
}
