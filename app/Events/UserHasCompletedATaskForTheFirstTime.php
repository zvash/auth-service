<?php

namespace App\Events;

use App\Services\BillingService;
use App\User;

class UserHasCompletedATaskForTheFirstTime extends Event
{
    /**
     * @var User $user
     */
    protected $user;

    /**
     * @var BillingService $billingService
     */
    protected $billingService;

    /**
     * Create a new event instance.
     *
     * @param User $user
     * @param BillingService $billingService
     */
    public function __construct(User $user, BillingService $billingService)
    {
        $this->user = $user;
        $this->billingService = $billingService;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return BillingService
     */
    public function getBillingService()
    {
        return $this->billingService;
    }
}
