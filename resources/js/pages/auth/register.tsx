import { Form, Head } from '@inertiajs/react';
import { Lock, Mail, UserRound } from 'lucide-react';
import { AuthDivider } from '@/components/auth-divider';
import { GoogleSignInButton } from '@/components/google-sign-in-button';
import InputError from '@/components/input-error';
import { InputIcon } from '@/components/input-icon';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
    /** The Google button shows only once GOOGLE_CLIENT_ID is set. */
    googleLogin: boolean;
};

export default function Register({ passwordRules, googleLogin }: Props) {
    return (
        <>
            <Head title="Daftar" />

            {googleLogin && (
                <div className="mb-6 grid gap-6">
                    <GoogleSignInButton />
                    <AuthDivider>atau daftar dengan email</AuthDivider>
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama</Label>
                                <InputIcon icon={UserRound}>
                                    <Input
                                        id="name"
                                        type="text"
                                        required
                                        autoComplete="name"
                                        name="name"
                                        placeholder="Nama kamu"
                                    />
                                </InputIcon>
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <InputIcon icon={Mail}>
                                    <Input
                                        id="email"
                                        type="email"
                                        required
                                        autoComplete="email"
                                        name="email"
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
                                        required
                                        autoComplete="new-password"
                                        name="password"
                                        placeholder="Password"
                                        passwordrules={passwordRules}
                                        aria-describedby="password-hint"
                                    />
                                </InputIcon>
                                <p
                                    id="password-hint"
                                    className="text-sm text-muted-foreground"
                                >
                                    Minimal 8 karakter.
                                </p>
                                <InputError message={errors.password} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Buat akun
                            </Button>
                        </div>

                        <p className="text-center text-sm text-muted-foreground">
                            Sudah punya akun?{' '}
                            <TextLink href={login()}>Masuk</TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Buat akun Lais',
    description:
        'Gratis. Setelah daftar, kamu langsung membuka pembukuan usaha.',
};
