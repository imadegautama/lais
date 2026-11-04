import { MessageCircle } from 'lucide-react';
import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { waLink } from '@/features/orders/phone';
import { reminderMessage } from './vocabulary';

/**
 * "Ingatkan" (UI-PAGES C11): a polite reminder the owner can edit, sent
 * from their own WhatsApp. The app never messages a customer itself.
 */
export function RemindDialog({
    name,
    phone,
    balance,
    since,
    businessName,
    size = 'default',
}: {
    name: string;
    phone: string;
    balance: number;
    since: string;
    businessName: string;
    size?: 'default' | 'sm';
}) {
    const id = useId();
    const initial = reminderMessage(name, balance, since, businessName);
    const [text, setText] = useState(initial);

    return (
        <Dialog onOpenChange={(open) => open && setText(initial)}>
            <DialogTrigger asChild>
                <Button type="button" variant="ghost" size={size}>
                    <MessageCircle aria-hidden />
                    Ingatkan
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Ingatkan {name}</DialogTitle>
                    <DialogDescription>
                        Pesan dikirim dari WhatsApp Anda sendiri. Ubah dulu
                        kalau perlu.
                    </DialogDescription>
                </DialogHeader>
                <Label htmlFor={id} className="sr-only">
                    Isi pesan
                </Label>
                <Textarea
                    id={id}
                    value={text}
                    onChange={(event) => setText(event.target.value)}
                    rows={5}
                    maxLength={1000}
                />
                <Button asChild>
                    <a
                        href={waLink(phone, text)}
                        target="_blank"
                        rel="noreferrer"
                    >
                        <MessageCircle aria-hidden />
                        Buka WhatsApp
                    </a>
                </Button>
            </DialogContent>
        </Dialog>
    );
}
