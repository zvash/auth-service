<?php

namespace App\Listeners;

use App\Config;
use App\Events\UserHasCompletedATaskForTheFirstTime;
use App\Exceptions\ServiceException;
use App\Services\BillingService;
use App\User;

class PayReferralCoinsListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  UserHasCompletedATaskForTheFirstTime $event
     * @return void
     */
    public function handle(UserHasCompletedATaskForTheFirstTime $event)
    {
        $user = $event->getUser();
        $billingService = $event->getBillingService();
        $affiliateService = $event->getAffiliateService();
        $referredUser = User::where('id', $user->referred_by)->first();
        if ($referredUser) {
            $affiliateService->acceptReferral($user->referred_by, $user->id);
//            try {
//                $amount = Config::getValue('refer_coins');
//                $transactions = [];
//                $transactions[] = $billingService->depositCoin($referredUser->id, $amount, 'users', $user->id);
//                $billingService->createTransactions($transactions);
//
//            } catch (ServiceException $exception) {
//                //pass.
//                dd($exception->getMessage());
//            }
        }
    }
}
