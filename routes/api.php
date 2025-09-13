<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TradingController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\MarketDataController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes (no authentication required for demo purposes)
Route::prefix('v1')->group(function () {
    
    // Dashboard Routes
    Route::get('/dashboard', [DashboardController::class, 'getDashboard']);
    Route::get('/dashboard/analytics', [DashboardController::class, 'getAnalytics']);

    // Trading Routes
    Route::prefix('trading')->group(function () {
        Route::post('/orders', [TradingController::class, 'submitOrder']);
        Route::get('/orders', [TradingController::class, 'getOrders']);
        Route::get('/orders/{trade}', [TradingController::class, 'getOrder']);
        Route::delete('/orders/{trade}', [TradingController::class, 'cancelOrder']);
        Route::post('/orders/{trade}/sync', [TradingController::class, 'syncOrderStatus']);
        Route::get('/stats', [TradingController::class, 'getTradingStats']);
    });

    // Portfolio Routes
    Route::prefix('portfolio')->group(function () {
        Route::get('/', [PortfolioController::class, 'getPortfolio']);
        Route::get('/positions', [PortfolioController::class, 'getPositions']);
        Route::get('/positions/{symbol}', [PortfolioController::class, 'getPosition']);
        Route::post('/positions/sync', [PortfolioController::class, 'syncPositions']);
        Route::get('/performance', [PortfolioController::class, 'getPerformance']);
        Route::get('/diversification', [PortfolioController::class, 'getDiversification']);
    });

    // Market Data Routes
    Route::prefix('market-data')->group(function () {
        Route::get('/quote/{symbol}', [MarketDataController::class, 'getQuote']);
        Route::get('/historical/{symbol}', [MarketDataController::class, 'getHistoricalData']);
        Route::get('/technical/{symbol}', [MarketDataController::class, 'getTechnicalIndicators']);
        Route::get('/company/{symbol}', [MarketDataController::class, 'getCompanyInfo']);
        Route::get('/search', [MarketDataController::class, 'searchSymbols']);
        Route::get('/news', [MarketDataController::class, 'getNews']);
        Route::get('/crypto', [MarketDataController::class, 'getCryptoPrices']);
        Route::get('/status', [MarketDataController::class, 'getMarketStatus']);
    });

    // Health Check Routes
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0',
            'services' => [
                'database' => 'connected',
                'cache' => 'available',
                'alpaca_api' => 'configured',
                'alpha_vantage' => 'configured',
                'news_api' => 'configured',
                'misbar_tracking' => 'enabled'
            ]
        ]);
    });

    // System Information Routes
    Route::get('/info', function () {
        return response()->json([
            'app_name' => config('app.name'),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'features' => [
                'paper_trading' => true,
                'real_trading' => false,
                'crypto_data' => true,
                'news_integration' => true,
                'technical_analysis' => true,
                'portfolio_analytics' => true,
                'misbar_monitoring' => true
            ],
            'api_endpoints' => [
                'trading' => '/api/v1/trading',
                'portfolio' => '/api/v1/portfolio',
                'market_data' => '/api/v1/market-data',
                'dashboard' => '/api/v1/dashboard'
            ]
        ]);
    });
});

// API Documentation route
Route::get('/docs', function () {
    return response()->json([
        'message' => 'Laravel Misbar Trading API Documentation',
        'version' => '1.0.0',
        'description' => 'A comprehensive trading API with multiple financial data provider integrations and HTTP traffic monitoring',
        'base_url' => config('app.url') . '/api/v1',
        'endpoints' => [
            'Dashboard' => [
                'GET /dashboard' => 'Get complete dashboard data including portfolio, trades, news, and analytics',
                'GET /dashboard/analytics' => 'Get detailed analytics and performance metrics'
            ],
            'Trading' => [
                'POST /trading/orders' => 'Submit a new trading order (buy/sell)',
                'GET /trading/orders' => 'List all trading orders with optional filters',
                'GET /trading/orders/{id}' => 'Get specific order details',
                'DELETE /trading/orders/{id}' => 'Cancel an active order',
                'POST /trading/orders/{id}/sync' => 'Sync order status with broker',
                'GET /trading/stats' => 'Get trading statistics and performance'
            ],
            'Portfolio' => [
                'GET /portfolio' => 'Get portfolio summary including account and positions',
                'GET /portfolio/positions' => 'List all current positions',
                'GET /portfolio/positions/{symbol}' => 'Get position details for specific symbol',
                'POST /portfolio/positions/sync' => 'Sync positions with broker',
                'GET /portfolio/performance' => 'Get portfolio performance metrics',
                'GET /portfolio/diversification' => 'Get portfolio diversification analysis'
            ],
            'Market Data' => [
                'GET /market-data/quote/{symbol}' => 'Get real-time quote for symbol',
                'GET /market-data/historical/{symbol}' => 'Get historical price data',
                'GET /market-data/technical/{symbol}' => 'Get technical indicators',
                'GET /market-data/company/{symbol}' => 'Get company information',
                'GET /market-data/search' => 'Search for symbols by keywords',
                'GET /market-data/news' => 'Get financial news with sentiment analysis',
                'GET /market-data/crypto' => 'Get cryptocurrency prices',
                'GET /market-data/status' => 'Get market status and hours'
            ],
            'System' => [
                'GET /health' => 'Health check endpoint',
                'GET /info' => 'System information and features',
                'GET /docs' => 'This API documentation'
            ]
        ],
        'authentication' => 'Not required for demo - would typically use Laravel Sanctum',
        'rate_limiting' => 'Configured via middleware',
        'monitoring' => 'All external API calls are monitored via Misbar HTTP capture',
        'data_providers' => [
            'Alpaca Markets' => 'Paper trading and market data',
            'Alpha Vantage' => 'Historical data and technical indicators',
            'News API' => 'Financial news and sentiment analysis',
            'CoinGecko' => 'Cryptocurrency data'
        ],
        'sample_requests' => [
            'Submit Buy Order' => [
                'method' => 'POST',
                'url' => '/api/v1/trading/orders',
                'body' => [
                    'symbol' => 'AAPL',
                    'quantity' => 10,
                    'side' => 'buy',
                    'type' => 'market'
                ]
            ],
            'Get Quote' => [
                'method' => 'GET',
                'url' => '/api/v1/market-data/quote/AAPL'
            ],
            'Get Portfolio' => [
                'method' => 'GET',
                'url' => '/api/v1/portfolio'
            ]
        ]
    ]);
});