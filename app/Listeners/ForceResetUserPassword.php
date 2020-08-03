<?php

namespace App\Listeners;


use App\User;
use Laravel\Passport\Events\AccessTokenCreated;

class ForceResetUserPassword
{

    public function handle(AccessTokenCreated $event)
    {
        $user = User::with('roles')->find($event->userId);
        if ($user && !in_array('admin', $user->roles->pluck('name')->toArray())) {
            $user->forceResetPassword();
        }
    }

}