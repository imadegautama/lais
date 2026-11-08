import { Link } from '@inertiajs/react';
import { ChoiceGroup } from '@/components/app/choice-group';
import InputError from '@/components/input-error';
import reports from '@/routes/reports';
import type { OwnAccountOption } from '@/types/domain';
import {
    AccountType,
    TransactionKind,
    TransactionType,
    transactionKindLabels,
} from '@/types/enums';

/**
 * Which account a biaya or a pemasukan lain lands on. A biaya lands on its
 * kind's own expense account (Beban Sewa, …; Lain-lain on Beban Operasional)
 * unless the owner picks one they added; other income has no default,
 * because 4101 is only for sales.
 */
export function AccountField({
    type,
    kind,
    value,
    accounts,
    error,
    onChange,
}: {
    type: TransactionType;
    /** The biaya's kind: it lands on that kind's own expense account unless another is picked. */
    kind?: TransactionKind | '';
    value: string;
    accounts: OwnAccountOption[];
    error?: string;
    onChange: (id: string) => void;
}) {
    const income = type === TransactionType.PemasukanLain;
    const own = accounts
        .filter(
            (account) =>
                account.type ===
                (income ? AccountType.Pendapatan : AccountType.Beban),
        )
        .map((account) => ({
            value: String(account.id),
            label: account.name,
        }));

    if (!income && (type !== TransactionType.Biaya || own.length === 0)) {
        return null;
    }

    if (income && own.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Belum ada akun pendapatan.{' '}
                <Link
                    href={reports.chart()}
                    className="font-medium text-primary-text underline underline-offset-4"
                >
                    Tambah di Daftar akun
                </Link>
                , misalnya Pendapatan Bunga atau Hibah.
                <InputError message={error} className="mt-1" />
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-1.5">
            <span id="tx-account-label" className="text-sm font-medium">
                Akun
            </span>
            <ChoiceGroup
                labelledBy="tx-account-label"
                value={value}
                options={
                    income
                        ? own
                        : [
                              {
                                  value: '',
                                  label:
                                      kind && kind !== TransactionKind.Lain
                                          ? `Beban ${transactionKindLabels[kind]}`
                                          : 'Beban Operasional',
                              },
                              ...own,
                          ]
                }
                onChange={onChange}
                wrap
            />
            <InputError message={error} />
        </div>
    );
}
