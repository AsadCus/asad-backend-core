<?php

namespace App\Auth\Traits;

use Illuminate\Auth\Passwords\CanResetPassword as BaseCanResetPassword;
use App\Notifications\ResetPassword as ResetPasswordNotification;

trait CanResetPassword
{
    use BaseCanResetPassword;

    public function getEmailForPasswordReset()
    {
        return $this->email;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
