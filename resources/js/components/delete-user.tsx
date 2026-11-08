import { Form, Link } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { SectionCard } from '@/components/app/section-card';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { edit as editSecurity } from '@/routes/security';

/**
 * Deleting the account takes the whole business with it (businesses cascade
 * on users), so the card says what goes and asks for the password.
 */
export default function DeleteUser({ hasPassword }: { hasPassword: boolean }) {
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <SectionCard
            title="Hapus akun"
            description="Akun dan seluruh data usaha dihapus permanen. Tidak bisa dibatalkan."
            icon={Trash2}
        >
            <div className="flex flex-col gap-4">
                <p className="text-sm">
                    Yang ikut terhapus: transaksi, laporan, produk dan stok,
                    pesanan, kontak, rekening, dan koneksi bot. Link toko online
                    berhenti bekerja. Unduh laporan yang masih kamu perlukan
                    lebih dulu.
                </p>

                {hasPassword ? (
                    <Dialog>
                        <DialogTrigger asChild>
                            <Button
                                variant="destructive"
                                className="w-fit"
                                data-test="delete-user-button"
                            >
                                Hapus akun
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                Hapus akun dan semua data usaha?
                            </DialogTitle>
                            <DialogDescription>
                                Semua catatan usaha hilang permanen dan tidak
                                bisa dipulihkan. Isi password untuk memastikan
                                ini memang kamu.
                            </DialogDescription>

                            <Form
                                {...ProfileController.destroy.form()}
                                options={{
                                    preserveScroll: true,
                                }}
                                onError={() => passwordInput.current?.focus()}
                                resetOnSuccess
                                className="flex flex-col gap-6"
                            >
                                {({
                                    resetAndClearErrors,
                                    processing,
                                    errors,
                                }) => (
                                    <>
                                        <div className="flex flex-col gap-1.5">
                                            <Label htmlFor="delete-password">
                                                Password
                                            </Label>
                                            <PasswordInput
                                                id="delete-password"
                                                name="password"
                                                ref={passwordInput}
                                                autoComplete="current-password"
                                            />
                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>

                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    variant="secondary"
                                                    onClick={() =>
                                                        resetAndClearErrors()
                                                    }
                                                >
                                                    Batal
                                                </Button>
                                            </DialogClose>

                                            <Button
                                                variant="destructive"
                                                type="submit"
                                                disabled={processing}
                                                data-test="confirm-delete-user-button"
                                            >
                                                {processing
                                                    ? 'Menghapus…'
                                                    : 'Hapus permanen'}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Akun ini masuk lewat Google dan belum punya password.{' '}
                        <Link
                            href={editSecurity()}
                            className="rounded-sm font-medium text-foreground underline underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            Buat password di Keamanan
                        </Link>{' '}
                        dulu untuk menghapus akun.
                    </p>
                )}
            </div>
        </SectionCard>
    );
}
