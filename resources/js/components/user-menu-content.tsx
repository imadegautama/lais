import { Link, router } from '@inertiajs/react';
import { LogOut, Settings, Smartphone } from 'lucide-react';
import { installApp, useInstallWay } from '@/components/app/install-app';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout, settings } from '@/routes';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const installWay = useInstallWay();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {/* Only where this browser can put Lais on the home screen and has not yet. */}
                {installWay && (
                    <DropdownMenuItem onSelect={installApp}>
                        <Smartphone className="mr-2" />
                        Pasang Lais di HP
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={settings()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Pengaturan
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Keluar
                </Link>
            </DropdownMenuItem>
        </>
    );
}
