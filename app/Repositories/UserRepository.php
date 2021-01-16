<?php

namespace App\Repositories;


use App\Exceptions\ServiceException;
use App\Services\BillingService;
use App\User;

class UserRepository
{

    /**
     * @param array $users
     * @param BillingService $billingService
     * @return mixed
     * @throws ServiceException
     */
    public function getCoinsFromReferrals(array $users, BillingService $billingService)
    {
        $sources['referrals'] = [];
        foreach ($users as $user) {
            $sources['referrals'][] = $user['id'];
        }
        if ($sources['referrals']) {
            $response = $billingService->getSourcesBalances($sources);
            if ($response['status'] == 200) {
                return $response['data'];
            }
        } else {
            return [];
        }
        throw new ServiceException('Could not get balances.', [
            'message' => 'Could not get balances.',
            'data' => $response['data'],
            'code' => $response['status']
        ]);
    }

    /**
     * @param User $user
     * @param null|string $iban
     * @return User
     */
    public function setIBan(User $user, ?string $iban)
    {
        $user->iban = $iban;
        $user->save();
        return $user;
    }

    /**
     * @param User $user
     * @return mixed
     */
    public function getIBan(User $user)
    {
        $user = User::find($user->id);
        if ($user) {
            return $user->iban;
        }
        return null;
    }
}