import { useSyncExternalStore } from 'react';

/** Chrome's install prompt, kept from the moment the page loads. */
type InstallPrompt = Event & { prompt: () => Promise<void> };

/** How this browser installs Lais: its own prompt, the iPhone's Share menu, or not at all. */
export type InstallWay = 'prompt' | 'ios' | null;

let prompt: InstallPrompt | null = null;
let iosSteps = false;
const listeners = new Set<() => void>();
const notify = () => listeners.forEach((listener) => listener());

/**
 * Chrome fires beforeinstallprompt once, early; app.tsx calls this before
 * any page renders so the menu and the landing page can offer it later.
 */
export function captureInstallPrompt(): void {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        prompt = event as InstallPrompt;
        notify();
    });
    window.addEventListener('appinstalled', () => {
        prompt = null;
        notify();
    });
}

function installWay(): InstallWay {
    const standalone =
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true;

    if (standalone) {
        return null;
    }

    if (prompt) {
        return 'prompt';
    }

    return /iphone|ipad|ipod/i.test(navigator.userAgent) ? 'ios' : null;
}

const subscribe = (listener: () => void) => {
    listeners.add(listener);

    return () => listeners.delete(listener);
};

/** null on the server and where Lais is already installed or cannot be. */
export function useInstallWay(): InstallWay {
    return useSyncExternalStore(subscribe, installWay, () => null);
}

export function useIosSteps(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => iosSteps,
        () => false,
    );
}

/** Chrome shows its own dialog; on an iPhone the steps open (InstallAppDialog). */
export function installApp(): void {
    if (prompt) {
        void prompt.prompt().finally(() => {
            prompt = null;
            notify();
        });
    } else {
        iosSteps = true;
        notify();
    }
}

export function closeIosSteps(): void {
    iosSteps = false;
    notify();
}
