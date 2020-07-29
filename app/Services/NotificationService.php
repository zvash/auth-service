<?php

namespace App\Services;


use App\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;

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

    private function getNotifyUserUrlSuffix()
    {
        return 'api/v1/send';
    }
}