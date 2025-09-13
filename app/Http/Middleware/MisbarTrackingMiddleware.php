<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Misbar\HttpCapture\MisbarClient;
use Misbar\HttpCapture\MisbarConfig;
use Misbar\HttpCapture\FilterRule;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Symfony\Component\HttpFoundation\Response;

class MisbarTrackingMiddleware
{
    private ?MisbarClient $misbarClient = null;
    private static ?Client $httpClient = null;

    public function __construct()
    {
        $this->initializeMisbar();
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Set up HTTP client with Misbar tracking for this request context
        if ($this->misbarClient && Config::get('services.misbar.enabled', true)) {
            $this->setupHttpClientForRequest($request);
        }

        $response = $next($request);

        // Log API request metadata
        $this->logRequestMetadata($request, $response);

        return $response;
    }

    private function initializeMisbar(): void
    {
        try {
            $misbarConfig = Config::get('services.misbar');
            
            if (!$misbarConfig['enabled'] ?? true) {
                return;
            }

            $config = new MisbarConfig(
                backendUrl: $misbarConfig['backend_url'],
                source: $misbarConfig['source'] ?? 'laravel-trading-app',
                apiKey: $misbarConfig['api_key'],
                samplingRate: $misbarConfig['sampling_rate'] ?? 1.0,
                redactSensitiveHeaders: $misbarConfig['redact_sensitive_headers'] ?? true,
                maxBodySize: $misbarConfig['max_body_size'] ?? 10000
            );

            $this->misbarClient = new MisbarClient($config);

            // Add custom filters for trading application
            $this->addTradingFilters();

        } catch (\Exception $e) {
            Log::error('Failed to initialize Misbar client', [
                'error' => $e->getMessage(),
                'config' => $misbarConfig ?? []
            ]);
        }
    }

    private function addTradingFilters(): void
    {
        if (!$this->misbarClient) {
            return;
        }

        // Exclude health checks and internal monitoring
        $this->misbarClient->addFilter(new FilterRule(
            type: FilterRule::TYPE_EXCLUDE,
            urlPattern: '/health'
        ));

        $this->misbarClient->addFilter(new FilterRule(
            type: FilterRule::TYPE_EXCLUDE,
            urlPattern: '/metrics'
        ));

        $this->misbarClient->addFilter(new FilterRule(
            type: FilterRule::TYPE_EXCLUDE,
            urlPattern: '/favicon.ico'
        ));

        // Include only external API calls for financial data
        $this->misbarClient->addFilter(new FilterRule(
            type: FilterRule::TYPE_INCLUDE,
            urlPattern: 'api.alpaca.markets'
        ));

        $this->misbarClient->addFilter(new FilterRule(
            type: FilterRule::TYPE_INCLUDE,
            urlPattern: 'data.alpaca.markets'
        ));

        $this->misbarClient->addFilter(new FilterRule(
            type: FilterRule::TYPE_INCLUDE,
            urlPattern: 'alphavantage.co'
        ));

        $this->misbarClient->addFilter(new FilterRule(
            type: FilterRule::TYPE_INCLUDE,
            urlPattern: 'newsapi.org'
        ));

        $this->misbarClient->addFilter(new FilterRule(
            type: FilterRule::TYPE_INCLUDE,
            urlPattern: 'coingecko.com'
        ));

        // Add custom redaction rules for sensitive trading data
        $this->misbarClient->addCustomRedactionRule([
            'pattern' => '/APCA-API-KEY-ID.*/',
            'replacement' => '[REDACTED-API-KEY]'
        ]);

        $this->misbarClient->addCustomRedactionRule([
            'pattern' => '/APCA-API-SECRET-KEY.*/',
            'replacement' => '[REDACTED-SECRET-KEY]'
        ]);

        $this->misbarClient->addCustomRedactionRule([
            'pattern' => '/apikey=[^&\s]+/',
            'replacement' => 'apikey=[REDACTED]'
        ]);
    }

    private function setupHttpClientForRequest(Request $request): void
    {
        if (self::$httpClient) {
            return; // Already configured
        }

        try {
            $stack = HandlerStack::create();
            $stack->push($this->misbarClient->createGuzzleMiddleware());

            self::$httpClient = new Client([
                'handler' => $stack,
                'timeout' => 30,
                'connect_timeout' => 10,
            ]);

            // Store in application container for services to use
            app()->instance('misbar.http.client', self::$httpClient);

        } catch (\Exception $e) {
            Log::error('Failed to setup HTTP client with Misbar tracking', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function logRequestMetadata(Request $request, Response $response): void
    {
        // Only log API requests, not asset requests
        if (!$request->is('api/*')) {
            return;
        }

        $metadata = [
            'request_id' => $request->header('X-Request-ID', uniqid()),
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'response_time_ms' => $this->calculateResponseTime($request),
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'query_params' => $this->sanitizeQueryParams($request->query()),
        ];

        // Add user context if available
        if (auth()->check()) {
            $metadata['user_id'] = auth()->id();
        }

        // Add trading-specific context
        if ($request->is('api/trading/*') || $request->is('api/portfolio/*')) {
            $metadata['context'] = 'trading_api';
            
            if ($request->has('symbol')) {
                $metadata['symbol'] = strtoupper($request->get('symbol'));
            }
        } elseif ($request->is('api/market-data/*')) {
            $metadata['context'] = 'market_data_api';
        }

        Log::info('API Request Processed', $metadata);
    }

    private function calculateResponseTime(Request $request): float
    {
        $startTime = $request->server('REQUEST_TIME_FLOAT', microtime(true));
        return round((microtime(true) - $startTime) * 1000, 2);
    }

    private function sanitizeQueryParams(array $params): array
    {
        $sensitive = ['api_key', 'apikey', 'token', 'secret', 'password'];
        
        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), $sensitive)) {
                $params[$key] = '[REDACTED]';
            }
        }

        return $params;
    }

    public static function getTrackedHttpClient(): ?Client
    {
        return self::$httpClient;
    }
}