import { Form } from '@inertiajs/react';
import { Eye, EyeOff, LockKeyhole, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AlertError from '@/components/alert-error';
import { Button } from '@/components/ui/button';
import { regenerateRecoveryCodes } from '@/routes/two-factor';

type Props = {
    recoveryCodesList: string[];
    fetchRecoveryCodes: () => Promise<void>;
    errors: string[];
};

export default function TwoFactorRecoveryCodes({
    recoveryCodesList,
    fetchRecoveryCodes,
    errors,
}: Props) {
    const [codesAreVisible, setCodesAreVisible] = useState<boolean>(false);
    const codesSectionRef = useRef<HTMLDivElement | null>(null);
    const canRegenerateCodes = recoveryCodesList.length > 0 && codesAreVisible;

    const toggleCodesVisibility = useCallback(async () => {
        if (!codesAreVisible && !recoveryCodesList.length) {
            await fetchRecoveryCodes();
        }

        setCodesAreVisible(!codesAreVisible);

        if (!codesAreVisible) {
            setTimeout(() => {
                codesSectionRef.current?.scrollIntoView({ block: 'nearest' });
            });
        }
    }, [codesAreVisible, recoveryCodesList.length, fetchRecoveryCodes]);

    useEffect(() => {
        if (!recoveryCodesList.length) {
            void fetchRecoveryCodes();
        }
    }, [recoveryCodesList.length, fetchRecoveryCodes]);

    const RecoveryCodeIconComponent = codesAreVisible ? EyeOff : Eye;

    return (
        <div className="flex w-full flex-col gap-3 rounded-lg border p-4">
            <div>
                <h3 className="flex items-center gap-2 text-sm font-semibold">
                    <LockKeyhole
                        className="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    Kode cadangan
                </h3>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    Dipakai untuk masuk kalau HP dengan aplikasi authenticator
                    hilang. Simpan di tempat aman, misalnya pengelola password.
                </p>
            </div>

            <div className="flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between">
                <Button
                    variant="outline"
                    onClick={toggleCodesVisibility}
                    className="w-fit"
                    aria-expanded={codesAreVisible}
                    aria-controls="recovery-codes-section"
                >
                    <RecoveryCodeIconComponent
                        className="size-4"
                        aria-hidden="true"
                    />
                    {codesAreVisible
                        ? 'Sembunyikan kode cadangan'
                        : 'Lihat kode cadangan'}
                </Button>

                {canRegenerateCodes && (
                    <Form
                        {...regenerateRecoveryCodes.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={fetchRecoveryCodes}
                    >
                        {({ processing }) => (
                            <Button
                                variant="outline"
                                type="submit"
                                disabled={processing}
                                aria-describedby="regenerate-warning"
                            >
                                <RefreshCw aria-hidden /> Buat kode baru
                            </Button>
                        )}
                    </Form>
                )}
            </div>

            <div
                id="recovery-codes-section"
                className={codesAreVisible ? 'flex flex-col gap-3' : 'hidden'}
            >
                {errors?.length ? (
                    <AlertError errors={errors} />
                ) : (
                    <>
                        <div
                            ref={codesSectionRef}
                            className="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                            role="list"
                            aria-label="Kode cadangan"
                        >
                            {recoveryCodesList.length ? (
                                recoveryCodesList.map((code, index) => (
                                    <div
                                        key={index}
                                        role="listitem"
                                        className="select-text"
                                    >
                                        {code}
                                    </div>
                                ))
                            ) : (
                                <div
                                    className="space-y-2"
                                    aria-label="Memuat kode cadangan"
                                >
                                    {Array.from({ length: 8 }, (_, index) => (
                                        <div
                                            key={index}
                                            className="h-4 animate-pulse rounded bg-muted-foreground/20 motion-reduce:animate-none"
                                            aria-hidden="true"
                                        />
                                    ))}
                                </div>
                            )}
                        </div>

                        <p
                            id="regenerate-warning"
                            className="text-xs text-muted-foreground select-none"
                        >
                            Setiap kode hanya bisa dipakai sekali. Kalau hampir
                            habis, tekan{' '}
                            <span className="font-semibold">
                                Buat kode baru
                            </span>
                            ; kode lama tidak berlaku lagi.
                        </p>
                    </>
                )}
            </div>
        </div>
    );
}
