<?php

namespace App\Repositories;


use App\Exceptions\ServiceException;
use App\Services\BillingService;

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
        $sources['users'] = [];
        foreach ($users as $user) {
            $sources['users'][] = $user['id'];
        }
        if ($sources['users']) {
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
}