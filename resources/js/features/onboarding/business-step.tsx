import { ChoiceGroup } from '@/components/app/choice-group';
import { Field } from '@/components/app/field';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import { BusinessCategory, businessCategoryLabels } from '@/types/enums';
import type { OnboardingDraft, OnboardingSetter } from './onboarding';

/** Langkah 1: the business, what it sells, and the day the books open. */
export function BusinessStep({
    data,
    errors,
    setField,
    latestStart,
    hasRecords,
}: {
    data: OnboardingDraft;
    errors: Record<string, string | undefined>;
    setField: OnboardingSetter;
    latestStart: string;
    hasRecords: boolean;
}) {
    return (
        <div className="flex flex-col gap-5">
            <Field label="Nama usaha" htmlFor="ob-name" error={errors.name}>
                <Input
                    id="ob-name"
                    value={data.name}
                    onChange={(event) => setField('name', event.target.value)}
                    placeholder="Warung Bu Rina"
                    autoComplete="organization"
                    aria-invalid={Boolean(errors.name) || undefined}
                />
            </Field>

            <div className="flex flex-col gap-1.5">
                <span id="ob-category-label" className="text-sm font-medium">
                    Jenis usaha
                </span>
                <ChoiceGroup
                    labelledBy="ob-category-label"
                    value={data.category}
                    options={Object.values(BusinessCategory).map((value) => ({
                        value,
                        label: businessCategoryLabels[value],
                    }))}
                    onChange={(category) => setField('category', category)}
                    wrap
                />
                <p className="text-xs text-muted-foreground">
                    Untuk menawarkan contoh produk di langkah Produk.
                </p>
                <InputError message={errors.category} />
            </div>

            <Field
                label="Mulai dicatat sejak"
                htmlFor="ob-start"
                error={errors.started_on}
                hint={
                    hasRecords
                        ? `Paling lambat ${formatDate(latestStart, 'long')}, sebelum catatan pertamamu. Saldo awal dicatat pada tanggal ini.`
                        : 'Biasanya hari ini. Saldo awal dicatat pada tanggal ini.'
                }
            >
                <Input
                    id="ob-start"
                    type="date"
                    max={latestStart}
                    value={data.started_on}
                    onChange={(event) =>
                        setField('started_on', event.target.value)
                    }
                    aria-invalid={Boolean(errors.started_on) || undefined}
                    className="sm:max-w-56"
                />
            </Field>
        </div>
    );
}
