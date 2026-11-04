import type { InertiaFormProps } from '@inertiajs/react';
import { useId } from 'react';
import { ChoiceGroup } from '@/components/app/choice-group';
import { Field } from '@/components/app/field';
import { QtyStepper } from '@/components/app/qty-stepper';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { fieldSetter, formErrors } from '@/lib/form';
import { formatRupiah } from '@/lib/format';
import { orderFulfillmentLabels } from '@/types/enums';
import type { OrderFulfillment } from '@/types/domain';
import { MAX_QTY, type CartLine } from './cart';

/** OrderRequest's fields; `lines` is filled from the cart when sending. */
export type OrderFormData = {
    customer_name: string;
    customer_phone: string;
    fulfillment: OrderFulfillment;
    address: string;
    wanted_at: string;
    note: string;
    /** Honeypot: hidden from people, filled by bots (PRD US-08). */
    website: string;
    lines: Array<{ item_id: number; qty: number }>;
};

export const emptyOrder: OrderFormData = {
    customer_name: '',
    customer_phone: '',
    fulfillment: 'ambil',
    address: '',
    wanted_at: '',
    note: '',
    website: '',
    lines: [],
};

const fulfillments = (['ambil', 'antar'] as const).map((value) => ({
    value,
    label: orderFulfillmentLabels[value],
}));

/** The items with their quantities and the total, editable in place. */
export function CartSummary({
    lines,
    onQty,
    errors,
}: {
    lines: CartLine[];
    onQty: (itemId: number, qty: number) => void;
    errors: Record<string, string | undefined>;
}) {
    const total = lines.reduce((sum, line) => sum + line.subtotal, 0);
    const lineError = Object.entries(errors).find(([key]) =>
        key.startsWith('lines'),
    )?.[1];

    return (
        <div className="flex flex-col gap-3">
            <ul className="flex flex-col divide-y">
                {lines.map((line) => (
                    <li
                        key={line.item.id}
                        className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 py-3 first:pt-0"
                    >
                        <div className="min-w-0 flex-1 basis-40">
                            <p className="text-sm font-medium">
                                {line.item.name}
                            </p>
                            <p className="num text-xs text-muted-foreground">
                                {formatRupiah(line.item.price)} /{' '}
                                {line.item.unit}
                            </p>
                        </div>
                        <QtyStepper
                            label={line.item.name}
                            value={line.qty}
                            min={0}
                            max={MAX_QTY}
                            onChange={(qty) => onQty(line.item.id, qty)}
                        />
                        <p className="num w-24 text-right text-sm font-semibold">
                            {formatRupiah(line.subtotal)}
                        </p>
                    </li>
                ))}
            </ul>
            <div className="flex items-baseline justify-between border-t pt-3">
                <span className="text-sm font-medium">Total</span>
                <span className="num text-lg font-semibold">
                    {formatRupiah(total)}
                </span>
            </div>
            {lineError && (
                <p
                    role="alert"
                    className="rounded-lg bg-destructive-soft px-3 py-2 text-sm text-destructive"
                >
                    {lineError}
                </p>
            )}
        </div>
    );
}

/** Who orders and how they get it (UI-PAGES A3); shared with the owner's own order form. */
export function CustomerFields({
    form,
    honeypot = false,
}: {
    form: InertiaFormProps<OrderFormData>;
    /** Only the public form needs the bot trap. */
    honeypot?: boolean;
}) {
    const id = useId();
    const errors = formErrors(form);
    const set = fieldSetter(form);

    return (
        <div className="flex flex-col gap-4">
            <Field
                label="Nama pemesan"
                htmlFor={`${id}-name`}
                error={errors.customer_name}
            >
                <Input
                    id={`${id}-name`}
                    value={form.data.customer_name}
                    onChange={(event) =>
                        set('customer_name', event.target.value)
                    }
                    maxLength={120}
                    autoComplete="name"
                    required
                    aria-invalid={Boolean(errors.customer_name) || undefined}
                />
            </Field>
            <Field
                label="Nomor WhatsApp"
                htmlFor={`${id}-phone`}
                error={errors.customer_phone}
                hint="Untuk kabar pesanan dari penjual."
            >
                <Input
                    id={`${id}-phone`}
                    type="tel"
                    inputMode="tel"
                    value={form.data.customer_phone}
                    onChange={(event) =>
                        set('customer_phone', event.target.value)
                    }
                    placeholder="08…"
                    maxLength={20}
                    autoComplete="tel"
                    required
                    aria-invalid={Boolean(errors.customer_phone) || undefined}
                />
            </Field>
            <div className="flex flex-col gap-1.5">
                <span id={`${id}-fulfillment`} className="text-sm font-medium">
                    Cara terima
                </span>
                <ChoiceGroup
                    labelledBy={`${id}-fulfillment`}
                    value={form.data.fulfillment}
                    options={fulfillments}
                    onChange={(value) => set('fulfillment', value)}
                />
            </div>
            {form.data.fulfillment === 'antar' && (
                <Field
                    label="Alamat pengantaran"
                    htmlFor={`${id}-address`}
                    error={errors.address}
                >
                    <Textarea
                        id={`${id}-address`}
                        value={form.data.address}
                        onChange={(event) => set('address', event.target.value)}
                        maxLength={300}
                        rows={3}
                        autoComplete="street-address"
                        required
                        aria-invalid={Boolean(errors.address) || undefined}
                    />
                </Field>
            )}
            <Field
                label={
                    form.data.fulfillment === 'antar'
                        ? 'Waktu antar (opsional)'
                        : 'Waktu ambil (opsional)'
                }
                htmlFor={`${id}-when`}
                error={errors.wanted_at}
            >
                <Input
                    id={`${id}-when`}
                    type="datetime-local"
                    value={form.data.wanted_at}
                    onChange={(event) => set('wanted_at', event.target.value)}
                    aria-invalid={Boolean(errors.wanted_at) || undefined}
                />
            </Field>
            <Field
                label="Catatan (opsional)"
                htmlFor={`${id}-note`}
                error={errors.note}
            >
                <Textarea
                    id={`${id}-note`}
                    value={form.data.note}
                    onChange={(event) => set('note', event.target.value)}
                    placeholder="Misalnya: pedas level 2, tanpa es"
                    maxLength={300}
                    rows={2}
                />
            </Field>
            {honeypot && (
                // Off-screen, not display:none, so simple bots still see and fill it.
                <div
                    aria-hidden
                    className="absolute -left-[9999px] size-px overflow-hidden"
                >
                    <label htmlFor={`${id}-website`}>Website</label>
                    <input
                        id={`${id}-website`}
                        tabIndex={-1}
                        autoComplete="off"
                        value={form.data.website}
                        onChange={(event) =>
                            form.setData('website', event.target.value)
                        }
                    />
                </div>
            )}
        </div>
    );
}
