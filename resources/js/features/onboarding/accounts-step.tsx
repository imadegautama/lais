import { Plus, Sparkles, X } from 'lucide-react';
import { useState } from 'react';
import { LabelBadge } from '@/components/app/badges';
import { ChoiceGroup } from '@/components/app/choice-group';
import { Money } from '@/components/app/money';
import { TermHint } from '@/components/app/term-hint';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { Rupiah } from '@/types/domain';
import { AccountType } from '@/types/enums';
import {
    ACCOUNT_IDEAS,
    chartGroups,
    type ChartAccount,
    type CustomAccountRow,
    type OnboardingDraft,
} from './onboarding';

const typeOptions = [
    { value: AccountType.Beban, label: 'Pengeluaran (beban)' },
    { value: AccountType.Pendapatan, label: 'Pemasukan (pendapatan)' },
];

const same = (a: string, b: string) =>
    a.trim().toLowerCase() === b.trim().toLowerCase();

/**
 * Langkah Akun: the chart (COA) in five groups with the opening balances so
 * far, and the owner's own accounts ("Akun khusus") for what the fixed chart
 * does not name.
 */
export function AccountsStep({
    chart,
    data,
    existingStock,
    onChange,
    errors,
}: {
    chart: ChartAccount[];
    data: OnboardingDraft;
    existingStock: Rupiah;
    onChange: (rows: CustomAccountRow[]) => void;
    errors: Record<string, string | undefined>;
}) {
    const [type, setType] = useState<CustomAccountRow['type']>(
        AccountType.Beban,
    );
    const [name, setName] = useState('');
    const rows = data.custom_accounts;
    const added = (candidate: string) =>
        rows.some((row) => same(row.name, candidate));
    const ideas = ACCOUNT_IDEAS[data.category];
    const listError =
        errors.custom_accounts ??
        rows
            .map((_, index) => errors[`custom_accounts.${index}.name`])
            .find(Boolean);

    const remove = (candidate: string) =>
        onChange(rows.filter((row) => !same(row.name, candidate)));
    const toggle = (idea: CustomAccountRow) =>
        added(idea.name) ? remove(idea.name) : onChange([...rows, idea]);
    const addTyped = () => {
        if (name.trim() === '' || added(name)) {
            return;
        }

        onChange([...rows, { type, name: name.trim() }]);
        setName('');
    };

    return (
        <div className="flex flex-col gap-6">
            <p className="text-sm text-muted-foreground">
                Setiap catatanmu nanti masuk ke salah satu akun ini, jadi
                laporan tersusun sendiri. Kodenya dipakai di laporan dan buku
                besar.
            </p>

            <div className="grid gap-3 md:grid-cols-2">
                {chartGroups(chart, data, existingStock).map((group) => (
                    <section
                        key={group.type}
                        aria-labelledby={`coa-${group.type}`}
                        className="flex flex-col gap-2 rounded-lg border p-3"
                    >
                        <h3
                            id={`coa-${group.type}`}
                            className="flex items-center gap-1 text-sm font-semibold"
                        >
                            {group.label}
                            <TermHint term={group.term} label={group.label} />
                        </h3>
                        {group.note && (
                            <p className="text-xs text-muted-foreground">
                                {group.note}
                            </p>
                        )}
                        <ul className="flex flex-col divide-y text-sm">
                            {group.accounts.map((account) => (
                                <li
                                    key={account.code ?? account.name}
                                    className="flex min-h-9 items-center justify-between gap-3 py-1.5"
                                >
                                    <span className="min-w-0">
                                        {account.code && (
                                            <span className="num text-muted-foreground">
                                                {account.code}{' '}
                                            </span>
                                        )}
                                        {account.name}
                                    </span>
                                    {account.code === null ? (
                                        <span className="flex shrink-0 items-center gap-1">
                                            <LabelBadge
                                                icon={Sparkles}
                                                label="Baru"
                                                tone="success"
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={`Hapus ${account.name}`}
                                                onClick={() =>
                                                    remove(account.name)
                                                }
                                            >
                                                <X aria-hidden />
                                            </Button>
                                        </span>
                                    ) : (
                                        account.amount !== null && (
                                            <Money
                                                value={account.amount}
                                                size="sm"
                                            />
                                        )
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>

            <section
                aria-labelledby="akun-khusus"
                className="flex flex-col gap-3"
            >
                <div>
                    <h3 id="akun-khusus" className="text-sm font-semibold">
                        Akun khusus (boleh dilewati)
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Ada pemasukan atau pengeluaran rutin yang belum ada di
                        daftar? Ketuk untuk menambah.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {ideas.map((idea) => (
                        <Button
                            key={idea.name}
                            type="button"
                            variant="outline"
                            size="sm"
                            aria-pressed={added(idea.name)}
                            onClick={() => toggle(idea)}
                            className={cn(
                                added(idea.name) &&
                                    'border-primary-text text-primary-text',
                            )}
                        >
                            {added(idea.name) ? (
                                <X aria-hidden />
                            ) : (
                                <Plus aria-hidden />
                            )}
                            {idea.name}
                        </Button>
                    ))}
                </div>
                <div className="flex flex-col gap-2 rounded-lg border p-3">
                    <span
                        id="akun-khusus-jenis"
                        className="text-sm font-medium"
                    >
                        Akun lain
                    </span>
                    <ChoiceGroup
                        labelledBy="akun-khusus-jenis"
                        value={type}
                        options={typeOptions}
                        onChange={setType}
                    />
                    <div className="flex gap-2">
                        <Input
                            aria-label="Nama akun"
                            value={name}
                            onChange={(event) => setName(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    addTyped();
                                }
                            }}
                            placeholder={
                                type === AccountType.Beban
                                    ? 'Beban Iuran kebersihan'
                                    : 'Pendapatan Sewa alat'
                            }
                            maxLength={60}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={addTyped}
                            disabled={name.trim() === '' || added(name)}
                        >
                            Tambah
                        </Button>
                    </div>
                </div>
                <InputError message={listError} />
            </section>
        </div>
    );
}
