import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { start } from '@/routes/demo';

/**
 * "Coba demo": signs a visitor in to an account of their own with the
 * Warung Bu Rina books (App\Services\DemoAccounts), no sign-up needed.
 */
export function TryDemoButton({
    className,
    size,
}: {
    className?: string;
    size?: 'default' | 'lg';
}) {
    return (
        <Form {...start.form()} className={className}>
            {({ processing }) => (
                <Button
                    type="submit"
                    variant="outline"
                    size={size}
                    disabled={processing}
                    className="w-full"
                >
                    {processing && <Spinner aria-hidden />}
                    {processing
                        ? 'Menyiapkan warung demo…'
                        : 'Coba demo tanpa daftar'}
                </Button>
            )}
        </Form>
    );
}
