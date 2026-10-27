import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormActionBar } from '@/components/app/form-action-bar';
import { PageHeader } from '@/components/app/page-header';
import { SectionCard } from '@/components/app/section-card';
import { Stepper } from '@/components/app/stepper';
import { Button } from '@/components/ui/button';
import { AmountRows } from '@/features/onboarding/amount-rows';
import { BusinessStep } from '@/features/onboarding/business-step';
import { MoneyStep } from '@/features/onboarding/money-step';
import {
    firstStepWithError,
    initialDraft,
    openingBalance,
    resumeIndex,
    STEPS,
    toPayload,
    type OnboardingDraft,
    type StarterRow,
} from '@/features/onboarding/onboarding';
import { ProductsStep } from '@/features/onboarding/products-step';
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
};

const intro: Record<
    (typeof STEPS)[number]['key'],
    { title: string; description: string }
> = {
    usaha: {
        title: 'Tentang usahamu',
        description: 'Nama dan jenis usaha, serta tanggal mulai pembukuan.',
    },
    uang: {
        title: 'Uang usaha saat ini',
        description:
            'Berapa uang usaha di tiap tempat pada tanggal mulai. Yang kosong biarkan saja.',
    },
    peralatan: {
        title: 'Peralatan usaha',
        description:
            'Barang yang dipakai untuk usaha, bukan untuk dijual: kompor, etalase, freezer. Isi perkiraan nilainya sekarang. Boleh dilewati.',
    },
    produk: {
        title: 'Produk & bahan',
        description:
            'Yang kamu jual dan bahan yang kamu stok, dengan stok saat mulai. Boleh dilewati.',
    },
    utang: {
        title: 'Utang & piutang',
        description:
            'Utang ke supplier dan kasbon pelanggan yang belum lunas saat mulai. Boleh dilewati.',
    },
    ringkasan: {
        title: 'Neraca awal',
        description:
            'Ringkasan dari langkah sebelumnya. Periksa dulu, lalu simpan.',
    },
};

/** Opening the books (UI-PAGES B5): saldo awal step by step, saved as a draft after each step. */
export default function Onboarding({
    business,
    draft,
    accounts,
    latestStart,
    hasRecords,
    templates,
    inventory,
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
            <Head title="Buka pembukuan" />
            <div className="flex flex-col gap-5">
                <PageHeader
                    title="Buka pembukuan"
                    description="Catat yang sudah dimiliki usahamu saat mulai, supaya saldo kas dan neraca sesuai kenyataan. Bisa dilanjutkan nanti."
                />
                <Stepper steps={STEPS} current={index} />

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
                    className="flex flex-col gap-5"
                >
                    <SectionCard
                        title={intro[step.key].title}
                        description={intro[step.key].description}
                        tone={last ? 'focus' : 'default'}
                    >
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
                                onChange={(next) => setField('accounts', next)}
                                errors={errors}
                            />
                        )}
                        {step.key === 'peralatan' && (
                            <AmountRows
                                list="equipment"
                                rows={data.equipment}
                                onChange={(next) => setField('equipment', next)}
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
                                onChange={(next) => setField('products', next)}
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
                        {step.key === 'ringkasan' && (
                            <SummaryStep
                                books={openingBalance(data, inventory.value)}
                            />
                        )}
                    </SectionCard>

                    {/* Back on the left and forward on the right, as in any wizard. */}
                    <FormActionBar>
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
                    </FormActionBar>
                </form>
            </div>
        </>
    );
}
