<?php

namespace App\Listeners;


use App\User;
use Laravel\Passport\Events\AccessTokenCreated;

class ForceResetUserPassword
{

    public function handle(AccessTokenCreated $event)
    {
        $user = User::find($event->userId);
        if ($user) {
            $user->forceResetPassword();
        }
    }

}