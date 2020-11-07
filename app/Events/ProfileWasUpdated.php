<?php

namespace App\Events;

use App\Services\BillingService;

class ProfileWasUpdated extends Event
{
    /**
     * @var int $userId
     */
    protected $userId;

    /**
     * @var BillingService $billingService
     */
    protected $billingService;

    /**
     * Create a new event instance.
     *
     * @param int $userId
     * @param BillingService $billingService
     */
    public function __construct(int $userId, BillingService $billingService)
    {
        $this->userId = $userId;
        $this->billingService = $billingService;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    public function getBillingService()
    {
        return $this->billingService;
    }
}
