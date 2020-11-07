<?php

namespace App\Listeners;

use App\Config;
use App\Events\ProfileWasUpdated;
use App\Exceptions\ServiceException;
use App\Services\BillingService;
use App\User;

class PayProfileCompletionCoins
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
     * @param  ProfileWasUpdated $event
     * @param BillingService $billingService
     * @return void
     */
    public function handle(ProfileWasUpdated $event, BillingService $billingService)
    {
        $userId = $event->getUserId();
        $user = User::find($userId);
        if ($user) {
            if (!$user->completed_profile) {
                $completionPercent = $this->getCompletionStatus($user);
                if ($completionPercent == 100) {
                    $this->payCoins($user, $billingService);
                }
            }
        }
    }

    /**
     * @param User $user
     * @return float|int
     */
    private function getCompletionStatus(User $user)
    {
        $data = [
            'has_mail' => !!$user->email,
            'has_image' => !!$user->image,
            'has_name' => !!$user->name,
            'has_gender' => !!$user->gender,
            'has_date_of_birth' => !!$user->date_of_birth
        ];

        return (array_sum(array_values($data)) * 20);
    }

    private function payCoins(User $user, BillingService $billingService)
    {
        try {
            $amount = Config::getValue('profile_completion_coins');
            $transactions = [];
            $transactions[] = $billingService->depositCoin($user->id, $amount, 'users', $user->id);
            $billingService->createTransactions($transactions);
            $user->completed_profile = 1;
            $user->save();
        } catch (ServiceException $exception) {
            //pass.
            //dd($exception->getMessage());
        }

    }
}
