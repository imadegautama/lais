import { Form, Head, Link, usePage } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Field } from '@/components/app/field';
import { SectionCard } from '@/components/app/section-card';
import DeleteUser from '@/components/delete-user';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { settings } from '@/routes';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

/** Pengaturan > Akun: the owner's own name and email, and leaving Lais. */
export default function Profile({
    mustVerifyEmail,
    hasPassword,
    status,
}: {
    mustVerifyEmail: boolean;
    hasPassword: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Akun" />

            <SectionCard
                title="Data akun"
                description="Email ini dipakai untuk masuk dan menerima link reset password."
                icon={UserRound}
            >
                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="flex flex-col gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Nama"
                                htmlFor="name"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    aria-invalid={
                                        Boolean(errors.name) || undefined
                                    }
                                />
                            </Field>

                            <Field
                                label="Email"
                                htmlFor="email"
                                error={errors.email}
                                hint="Ganti email berarti perlu verifikasi ulang lewat link yang dikirim ke email baru."
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    aria-invalid={
                                        Boolean(errors.email) || undefined
                                    }
                                />
                            </Field>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div
                                        role="status"
                                        className="rounded-lg bg-warning-soft px-4 py-3 text-sm text-warning"
                                    >
                                        <p>
                                            Email ini belum diverifikasi.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="rounded-sm font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                Kirim ulang link verifikasi
                                            </Link>
                                        </p>
                                        {status ===
                                            'verification-link-sent' && (
                                            <p className="mt-1 font-medium">
                                                Link verifikasi baru sudah
                                                dikirim ke email kamu.
                                            </p>
                                        )}
                                    </div>
                                )}

                            <Button
                                className="w-fit"
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                {processing ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        </>
                    )}
                </Form>
            </SectionCard>

            <DeleteUser hasPassword={hasPassword} />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        { title: 'Pengaturan', href: settings() },
        { title: 'Akun', href: edit() },
    ],
};
