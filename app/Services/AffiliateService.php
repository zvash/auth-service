<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;

class AffiliateService
{
    /**
     * @var array $headers
     */
    protected $headers;

    /**
     * @var Client $client
     */
    protected $client;

    /**
     * AuthService constructor.
     */
    public function __construct()
    {
        $baseUrl = rtrim(env('AFFILIATE_MANAGEMENT_SERVICE_URL', 'internal-affiliate'), '/') . '/';
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Service-Token' => env('AFFILIATE_MANAGEMENT_SERVICE_TOKEN', '')
        ];
        $this->client = new Client(
            [
                'base_uri' => $baseUrl,
                'timeout' => config('timeout', 5)
            ]
        );
    }

    /**
     * @param int $taskId
     * @param int $userId
     * @param int $offerId
     * @return array
     */
    public function registerClick(int $taskId, int $userId, int $offerId)
    {
        $payload = [
            'task_id' => $taskId,
            'user_id' => $userId,
            'offer_id' => $offerId
        ];

        try {
            $response = $this->client->request(
                'POST',
                $this->getRegisterClickUrl(),
                [
                    'json' => $payload,
                    'headers' => $this->headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
        } catch (GuzzleException $exception) {
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @param int $userId
     * @param string $claimableType
     * @param int $claimableId
     * @param int $coinReward
     * @param null|string $remoteId
     * @return array
     */
    public function registerClaim(int $userId, string $claimableType, int $claimableId, int $coinReward, ?string $remoteId = null)
    {
        $payload = [
            'user_id' => $userId,
            'claimable_type' => $claimableType,
            'claimable_id' => $claimableId,
            'coin_reward' => $coinReward
        ];
        if ($remoteId) {
            $payload['remote_id'] = $remoteId;
        }

        try {
            $response = $this->client->request(
                'POST',
                $this->getRegisterClaimUrl(),
                [
                    'json' => $payload,
                    'headers' => $this->headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
        } catch (GuzzleException $exception) {

            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    public function acceptReferral(int $userId, int $claimableId)
    {
        $payload = [
            'user_id' => $userId,
            'claimable_id' => $claimableId
        ];

        try {
            $response = $this->client->request(
                'POST',
                $this->getAcceptReferralUrl(),
                [
                    'json' => $payload,
                    'headers' => $this->headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
        } catch (GuzzleException $exception) {
            dd($exception->getMessage());
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @param int $userId
     * @param int $page
     * @return array
     */
    public function getClicks(int $userId, int $page = 1)
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->getAllClicksUrl($userId, $page),
                [
                    'headers' => $this->headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
        } catch (GuzzleException $exception) {
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @param int $userId
     * @param int $page
     * @return array
     */
    public function getClaims(int $userId, int $page = 1)
    {
        try {
            $response = $this->client->request(
                'GET',
                $this->getAllClaimsUrl($userId, $page),
                [
                    'headers' => $this->headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
        } catch (GuzzleException $exception) {
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    public function claim(int $clickId, int $userId)
    {
        $payload = [
            'click_id' => $clickId,
            'user_id' => $userId,
        ];

        try {
            $response = $this->client->request(
                'POST',
                $this->getClaimUrl(),
                [
                    'json' => $payload,
                    'headers' => $this->headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
        } catch (GuzzleException $exception) {
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @return string
     */
    private function getRegisterClickUrl()
    {
        return 'api/v1/clicks/create';
    }

    /**
     * @return string
     */
    private function getRegisterClaimUrl()
    {
        return 'api/v1/claims/create';
    }

    /**
     * @return string
     */
    private function getAcceptReferralUrl()
    {
        return 'api/v1/claims/accept/referral';
    }

    /**
     * @param int $userId
     * @param int $page
     * @return string
     */
    private function getAllClicksUrl(int $userId, int $page = 1)
    {
        return "api/v1/clicks/all?user_id={$userId}&page={$page}";
    }

    /**
     * @param int $userId
     * @param int $page
     * @return string
     */
    private function getAllClaimsUrl(int $userId, int $page = 1)
    {
        return "api/v1/claims/all?user_id={$userId}&page={$page}";
    }

    /**
     * @return string
     */
    private function getClaimUrl()
    {
        return 'api/v1/clicks/claim';
    }
}