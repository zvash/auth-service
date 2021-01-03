<?php

namespace App\Services;


use App\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class NotificationService
{
    protected $headers;
    protected $client;

    /**
     * NotificationService constructor.
     */
    public function __construct()
    {
        $baseUrl = rtrim(env('NOTIFICATION_SERVICE_URL', 'notification-service.local'), '/') . '/';
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
        $this->client = new Client(
            [
                'base_uri' => $baseUrl,
                'timeout' => config('timeout', 5)
            ]
        );
    }

    public function sendLoginActivationCode(User $user, string $password)
    {
        $template = 'registration';
        $users = [$user->toArray()];
        $extraParams = ['code' => $password];
        $this->notifyUser($users, $template, $extraParams);
    }

    public function notifyUser(array $users, string $template, array $extraParams)
    {
        $payload = [
            'recipients' => $users,
            'template' => $template,
            'extra_params' => $extraParams
        ];
        try {
            $response = $this->client->request(
                'POST',
                $this->getNotifyUserUrlSuffix(),
                [
                    'json' => $payload
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                //handle contents
            }
        } catch (GuzzleException $exception) {

        }
    }

    /**
     * @param int $userId
     * @param string $playerId
     * @param null|string $platform
     * @param null|string $deviceToken
     * @return array
     */
    public function registerPlayer(int $userId, string $playerId, ?string $platform, ?string $deviceToken)
    {
        $payload = [
            'user_id' => $userId,
            'player_id' => $playerId,
            'platform' => $platform,
            'device_token' => $deviceToken
        ];
        try {
            $response = $this->client->request(
                'POST',
                $this->getRegisterPlayerIdUrlSuffix(),
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
            return ['data' => $exception->getMessage(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @param int $userId
     * @param null|string $platform
     * @param null|string $deviceToken
     * @param null|string $playerToken
     * @return array
     */
    public function removePlayer(int $userId, ?string $platform, ?string $deviceToken, ?string $playerToken)
    {
        $payload = [
            'user_id' => $userId,
            'platform' => $platform,
            'device_token' => $deviceToken,
            'player_token' => $playerToken,
        ];

        try {
            $response = $this->client->request(
                'POST',
                $this->getRemovePlayerIdUrlSuffix(),
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
            return ['data' => $exception->getMessage(), 'status' => $exception->getCode()];
        }
    }

    private function getNotifyUserUrlSuffix()
    {
        return 'api/v1/send';
    }

    private function getRegisterPlayerIdUrlSuffix()
    {
        return 'api/v1/player/register';
    }

    private function getRemovePlayerIdUrlSuffix()
    {
        return 'api/v1/player/remove';
    }
}