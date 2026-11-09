import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormActionBar } from '@/components/app/form-action-bar';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { TermHint, type Term } from '@/components/app/term-hint';
import { Button } from '@/components/ui/button';
import { AccountsStep } from '@/features/onboarding/accounts-step';
import { AmountRows } from '@/features/onboarding/amount-rows';
import { BusinessStep } from '@/features/onboarding/business-step';
import { MoneyStep } from '@/features/onboarding/money-step';
import {
    earnedBadges,
    firstStepWithError,
    initialDraft,
    openingBalance,
    resumeIndex,
    STEPS,
    toPayload,
    type ChartAccount,
    type OnboardingDraft,
    type StarterRow,
} from '@/features/onboarding/onboarding';
import { ProductsStep } from '@/features/onboarding/products-step';
import { SetupProgress } from '@/features/onboarding/setup-progress';
import { SummaryStep } from '@/features/onboarding/summary-step';
import { fieldSetter, focusFirstError, formErrors } from '@/lib/form';
import { store, update } from '@/routes/onboarding';
import type { BusinessCategory, MoneyAccountKind } from '@/types/enums';

type OnboardingProps = {
    business: { name: string; category: BusinessCategory | null };
    draft: Partial<OnboardingDraft> | null;
    accounts: Array<{ id: number; kind: MoneyAccountKind; name: string }>;
    /** The latest start date: today, or the first record already there. */
    latestStart: string;
    hasRecords: boolean;
    templates: Record<BusinessCategory, StarterRow[]>;
    /** Products added before the wizard, with their opening stock. */
    inventory: { count: number; value: number };
    /** The fixed chart (COA) for the Akun step. */
    chart: ChartAccount[];
};

/** Each step asks one everyday question, says why it matters, and explains its terms. */
const intro: Record<
    (typeof STEPS)[number]['key'],
    { title: string; description: string; terms: Array<[Term, string]> }
> = {
    usaha: {
        title: 'Usahamu apa?',
        description:
            'Nama dan jenis usaha membantu Lais menyiapkan contoh produk dan akun yang cocok. Tanggal mulai jadi hari pertama pembukuanmu.',
        terms: [['openingBalance', 'Saldo awal']],
    },
    uang: {
        title: 'Ada uang usaha berapa sekarang?',
        description:
            'Isi uang usaha di tiap tempat pada tanggal mulai, supaya saldo kas di Lais sama dengan kenyataan. Yang kosong biarkan saja.',
        terms: [['assets', 'Aset']],
    },
    peralatan: {
        title: 'Punya peralatan usaha apa?',
        description:
            'Barang yang dipakai untuk usaha, bukan dijual: kompor, etalase, mesin jahit. Isi perkiraan nilainya sekarang. Boleh dilewati.',
        terms: [['equipment', 'Peralatan']],
    },
    produk: {
        title: 'Apa yang kamu jual, dan stoknya berapa?',
        description:
            'Produk dan bahan beserta stok saat mulai. Dari sini Lais menghitung stok dan modal barang tiap penjualan. Boleh dilewati.',
        terms: [
            ['inventory', 'Persediaan'],
            ['cogs', 'HPP'],
        ],
    },
    utang: {
        title: 'Ada utang atau kasbon yang belum lunas?',
        description:
            'Utang ke supplier dan kasbon pelanggan saat mulai, supaya tagihannya tidak terlupa. Boleh dilewati.',
        terms: [
            ['payables', 'Utang'],
            ['receivables', 'Piutang'],
        ],
    },
    akun: {
        title: 'Ini daftar akun usahamu',
        description:
            'Daftar akun (COA) adalah laci-laci pembukuan. Lais sudah menyiapkannya; tambah akun khusus kalau perlu.',
        terms: [['chartOfAccounts', 'Daftar akun']],
    },
    ringkasan: {
        title: 'Hampir selesai!',
        description:
            'Ringkasan dari langkah sebelumnya. Periksa dulu, lalu simpan.',
        terms: [['capital', 'Modal']],
    },
};

/** The page column: header, progress, step, and the action bar's buttons line up on it. */
const column = 'mx-auto w-full max-w-3xl px-4 md:px-6';

