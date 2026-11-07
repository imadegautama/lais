import { ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { shrinkPhoto } from '@/lib/image';
import { cn } from '@/lib/utils';

/**
 * One photo with its preview, choose or replace, and remove: a product's
 * shop photo, or a transaction's proof. Shrunk in the browser first, so a
 * phone photo of several MB goes up small.
 */
export function ImagePicker({
    current,
    file,
    error,
    onFile,
    onRemove,
    maxSide,
    alt,
    fit = 'cover',
    noun = 'foto',
}: {
    /** The saved photo, unless the owner just removed it. */
    current: string | null;
    file: File | null;
    error?: string;
    onFile: (file: File) => void;
    onRemove: () => void;
    /** Longest side after shrinking: 800 for a shop photo, 1600 keeps a transfer's digits readable. */
    maxSide: number;
    alt: string;
    /** "contain" shows a whole screenshot instead of cropping it. */
    fit?: 'cover' | 'contain';
    /** Used in the button labels: "Pilih foto", "Ganti bukti". */
    noun?: string;
}) {
    const id = useId();
    const [preview, setPreview] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!file) {
            setPreview(null);

            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const shown = preview ?? current;

    return (
        <div className="flex items-start gap-4">
            <div className="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-secondary">
                {shown ? (
                    <img
                        src={shown}
                        alt={alt}
                        className={cn(
                            'size-full',
                            fit === 'cover'
                                ? 'object-cover'
                                : 'bg-white object-contain',
                        )}
                    />
                ) : (
                    <ImagePlus
                        className="size-6 text-muted-foreground"
                        aria-hidden
                    />
                )}
            </div>
            <div className="flex min-w-0 flex-col items-start gap-2">
                <Button type="button" variant="outline" size="sm" asChild>
                    <label htmlFor={id} className="cursor-pointer">
                        <ImagePlus aria-hidden />
                        {busy
                            ? 'Menyiapkan foto…'
                            : shown
                              ? `Ganti ${noun}`
                              : `Pilih ${noun}`}
                    </label>
                </Button>
                <input
                    id={id}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="sr-only"
                    onChange={async (event) => {
                        const picked = event.target.files?.[0];
                        event.target.value = '';

                        if (picked) {
                            setBusy(true);
                            onFile(await shrinkPhoto(picked, maxSide));
                            setBusy(false);
                        }
                    }}
                />
                {shown && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive"
                        onClick={onRemove}
                    >
                        <Trash2 aria-hidden />
                        Hapus {noun}
                    </Button>
                )}
                <InputError message={error} />
            </div>
        </div>
    );
}
