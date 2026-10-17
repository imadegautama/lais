import { ChoiceGroup } from '@/components/app/choice-group';
import InputError from '@/components/input-error';
import type { MoneyAccountOption } from '@/types/domain';
import type { RecordFieldSetter, RecordFormData } from './record';

/** Pindah dana: from which rekening to which. The amount sits in the details card. */
export function TransferFields({
    data,
    errors,
    setField,
    moneyAccounts,
}: {
    data: RecordFormData;
    errors: Record<string, string | undefined>;
    setField: RecordFieldSetter;
    moneyAccounts: MoneyAccountOption[];
}) {
    const options = (except: string) =>
        moneyAccounts
            .filter((account) => String(account.id) !== except)
            .map((account) => ({
                value: String(account.id),
                label: account.name,
            }));

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-1.5">
                <span id="tx-from-label" className="text-sm font-medium">
                    Dari
                </span>
                <ChoiceGroup
                    labelledBy="tx-from-label"
                    value={data.money_account_id}
                    options={options('')}
                    onChange={(id) => {
                        setField('money_account_id', id);

                        // The same rekening cannot be both sides.
                        if (id === data.to_money_account_id) {
                            setField('to_money_account_id', '');
                        }
                    }}
                    wrap
                />
                <InputError message={errors.money_account_id} />
            </div>
            <div className="flex flex-col gap-1.5">
                <span id="tx-to-label" className="text-sm font-medium">
                    Ke
                </span>
                <ChoiceGroup
                    labelledBy="tx-to-label"
                    value={data.to_money_account_id}
                    options={options(data.money_account_id)}
                    onChange={(id) => setField('to_money_account_id', id)}
                    wrap
                />
                <InputError message={errors.to_money_account_id} />
            </div>
        </div>
    );
}
