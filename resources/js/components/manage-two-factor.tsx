import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { SectionCard } from '@/components/app/section-card';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { disable, enable } from '@/routes/two-factor';

export type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function ManageTwoFactor(props: Props) {
    const requiresConfirmation = props.requiresConfirmation ?? false;
    const twoFactorEnabled = props.twoFactorEnabled ?? false;

    const {
        qrCodeSvg,
        hasSetupData,
        manualSetupKey,
        clearSetupData,
        clearTwoFactorAuthData,
        fetchSetupData,
        recoveryCodesList,
        fetchRecoveryCodes,
        errors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState<boolean>(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }

        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    if (!(props.canManageTwoFactor ?? false)) {
        return null;
    }

    return (
        <SectionCard
            title="Verifikasi dua langkah"
            description={
                twoFactorEnabled
                    ? 'Aktif. Setiap masuk, Lais meminta kode 6 angka dari aplikasi authenticator di HP kamu.'
                    : 'Selain password, Lais meminta kode 6 angka dari aplikasi authenticator (Google Authenticator, Authy) setiap kamu masuk.'
            }
            icon={ShieldCheck}
        >
            {twoFactorEnabled ? (
                <div className="flex flex-col items-start gap-4">
                    <Form {...disable.form()}>
                        {({ processing }) => (
                            <Button
                                variant="outline"
                                type="submit"
                                disabled={processing}
                            >
                                {processing
                                    ? 'Mematikan…'
                                    : 'Matikan verifikasi dua langkah'}
                            </Button>
                        )}
                    </Form>

                    <TwoFactorRecoveryCodes
                        recoveryCodesList={recoveryCodesList}
                        fetchRecoveryCodes={fetchRecoveryCodes}
                        errors={errors}
                    />
                </div>
            ) : hasSetupData ? (
                <Button onClick={() => setShowSetupModal(true)}>
                    <ShieldCheck aria-hidden />
                    Lanjutkan pengaturan
                </Button>
            ) : (
                <Form
                    {...enable.form()}
                    onSuccess={() => setShowSetupModal(true)}
                >
                    {({ processing }) => (
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Menyiapkan…'
                                : 'Nyalakan verifikasi dua langkah'}
                        </Button>
                    )}
                </Form>
            )}

            <TwoFactorSetupModal
                isOpen={showSetupModal}
                onClose={() => setShowSetupModal(false)}
                requiresConfirmation={requiresConfirmation}
                twoFactorEnabled={twoFactorEnabled}
                qrCodeSvg={qrCodeSvg}
                manualSetupKey={manualSetupKey}
                clearSetupData={clearSetupData}
                fetchSetupData={fetchSetupData}
                errors={errors}
            />
        </SectionCard>
    );
}
