import { Head, Link } from '@inertiajs/react';
import { ArrowLeftRight, Banknote, Landmark, Pencil, Plus } from 'lucide-react';
import { Money } from '@/components/app/money';
import { OnboardingReminder } from '@/components/app/onboarding-reminder';
import { PageHeader } from '@/components/app/page-header';
import { IconChip, SectionCard } from '@/components/app/section-card';
import { Button } from '@/components/ui/button';
import { AccountNameDialog } from '@/features/money-accounts/account-name-dialog';
import { ArchiveAccountButton } from '@/features/money-accounts/archive-account-button';
import { index } from '@/routes/money-accounts';
import { create } from '@/routes/transactions';
import type { MoneyAccountsIndexProps } from '@/types/domain';
import {
    MoneyAccountKind,
    moneyAccountKindLabels,
    TransactionType,
} from '@/types/enums';

export default function MoneyAccountsIndex({
    accounts,
    needsOnboarding,
}: MoneyAccountsIndexProps) {
    const total = accounts.reduce((sum, account) => sum + account.balance, 0);

    return (
        <>
            <Head title="Kas & rekening" />
            <div className="flex flex-col gap-4 p-4 md:gap-6 md:p-6">
                <PageHeader
                    title="Kas & rekening"
                    description="Tempat uang usaha disimpan. Saldonya dihitung dari semua catatan."
                    actions={
                        <>
                            <Button asChild>
                                <Link
                                    href={create({
                                        query: {
                                            jenis: TransactionType.PindahDana,
                                        },
                                    })}
                                >
                                    <ArrowLeftRight aria-hidden />
                                    Pindah dana
                                </Link>
                            </Button>
                            <AccountNameDialog
                                trigger={
                                    <Button variant="outline">
                                        <Plus aria-hidden />
                                        Tambah rekening
                                    </Button>
                                }
                            />
                        </>
                    }
                />

                {needsOnboarding && <OnboardingReminder />}

                <SectionCard
                    title="Saldo per rekening"
                    description={
                        <>
                            Pindahkan uang antar rekening lewat Pindah dana,
                            misalnya setor tunai ke bank. Uang yang sudah ada
                            sebelum mulai mencatat?{' '}
                            <Link
                                href={create({
                                    query: { jenis: TransactionType.SaldoAwal },
                                })}
                                className="rounded-sm font-medium text-foreground underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                Catat saldo awal
                            </Link>
                        </>
                    }
                >
                    <ul className="flex flex-col divide-y">
                        {accounts.map((account) => {
                            const isCash =
                                account.kind === MoneyAccountKind.Tunai;

                            return (
                                <li
                                    key={account.id}
                                    className="flex flex-col gap-2 py-3 first:pt-0 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <IconChip
                                            icon={isCash ? Banknote : Landmark}
                                        />
                                        <div className="flex min-w-0 flex-col">
                                            <span className="font-medium">
                                                {account.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {account.balance < 0
                                                    ? 'Saldo minus. Biasanya karena saldo awalnya belum dicatat lewat Modal → Setor modal.'
                                                    : moneyAccountKindLabels[
                                                          account.kind
                                                      ]}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex items-center justify-between gap-2 pl-12 sm:pl-0">
                                        <Money value={account.balance} />
                                        <div className="flex items-center gap-1">
                                            <AccountNameDialog
                                                account={account}
                                                trigger={
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={`Ganti nama ${account.name}`}
                                                    >
                                                        <Pencil aria-hidden />
                                                        <span className="max-sm:sr-only">
                                                            Ganti nama
                                                        </span>
                                                    </Button>
                                                }
                                            />
                                            {/* Kas tunai always stays: every tunai record lands there. */}
                                            {!isCash && (
                                                <ArchiveAccountButton
                                                    account={account}
                                                />
                                            )}
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                        <li className="flex items-baseline justify-between gap-3 pt-3">
                            <span className="text-sm font-semibold">
                                Total uang usaha
                            </span>
                            <Money value={total} size="lg" />
                        </li>
                    </ul>
                </SectionCard>
            </div>
        </>
    );
}

MoneyAccountsIndex.layout = {
    breadcrumbs: [{ title: 'Kas & rekening', href: index() }],
};
