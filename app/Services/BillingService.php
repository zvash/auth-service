<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Exception\GuzzleException;

class BillingService
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
        $baseUrl = rtrim(env('BILLING_SERVICE_URL', 'internal-billing'), '/') . '/';
        $this->headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Service-Token' => env('BILLING_SERVICE_TOKEN', '')
        ];
        $this->client = new Client(
            [
                'base_uri' => $baseUrl,
                'timeout' => config('timeout', 5)
            ]
        );
    }

    /**
     * @param array $transactions
     * @return array
     */
    public function createTransactions(array $transactions)
    {
        $payload = [
            'transactions' => $transactions
        ];
        try {
            $response = $this->client->request(
                'POST',
                $this->getCreateTransactionsUrlSuffix(),
                [
                    'json' => $payload,
                    'headers' => $this->headers
                ]
            );
            if ($response->getStatusCode() == 200) {
                $contents = json_decode($response->getBody()->getContents(), 1);
                return ['data' => $contents['data'], 'status' => 200];
            }
            return ['data' => ['json' => $response->getBody()->getContents()], 'status' => $response->getStatusCode()];
        } catch (GuzzleException $exception) {
            if ($exception->getCode() == 401) {
                return ['data' => ['json' => $exception->getResponse()->getBody()->getContents()], 'status' => $exception->getCode()];
            }
            return ['data' => $exception->getResponse()->getBody()->getContents(), 'status' => $exception->getCode()];
        }
    }

    /**
     * @param int $userId
     * @param int $amount
     * @param string $sourceType
     * @param int $sourceId
     * @return array
     */
    public function withdrawCoin(int $userId, int $amount, string $sourceType, int $sourceId)
    {
        return $this->transactionMaker(
            $userId,
            'withdraw-coin',
            $amount,
            'COIN',
            $sourceType,
            $sourceId,
            "$sourceType-withdraw-coin"
        );
    }

    /**
     * @param int $userId
     * @param int $amount
     * @param string $sourceType
     * @param int $sourceId
     * @return array
     */
    public function depositCoin(int $userId, int $amount, string $sourceType, int $sourceId)
    {
        return $this->transactionMaker(
            $userId,
            'deposit-coin',
            $amount,
            'COIN',
            $sourceType,
            $sourceId,
            "$sourceType-deposit-coin"
        );
    }

    /**
     * @param int $userId
     * @param float $amount
     * @param string $currency
     * @param string $sourceType
     * @param int $sourceId
     * @return array
     */
    public function withdrawMoney(int $userId, float $amount, string $currency, string $sourceType, int $sourceId)
    {
        return $this->transactionMaker(
            $userId,
            'withdraw-money',
            $amount,
            $currency,
            $sourceType,
            $sourceId,
            "$sourceType-withdraw-money"
        );
    }

    /**
     * @param int $userId
     * @param float $amount
     * @param string $currency
     * @param string $sourceType
     * @param int $sourceId
     * @return array
     */
    public function depositMoney(int $userId, float $amount, string $currency, string $sourceType, int $sourceId)
    {
        return $this->transactionMaker(
            $userId,
            'deposit-money',
            $amount,
            $currency,
            $sourceType,
            $sourceId,
            "$sourceType-deposit-money"
        );
    }

    /**
     * @param array $sources
     * @return array
     */
    public function getSourcesBalances(array $sources)
    {
        $payload = ['sources' => []];
        foreach ($sources as $sourceType => $ids) {
            $source = [];
            $source['source_type'] = $sourceType;
            $source['ids'] = $ids;
            $payload['sources'][] = $source;
        }

        try {
            $response = $this->client->request(
                'POST',
                $this->getSourcesBalance(),
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
     * @param string $action
     * @param float $amount
     * @param string $currency
     * @param string $sourceType
     * @param int $sourceId
     * @param string $description
     * @param array $extraParams
     * @return array
     */
    private function transactionMaker(int $userId, string $action, float $amount, string $currency, string $sourceType, int $sourceId, string $description = '', array $extraParams = [])
    {
        $transaction = [
            'action' => $action,
            'amount' => $amount,
            'currency' => $currency,
            'user_id' => $userId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'description' => $description,
            'extra_params' => json_encode($extraParams)
        ];
        return $transaction;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    private function getHeadersFromRequest(Request $request)
    {
        $headers = $request->headers->all();
        $formattedHeaders = [];
        foreach ($headers as $key => $record) {
            if (in_array(strtolower($key), ['content-length'])) {
                continue;
            }
            if (is_array($record)) {
                $value = $record[0];
            } else {
                $value = $record;
            }
            $key = implode('-', array_map('ucfirst', explode('-', $key)));
            $formattedHeaders[$key] = $value;
        }
        return $formattedHeaders;
    }

    private function getCreateTransactionsUrlSuffix()
    {
        return 'api/v1/transactions/create';
    }

    /**
     * @return string
     */
    private function getSourcesBalance()
    {
        return 'api/v1/transactions/sources/balance';
    }
}