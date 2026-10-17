import { MessageCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { SoonTag } from '@/components/app/action-button';
import { transactionTypes } from '@/components/app/badges';
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
import type { TransactionType } from '@/types/domain';

const hints: Record<TransactionType, string> = {
    penjualan: 'Jualan tunai, QRIS, atau yang belum dibayar',
    pembelian: 'Bahan baku, barang dagangan, atau peralatan',
    biaya: 'Listrik, gas, sewa, upah karyawan',
    utang_piutang: 'Terima bayaran pelanggan atau bayar supplier',
    modal: 'Setor modal atau ambil uang untuk rumah',
};

// TODO(C3): link each option to /transaksi/baru?jenis=… once the form exists.
function Options() {
    return (
        <div className="flex flex-col gap-4">
            <ul className="flex flex-col divide-y rounded-lg border">
                {(Object.keys(transactionTypes) as TransactionType[]).map(
                    (type) => {
                        const { label, icon: Icon } = transactionTypes[type];

                        return (
                            <li
                                key={type}
                                aria-disabled="true"
                                className="flex min-h-14 items-center gap-3 px-3 py-2.5"
                            >
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-muted-foreground">
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
                                <SoonTag />
                            </li>
                        );
                    },
                )}
            </ul>
            <p className="flex gap-2 rounded-lg bg-secondary px-3 py-2.5 text-sm">
                <MessageCircle
                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                    aria-hidden
                />
                <span>
                    Nanti bisa juga lewat WhatsApp: kirim{' '}
                    <q className="font-semibold">jual 3 nasgor 2 es teh qris</q>{' '}
                    ke nomor bot.
                </span>
            </p>
        </div>
    );
}

/** The "+ Catat" entry point. Drawer on phones, dialog on larger screens. */
export function QuickRecordSheet({ trigger }: { trigger: ReactNode }) {
    const isMobile = useIsMobile();
    const title = 'Catat apa?';
    const description = 'Pilih jenis catatan. Formulirnya sedang dibangun.';

    if (isMobile) {
        return (
            <Drawer>
                <DrawerTrigger asChild>{trigger}</DrawerTrigger>
                <DrawerContent>
                    <DrawerHeader className="text-left">
                        <DrawerTitle>{title}</DrawerTitle>
                        <DrawerDescription>{description}</DrawerDescription>
                    </DrawerHeader>
                    <div className="px-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                        <Options />
                    </div>
                </DrawerContent>
            </Drawer>
        );
    }

    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <Options />
            </DialogContent>
        </Dialog>
    );
}
