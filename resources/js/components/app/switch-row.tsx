import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

/** A yes/no setting with a line that says what it does. */
export function SwitchRow({
    id,
    label,
    hint,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    hint: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-4 py-3 last:pb-0">
            <Label htmlFor={id} className="flex flex-col items-start gap-0.5">
                {label}
                <span className="text-xs font-normal text-muted-foreground">
                    {hint}
                </span>
            </Label>
            <Switch id={id} checked={checked} onCheckedChange={onChange} />
        </div>
    );
}
