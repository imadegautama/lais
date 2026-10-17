import {
    CircleCheck,
    CircleX,
    Info,
    LoaderCircle,
    TriangleAlert,
    type LucideIcon,
} from 'lucide-react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { cn } from '@/lib/utils';

// Same tones as the status badges: a soft chip with the status color, never color alone.
function ToastIcon({
    icon: Icon,
    className,
}: {
    icon: LucideIcon;
    className: string;
}) {
    return (
        <span
            className={cn(
                'flex size-8 items-center justify-center rounded-full',
                className,
            )}
        >
            <Icon className="size-4" aria-hidden />
        </span>
    );
}

function Toaster(props: ToasterProps) {
    useFlashToast();

    return (
        <Sonner
            theme="light"
            position="bottom-right"
            // --toast-offset-bottom clears the phone and tablet bottom nav (app.css).
            offset={{ bottom: 'var(--toast-offset-bottom)', right: '1.5rem' }}
            mobileOffset={{ bottom: 'var(--toast-offset-bottom)' }}
            icons={{
                success: (
                    <ToastIcon
                        icon={CircleCheck}
                        className="bg-success-soft text-success"
                    />
                ),
                error: (
                    <ToastIcon
                        icon={CircleX}
                        className="bg-destructive-soft text-destructive"
                    />
                ),
                warning: (
                    <ToastIcon
                        icon={TriangleAlert}
                        className="bg-warning-soft text-warning"
                    />
                ),
                info: (
                    <ToastIcon
                        icon={Info}
                        className="bg-accent text-accent-foreground"
                    />
                ),
                loading: (
                    <ToastIcon
                        icon={LoaderCircle}
                        className="bg-secondary text-muted-foreground [&_svg]:animate-spin"
                    />
                ),
            }}
            toastOptions={{
                unstyled: true,
                classNames: {
                    toast: 'flex w-full items-center gap-3 rounded-xl border bg-card p-4 font-sans text-card-foreground shadow-toast',
                    icon: 'shrink-0',
                    content: 'flex min-w-0 flex-1 flex-col gap-0.5',
                    title: 'text-sm font-semibold',
                    description: 'text-sm text-muted-foreground',
                },
            }}
            {...props}
        />
    );
}

export { Toaster };
