import { Field } from '@/components/app/field';
import { SearchSelect } from '@/components/app/search-select';
import { formatRupiah } from '@/lib/format';
import type { ContactOption } from '@/types/domain';
import { ContactType } from '@/types/enums';
import type { ContactFieldConfig } from './record';

type ContactFieldProps = ContactFieldConfig & {
    value: string;
    contacts: ContactOption[];
    error?: string;
    onChange: (value: string) => void;
};

/** Pick a customer or supplier, or type a new name when the type allows it. */
export function ContactField({
    label,
    contactType,
    creatable,
    onlyOwing,
    value,
    contacts,
    error,
    onChange,
}: ContactFieldProps) {
    const customer = contactType === ContactType.Pelanggan;
    const options = contacts
        .filter(
            (contact) =>
                contact.type === contactType &&
                (!onlyOwing || contact.balance > 0),
        )
        .map((contact) => ({
            value: String(contact.id),
            label: contact.name,
            description:
                contact.balance > 0
                    ? `sisa ${formatRupiah(contact.balance)}`
                    : undefined,
        }));

    return (
        <Field
            label={label}
            htmlFor="tx-contact"
            error={error}
            hint={
                creatable
                    ? 'Belum ada di daftar? Ketik namanya untuk menambahkan.'
                    : options.length === 0
                      ? customer
                          ? 'Belum ada pelanggan yang punya tagihan.'
                          : 'Belum ada utang ke supplier.'
                      : undefined
            }
        >
            <SearchSelect
                id="tx-contact"
                value={value}
                onChange={onChange}
                options={options}
                placeholder={customer ? 'Pilih pelanggan' : 'Pilih supplier'}
                searchPlaceholder="Cari nama"
                emptyText={
                    creatable
                        ? 'Ketik nama untuk menambahkan.'
                        : 'Tidak ada yang punya tagihan.'
                }
                creatable={creatable ? (name) => `Tambah “${name}”` : undefined}
                invalid={Boolean(error)}
            />
        </Field>
    );
}
