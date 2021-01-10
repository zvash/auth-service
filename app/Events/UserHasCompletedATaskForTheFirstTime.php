<?php

namespace App\Events;

use App\Services\AffiliateService;
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
     * @var AffiliateService $affiliateService
     */
    protected $affiliateService;

    /**
     * Create a new event instance.
     *
     * @param User $user
     * @param BillingService $billingService
     * @param AffiliateService $affiliateService
     */
    public function __construct(User $user, BillingService $billingService, AffiliateService $affiliateService)
    {
        $this->user = $user;
        $this->billingService = $billingService;
        $this->affiliateService = $affiliateService;
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

    /**
     * @return AffiliateService
     */
    public function getAffiliateService(): AffiliateService
    {
        return $this->affiliateService;
    }
}
