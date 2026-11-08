<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\RequirePassword;

/**
 * The `password.confirm` alias. An account made with Google has no password
 * to confirm, so it goes straight through until the owner sets one.
 */
class RequirePasswordIfSet extends RequirePassword
{
    protected function shouldConfirmPassword($request, $passwordTimeoutSeconds = null)
    {
        return $request->user()?->password !== null && parent::shouldConfirmPassword($request, $passwordTimeoutSeconds);
    }
}
