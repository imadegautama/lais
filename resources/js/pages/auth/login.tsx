import { Form, Head, usePage } from '@inertiajs/react';
import { Lock, Mail } from 'lucide-react';
import { AuthDivider } from '@/components/auth-divider';
import { GoogleSignInButton } from '@/components/google-sign-in-button';
import InputError from '@/components/input-error';
import { InputIcon } from '@/components/input-icon';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    /** The Google button shows only once GOOGLE_CLIENT_ID is set. */
    googleLogin: boolean;
};

export default function Login({
    status,
    canResetPassword,
    googleLogin,
}: Props) {
    // Set by GoogleController when the sign-in comes back refused.
    const googleError = usePage().props.errors.google;

    return (
        <>
            <Head title="Masuk" />

            {status && (
                <p className="mb-6 text-sm font-medium text-success">
                    {status}
                </p>
            )}

            {googleLogin && (
                <div className="mb-6 grid gap-3">
                    <GoogleSignInButton />
                    <InputError message={googleError} />
                    <div className="mt-3">
                        <AuthDivider />
                    </div>
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <InputIcon icon={Mail}>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoComplete="email"
                                        placeholder="nama@email.com"
                                    />
                                </InputIcon>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <InputIcon icon={Lock}>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="current-password"
                                        placeholder="Password"
                                    />
                                </InputIcon>
                                <InputError message={errors.password} />
                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="ml-auto text-sm"
                                    >
                                        Lupa password?
                                    </TextLink>
                                )}
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox id="remember" name="remember" />
                                <Label htmlFor="remember">Ingat saya</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Masuk
                            </Button>
                        </div>

                        <p className="text-center text-sm text-muted-foreground">
                            Belum punya akun?{' '}
                            <TextLink href={register()}>Daftar gratis</TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Masuk ke Lais',
    description: 'Lanjutkan dengan Google atau email.',
};
