import type { SharedPageProps } from '@inertiajs/core';
import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import OnboardingLayout from '@/layouts/onboarding-layout';
import SettingsLayout from '@/layouts/settings/layout';
import ShopLayout from '@/layouts/shop-layout';
import { setTimeZone } from '@/lib/format';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name, page) => {
        // Runs before every page renders, so dates format in the server's zone.
        // An error page outside the middleware has no shared props.
        const zone = page.props.timeZone as
            | SharedPageProps['timeZone']
            | undefined;

        if (zone) {
            setTimeZone(zone);
        }

        switch (true) {
            case name === 'welcome':
            case name.startsWith('errors/'):
            case name.startsWith('legal/'):
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('onboarding/'):
                return OnboardingLayout;
            case name.startsWith('shop/'):
                return ShopLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
