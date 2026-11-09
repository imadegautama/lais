import { usePage } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { formatDate, zoneLabel } from '@/lib/format';

/**
 * Says so inside a "Coba demo" account: its own copy of Warung Bu Rina,
 * free to change, removed at demo_until (App\Services\DemoAccounts).
 */
export function DemoBanner() {
    const { user } = usePage().props.auth;

    if (!user?.is_demo || !user.demo_until) {
        return null;
    }

    return (
        <p className="flex items-start gap-2 border-b bg-accent px-4 py-2 text-sm md:px-6 print:hidden">
            <FlaskConical
                className="mt-0.5 size-4 shrink-0 text-brand-strong"
                aria-hidden
            />
            <span>
                Akun demo pribadimu, berisi data contoh Warung Bu Rina. Bebas
                dicoba; akun ini terhapus otomatis{' '}
                {formatDate(user.demo_until, 'datetime')} {zoneLabel()}.
            </span>
        </p>
    );
}
