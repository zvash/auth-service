<?php

namespace App\Providers;

use Laravel\Lumen\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        \App\Events\ExampleEvent::class => [
            \App\Listeners\ExampleListener::class,
        ],
        \Laravel\Passport\Events\AccessTokenCreated::class => [
            \App\Listeners\ForceResetUserPassword::class,
        ],
        \App\Events\UserHasCompletedATaskForTheFirstTime::class => [
            \App\Listeners\PayReferralCoinsListener::class
        ]
    ];
}
