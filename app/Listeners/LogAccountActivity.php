<?php

namespace App\Listeners;

use App\Enums\ActivityCategory;
use App\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

/**
 * Sign-ins and security changes for the audit log (category Akun). The
 * account is the subject, so a failed sign-in shows up in the log of the
 * business whose email was tried. Passwords and codes are never logged.
 */
class LogAccountActivity
{
    public function handleLogin(Login $event): void
    {
        $this->log(ActivityEvent::Login, $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        $this->log(ActivityEvent::Logout, $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $this->log(ActivityEvent::LoginFailed, $event->user, ['email' => $event->credentials['email'] ?? null], anonymous: true);
    }

    public function handleLockout(Lockout $event): void
    {
        $email = (string) $event->request->input('email');

        $this->log(ActivityEvent::Lockout, User::query()->where('email', $email)->first(), ['email' => $email], anonymous: true);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->log(ActivityEvent::PasswordReset, $event->user);
    }

    public function handlePasswordUpdated(PasswordUpdatedViaController $event): void
    {
        $this->log(ActivityEvent::PasswordChanged, $event->user);
    }

    public function handleTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->log(ActivityEvent::TwoFactorEnabled, $event->user);
    }

    public function handleTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->log(ActivityEvent::TwoFactorDisabled, $event->user);
    }

    /**
     * @param  array<string, string|null>  $properties
     */
    private function log(ActivityEvent $event, mixed $user, array $properties = [], bool $anonymous = false): void
    {
        $user = $user instanceof User ? $user : null;
        $logger = activity(ActivityCategory::Akun)
            ->event($event->value)
            ->withProperties(array_filter($properties + [
                'ip' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 200, ''),
            ]));

        if ($user !== null) {
            $logger->performedOn($user);
        }

        // A failed attempt was made by whoever typed the password, not by the account owner.
        $anonymous ? $logger->causedByAnonymous() : $logger->causedBy($user);

        $logger->log($event->verb());
    }
}