/** Opening the books (UI-PAGES B5): saldo awal step by step, saved as a draft after each step. */
export default function Onboarding({
    business,
    draft,
    accounts,
    latestStart,
    hasRecords,
    templates,
    inventory,
    chart,
}: OnboardingProps) {
    const form = useForm<OnboardingDraft>(
        initialDraft({
            draft,
            name: business.name,
            category: business.category,
            accounts,
            latestStart,
        }),
    );
    const [index, setIndex] = useState(() => resumeIndex(form.data));
    const step = STEPS[index];
    const last = index === STEPS.length - 1;
    const { data, processing } = form;
    const errors = formErrors(form);
    const setField = fieldSetter(form);
    const go = (next: number) => {
        setIndex(next);
        window.scrollTo({ top: 0 });
    };

    const saveStep = () => {
        // The payload is checked; the form as typed is what comes back on resume.
        form.transform((values) => ({
            ...toPayload(values),
            step: step.key,
            draft: { ...values, step: step.key },
        }));
        form.submit(update(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => go(index + 1),
            onError: focusFirstError,
        });
    };

    const finish = () => {
        form.transform(toPayload);
        form.submit(store(), {
            preserveState: true,
            onError: (failed) => {
                const back = firstStepWithError(failed);

                if (back) {
                    go(STEPS.findIndex((candidate) => candidate.key === back));
                }

                focusFirstError();
            },
        });
    };

    return (
        <>
            <Head title="Siapkan buku usaha" />
            <div className="flex flex-1 flex-col">
                <div className={`${column} flex flex-col gap-5 pt-6 md:pt-8`}>
                    <PageHeader
                        title="Siapkan buku usahamu"
                        description="Jawab beberapa pertanyaan tentang usahamu. Lais menyusun saldo awal dan daftar akunnya. Bisa dilanjutkan nanti."
                    />
                    <SetupProgress current={index} />
                </div>

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (last) {
                            finish();
                        } else {
                            saveStep();
                        }
                    }}
                    className="flex flex-1 flex-col"
                >
                    {/* Keyed by step, so each step slides in once (setup motion, DESIGN.md §6). */}
                    <div
                        key={step.key}
                        className={`${column} pt-5 pb-6 motion-safe:animate-in motion-safe:duration-300 motion-safe:fade-in motion-safe:slide-in-from-bottom-2 md:pb-8`}
                    >
                        <SectionCard
                            title={intro[step.key].title}
                            description={intro[step.key].description}
                            tone={last ? 'focus' : 'default'}
                        >
                            <div className="-mt-1 mb-4 flex flex-wrap gap-2">
                                {intro[step.key].terms.map(([term, label]) => (
                                    <TermHint
                                        key={term}
                                        term={term}
                                        label={label}
                                        chip
                                    />
                                ))}
                            </div>
                            {step.key === 'usaha' && (
                                <BusinessStep
                                    data={data}
                                    errors={errors}
                                    setField={setField}
                                    latestStart={latestStart}
                                    hasRecords={hasRecords}
                                />
                            )}
                            {step.key === 'uang' && (
                                <MoneyStep
                                    accounts={data.accounts}
                                    onChange={(next) =>
                                        setField('accounts', next)
                                    }
                                    errors={errors}
                                />
                            )}
                            {step.key === 'peralatan' && (
                                <AmountRows
                                    list="equipment"
                                    rows={data.equipment}
                                    onChange={(next) =>
                                        setField('equipment', next)
                                    }
                                    errors={errors}
                                    nameLabel="Nama peralatan"
                                    namePlaceholder="Kompor dua tungku"
                                    amountLabel="Perkiraan nilai"
                                    addLabel="Tambah peralatan"
                                />
                            )}
                            {step.key === 'produk' && (
                                <ProductsStep
                                    products={data.products}
                                    onChange={(next) =>
                                        setField('products', next)
                                    }
                                    errors={errors}
                                    category={data.category}
                                    templates={templates}
                                    inventory={inventory}
                                />
                            )}
                            {step.key === 'utang' && (
                                <div className="flex flex-col gap-6">
                                    <section className="flex flex-col gap-3">
                                        <h3 className="text-sm font-semibold">
                                            Utang ke supplier
                                        </h3>
                                        <AmountRows
                                            list="debts"
                                            rows={data.debts}
                                            onChange={(next) =>
                                                setField('debts', next)
                                            }
                                            errors={errors}
                                            nameLabel="Supplier"
                                            namePlaceholder="Toko Sumber Rejeki"
                                            amountLabel="Sisa utang"
                                            addLabel="Tambah utang"
                                        />
                                    </section>
                                    <section className="flex flex-col gap-3">
                                        <h3 className="text-sm font-semibold">
                                            Piutang pelanggan (kasbon)
                                        </h3>
                                        <AmountRows
                                            list="receivables"
                                            rows={data.receivables}
                                            onChange={(next) =>
                                                setField('receivables', next)
                                            }
                                            errors={errors}
                                            nameLabel="Pelanggan"
                                            namePlaceholder="Bu Sari"
                                            amountLabel="Sisa piutang"
                                            addLabel="Tambah piutang"
                                        />
                                    </section>
                                </div>
                            )}
                            {step.key === 'akun' && (
                                <AccountsStep
                                    chart={chart}
                                    data={data}
                                    existingStock={inventory.value}
                                    onChange={(next) =>
                                        setField('custom_accounts', next)
                                    }
                                    errors={errors}
                                />
                            )}
                            {step.key === 'ringkasan' && (
                                <SummaryStep
                                    books={openingBalance(
                                        data,
                                        inventory.value,
                                    )}
                                    badges={earnedBadges(index)}
                                />
                            )}
                        </SectionCard>
                    </div>

                    {/* Back on the left and forward on the right, as in any wizard. The bar spans
                        the screen at its bottom (setup has no phone bottom nav); the buttons keep the column. */}
                    <FormActionBar className="bottom-0 mx-0 mb-0 block p-0 md:mx-0 md:mb-0 md:px-0">
                        <div
                            className={`${column} flex items-center gap-2 py-3`}
                        >
                            <Button
                                type="button"
                                variant="outline"
                                disabled={index === 0 || processing}
                                onClick={() => go(index - 1)}
                            >
                                Kembali
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="ml-auto"
                            >
                                {processing
                                    ? 'Menyimpan…'
                                    : last
                                      ? 'Simpan dan mulai'
                                      : 'Lanjut'}
                            </Button>
                        </div>
                    </FormActionBar>
                </form>
            </div>
        </>
    );
}
