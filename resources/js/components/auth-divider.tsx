import { Separator } from '@/components/ui/separator';

/** "atau dengan email" between the one-tap buttons and the form. */
export function AuthDivider({
    children = 'atau dengan email',
}: {
    children?: string;
}) {
    return (
        <div className="flex items-center gap-3 text-sm text-muted-foreground">
            <Separator className="flex-1" />
            <span>{children}</span>
            <Separator className="flex-1" />
        </div>
    );
}
