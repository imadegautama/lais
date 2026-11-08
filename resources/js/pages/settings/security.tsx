import { Form, Head, Link } from '@inertiajs/react';
import { History, KeyRound } from 'lucide-react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { ActivityList } from '@/components/app/activity-list';
import { Field } from '@/components/app/field';
import { SectionCard } from '@/components/app/section-card';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { settings } from '@/routes';
import { index as activityLog } from '@/routes/activity';
import { edit } from '@/routes/security';
import type { ActivityEntry } from '@/types/domain';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    /** False for an account made with Google until a password is set here. */
    hasPassword: boolean;
    activities: ActivityEntry[];
} & ManageTwoFactorProps;

/** Pengaturan > Keamanan: password, two-step login, and recent sign-ins. */
export default function Security(props: Props) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Keamanan" />

            <SectionCard
                title={props.hasPassword ? 'Ganti password' : 'Buat password'}
                description={
                    props.hasPassword
                        ? 'Pakai password panjang yang tidak dipakai di tempat lain.'
                        : 'Akun ini masuk lewat Google. Buat password supaya bisa juga masuk dengan email, dan untuk menghapus akun.'
                }
                icon={KeyRound}
            >
                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="flex flex-col gap-5"
                >
                    {({ errors, processing }) => (
                        <>
                            {props.hasPassword && (
                                <Field
                                    label="Password sekarang"
                                    htmlFor="current_password"
                                    error={errors.current_password}
                                >
                                    <PasswordInput
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        autoComplete="current-password"
                                    />
                                </Field>
                            )}

                            <Field
                                label="Password baru"
                                htmlFor="password"
                                error={errors.password}
                            >
                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    autoComplete="new-password"
                                    passwordrules={props.passwordRules}
                                />
                            </Field>

                            <Field
                                label="Ulangi password baru"
                                htmlFor="password_confirmation"
                                error={errors.password_confirmation}
                            >
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    passwordrules={props.passwordRules}
                                />
                            </Field>

                            <Button
                                className="w-fit"
                                disabled={processing}
                                data-test="update-password-button"
                            >
                                {processing ? 'Menyimpan…' : 'Simpan password'}
                            </Button>
                        </>
                    )}
                </Form>
            </SectionCard>

            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />

            <SectionCard
                title="Aktivitas akun"
                description="Masuk, keluar, dan perubahan keamanan terbaru. Kalau ada yang tidak kamu kenali, segera ganti password."
                icon={History}
            >
                <div className="flex flex-col gap-4">
                    <ActivityList entries={props.activities} />
                    <Link
                        href={activityLog({ query: { kategori: ['akun'] } })}
                        className="w-fit rounded-sm text-sm font-medium underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        Lihat semua di Log aktivitas
                    </Link>
                </div>
            </SectionCard>
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        { title: 'Pengaturan', href: settings() },
        { title: 'Keamanan', href: edit() },
    ],
};
