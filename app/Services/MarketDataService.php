<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

class MarketDataService
{
    private Client $client;
    private string $alphaVantageKey;
    private string $alphaVantageUrl;

    public function __construct()
    {
        $this->client = new Client(['timeout' => 30]);
        $this->alphaVantageKey = Config::get('services.alpha_vantage.api_key');
        $this->alphaVantageUrl = Config::get('services.alpha_vantage.base_url');
    }

    public function getIntradayData(string $symbol, string $interval = '5min'): array
    {
        $cacheKey = "intraday_{$symbol}_{$interval}";
        
        return Cache::remember($cacheKey, 300, function() use ($symbol, $interval) {
            try {
                $response = $this->client->get($this->alphaVantageUrl, [
                    'query' => [
                        'function' => 'TIME_SERIES_INTRADAY',
                        'symbol' => $symbol,
                        'interval' => $interval,
                        'apikey' => $this->alphaVantageKey,
                        'outputsize' => 'compact'
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if (isset($data['Error Message'])) {
                    throw new \Exception("Alpha Vantage API error: " . $data['Error Message']);
                }
                
                return $data;
            } catch (RequestException $e) {
                Log::error('Alpha Vantage API error getting intraday data', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    public function getDailyData(string $symbol, bool $compact = true): array
    {
        $cacheKey = "daily_{$symbol}_" . ($compact ? 'compact' : 'full');
        
        return Cache::remember($cacheKey, 3600, function() use ($symbol, $compact) {
            try {
                $response = $this->client->get($this->alphaVantageUrl, [
                    'query' => [
                        'function' => 'TIME_SERIES_DAILY',
                        'symbol' => $symbol,
                        'apikey' => $this->alphaVantageKey,
                        'outputsize' => $compact ? 'compact' : 'full'
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if (isset($data['Error Message'])) {
                    throw new \Exception("Alpha Vantage API error: " . $data['Error Message']);
                }
                
                return $data;
            } catch (RequestException $e) {
                Log::error('Alpha Vantage API error getting daily data', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    public function getTechnicalIndicators(string $symbol, string $function, array $params = []): array
    {
        $cacheKey = "technical_{$symbol}_{$function}_" . md5(serialize($params));
        
        return Cache::remember($cacheKey, 3600, function() use ($symbol, $function, $params) {
            try {
                $queryParams = array_merge([
                    'function' => $function,
                    'symbol' => $symbol,
                    'apikey' => $this->alphaVantageKey,
                    'interval' => 'daily'
                ], $params);

                $response = $this->client->get($this->alphaVantageUrl, [
                    'query' => $queryParams
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if (isset($data['Error Message'])) {
                    throw new \Exception("Alpha Vantage API error: " . $data['Error Message']);
                }
                
                return $data;
            } catch (RequestException $e) {
                Log::error('Alpha Vantage API error getting technical indicators', [
                    'symbol' => $symbol,
                    'function' => $function,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    public function getQuote(string $symbol): array
    {
        $cacheKey = "quote_{$symbol}";
        
        return Cache::remember($cacheKey, 60, function() use ($symbol) {
            try {
                $response = $this->client->get($this->alphaVantageUrl, [
                    'query' => [
                        'function' => 'GLOBAL_QUOTE',
                        'symbol' => $symbol,
                        'apikey' => $this->alphaVantageKey
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if (isset($data['Error Message'])) {
                    throw new \Exception("Alpha Vantage API error: " . $data['Error Message']);
                }
                
                return $data;
            } catch (RequestException $e) {
                Log::error('Alpha Vantage API error getting quote', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    public function getCompanyOverview(string $symbol): array
    {
        $cacheKey = "overview_{$symbol}";
        
        return Cache::remember($cacheKey, 86400, function() use ($symbol) {
            try {
                $response = $this->client->get($this->alphaVantageUrl, [
                    'query' => [
                        'function' => 'OVERVIEW',
                        'symbol' => $symbol,
                        'apikey' => $this->alphaVantageKey
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if (isset($data['Error Message'])) {
                    throw new \Exception("Alpha Vantage API error: " . $data['Error Message']);
                }
                
                return $data;
            } catch (RequestException $e) {
                Log::error('Alpha Vantage API error getting company overview', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    public function searchSymbols(string $keywords): array
    {
        $cacheKey = "search_" . md5($keywords);
        
        return Cache::remember($cacheKey, 3600, function() use ($keywords) {
            try {
                $response = $this->client->get($this->alphaVantageUrl, [
                    'query' => [
                        'function' => 'SYMBOL_SEARCH',
                        'keywords' => $keywords,
                        'apikey' => $this->alphaVantageKey
                    ]
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                
                if (isset($data['Error Message'])) {
                    throw new \Exception("Alpha Vantage API error: " . $data['Error Message']);
                }
                
                return $data;
            } catch (RequestException $e) {
                Log::error('Alpha Vantage API error searching symbols', [
                    'keywords' => $keywords,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    public function getCryptocurrencyData(string $symbol, string $market = 'USD'): array
    {
        $cacheKey = "crypto_{$symbol}_{$market}";
        
        return Cache::remember($cacheKey, 300, function() use ($symbol, $market) {
            try {
                $response = $this->client->get('https://api.coingecko.com/api/v3/simple/price', [
                    'query' => [
                        'ids' => strtolower($symbol),
                        'vs_currencies' => strtolower($market),
                        'include_24hr_change' => 'true',
                        'include_24hr_vol' => 'true',
                        'include_last_updated_at' => 'true'
                    ]
                ]);

                return json_decode($response->getBody()->getContents(), true);
            } catch (RequestException $e) {
                Log::error('CoinGecko API error getting crypto data', [
                    'symbol' => $symbol,
                    'market' => $market,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }
}