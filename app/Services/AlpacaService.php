<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class AlpacaService
{
    private Client $client;
    private string $baseUrl;
    private string $dataUrl;
    private array $headers;

    public function __construct()
    {
        $config = Config::get('services.alpaca');
        
        $this->baseUrl = $config['base_url'];
        $this->dataUrl = $config['data_url'];
        
        $this->headers = [
            'APCA-API-KEY-ID' => $config['api_key'],
            'APCA-API-SECRET-KEY' => $config['secret_key'],
            'Content-Type' => 'application/json',
        ];

        $this->client = new Client([
            'timeout' => 30,
            'headers' => $this->headers,
        ]);
    }

    public function getAccount(): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/v2/account");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Alpaca API error getting account', [
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            throw $e;
        }
    }

    public function getPositions(): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/v2/positions");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Alpaca API error getting positions', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPosition(string $symbol): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/v2/positions/{$symbol}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return []; // No position found
            }
            throw $e;
        }
    }

    public function submitOrder(array $orderData): array
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/v2/orders", [
                'json' => $orderData
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Order submitted to Alpaca', [
                'order_id' => $result['id'] ?? null,
                'symbol' => $orderData['symbol'],
                'side' => $orderData['side'],
                'qty' => $orderData['qty']
            ]);
            
            return $result;
        } catch (RequestException $e) {
            Log::error('Alpaca API error submitting order', [
                'error' => $e->getMessage(),
                'order_data' => $orderData,
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            throw $e;
        }
    }

    public function getOrders(array $params = []): array
    {
        try {
            $queryParams = http_build_query(array_merge([
                'status' => 'all',
                'limit' => 100,
                'direction' => 'desc'
            ], $params));
            
            $response = $this->client->get("{$this->baseUrl}/v2/orders?{$queryParams}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Alpaca API error getting orders', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getOrder(string $orderId): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/v2/orders/{$orderId}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Alpaca API error getting order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function cancelOrder(string $orderId): bool
    {
        try {
            $this->client->delete("{$this->baseUrl}/v2/orders/{$orderId}");
            return true;
        } catch (RequestException $e) {
            Log::error('Alpaca API error canceling order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getQuote(string $symbol): array
    {
        try {
            $response = $this->client->get("{$this->dataUrl}/v2/stocks/{$symbol}/quotes/latest", [
                'headers' => $this->headers
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Alpaca API error getting quote', [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getBars(string $symbol, array $params = []): array
    {
        try {
            $queryParams = http_build_query(array_merge([
                'timeframe' => '1Day',
                'limit' => 100,
            ], $params));
            
            $response = $this->client->get(
                "{$this->dataUrl}/v2/stocks/{$symbol}/bars?{$queryParams}",
                ['headers' => $this->headers]
            );
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Alpaca API error getting bars', [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getAssets(array $params = []): array
    {
        try {
            $queryParams = http_build_query(array_merge([
                'status' => 'active',
                'asset_class' => 'us_equity'
            ], $params));
            
            $response = $this->client->get("{$this->baseUrl}/v2/assets?{$queryParams}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Alpaca API error getting assets', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getAsset(string $symbol): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/v2/assets/{$symbol}");
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            Log::error('Alpaca API error getting asset', [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}