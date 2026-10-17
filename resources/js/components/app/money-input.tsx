import { useId, useState } from 'react';
import { Input } from '@/components/ui/input';
import { parseMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

type MoneyInputProps = {
    label: string;
    value: number | null;
    onChange: (value: number | null) => void;
    placeholder?: string;
    className?: string;
};

const grouped = (value: number) => new Intl.NumberFormat('id-ID').format(value);

/**
 * Accepts the way owners write money ("15rb", "1,5jt", "15.000") and
 * shows the parsed amount, so a typo is visible before saving.
 */
export function MoneyInput({
    label,
    value,
    onChange,
    placeholder = '15.000',
    className,
}: MoneyInputProps) {
    const id = useId();
    const [text, setText] = useState(value === null ? '' : grouped(value));
    const parsed = text.trim() === '' ? null : parseMoney(text);
    const invalid = text.trim() !== '' && parsed === null;

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <label htmlFor={id} className="text-sm font-medium">
                {label}
            </label>
            <div className="relative">
                <span
                    aria-hidden
                    className="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-muted-foreground"
                >
                    Rp
                </span>
                <Input
                    id={id}
                    inputMode="text"
                    autoComplete="off"
                    value={text}
                    placeholder={placeholder}
                    aria-invalid={invalid || undefined}
                    aria-describedby={`${id}-hint`}
                    className="num pl-10"
                    onChange={(event) => {
                        setText(event.target.value);
                        onChange(
                            event.target.value.trim() === ''
                                ? null
                                : parseMoney(event.target.value),
                        );
                    }}
                    onBlur={() => {
                        if (parsed !== null) {
                            setText(grouped(parsed));
                        }
                    }}
                />
            </div>
            <p
                id={`${id}-hint`}
                className={cn(
                    'text-xs',
                    invalid ? 'text-destructive' : 'text-muted-foreground',
                )}
            >
                {invalid
                    ? 'Tulis angka, misalnya 15000, 15rb, atau 1,5jt.'
                    : parsed !== null && !/^[\d.]+$/.test(text.trim())
                      ? `Terbaca Rp${grouped(parsed)}`
                      : 'Bisa ketik 15rb atau 1,5jt.'}
            </p>
        </div>
    );
}
