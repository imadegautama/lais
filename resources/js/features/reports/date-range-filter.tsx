import { CalendarRange } from 'lucide-react';
import { useId, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import type { ReportFrame } from '@/types/domain';

/** The owner's own dates: a popover beside the quick periods, a drawer on a phone. */
export function DateRangeFilter({
    frame,
    disabled,
    onApply,
}: {
    frame: ReportFrame;
    disabled: boolean;
    onApply: (from: string, to: string) => void;
}) {
    const id = useId();
    const isMobile = useIsMobile();
    const [open, setOpen] = useState(false);
    const [from, setFrom] = useState(frame.range.from);
    const [to, setTo] = useState(frame.range.to);
    const today = new Date().toLocaleDateString('en-CA', {
        timeZone: 'Asia/Jakarta',
    });
    const custom = frame.period.key === null;
    const invalid = from === '' || to === '' || from > to;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!invalid) {
            setOpen(false);
            onApply(from, to);
        }
    };

    const trigger = (
        <Button
            type="button"
            variant="outline"
            disabled={disabled}
            className={cn(
                custom &&
                    'border-primary bg-accent font-semibold text-accent-foreground',
            )}
        >
            <CalendarRange aria-hidden />
            {custom ? frame.period.label : 'Pilih tanggal'}
        </Button>
    );

    const fields = (
        <form onSubmit={submit} className="flex flex-col gap-3">
            <div className="grid grid-cols-2 gap-3">
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${id}-from`}>Dari</Label>
                    <Input
                        id={`${id}-from`}
                        type="date"
                        value={from}
                        max={to || today}
                        min={frame.startedOn ?? undefined}
                        onChange={(event) => setFrom(event.target.value)}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={`${id}-to`}>Sampai</Label>
                    <Input
                        id={`${id}-to`}
                        type="date"
                        value={to}
                        min={from || undefined}
                        max={today}
                        onChange={(event) => setTo(event.target.value)}
                    />
                </div>
            </div>
            {invalid && (
                <p role="alert" className="text-sm text-destructive">
                    Tanggal awal tidak boleh setelah tanggal akhir.
                </p>
            )}
            <Button type="submit" disabled={invalid}>
                Tampilkan
            </Button>
        </form>
    );

    if (isMobile) {
        return (
            <Drawer open={open} onOpenChange={setOpen}>
                <DrawerTrigger asChild>{trigger}</DrawerTrigger>
                <DrawerContent>
                    <DrawerHeader className="text-left">
                        <DrawerTitle>Pilih tanggal</DrawerTitle>
                        <DrawerDescription>
                            Laporan akan menampilkan rentang ini, dibandingkan
                            dengan rentang yang sama sebelumnya.
                        </DrawerDescription>
                    </DrawerHeader>
                    <div className="p-4 pt-0">{fields}</div>
                </DrawerContent>
            </Drawer>
        );
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>{trigger}</PopoverTrigger>
            <PopoverContent align="end" className="w-80">
                {fields}
            </PopoverContent>
        </Popover>
    );
}
