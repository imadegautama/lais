// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verifikasi email" />

            {status === 'verification-link-sent' && (
                <div className="mb-6 text-sm font-medium text-success">
                    Link verifikasi baru sudah kami kirim ke emailmu.
                </div>
            )}

            <Form {...send.form()} className="flex flex-col gap-4">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            variant="secondary"
                            className="w-full"
                        >
                            {processing && <Spinner />}
                            Kirim ulang email verifikasi
                        </Button>

                        <TextLink href={logout()} className="mx-auto text-sm">
                            Keluar
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verifikasi email',
    description:
        'Buka email kami, lalu tekan link di dalamnya untuk memverifikasi akunmu.',
};
