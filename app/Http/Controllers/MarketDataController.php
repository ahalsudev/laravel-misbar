<?php

namespace App\Http\Controllers;

use App\Services\MarketDataService;
use App\Services\NewsService;
use App\Services\AlpacaService;
use App\Models\MarketData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class MarketDataController extends Controller
{
    private MarketDataService $marketDataService;
    private NewsService $newsService;
    private AlpacaService $alpacaService;

    public function __construct(
        MarketDataService $marketDataService,
        NewsService $newsService,
        AlpacaService $alpacaService
    ) {
        $this->marketDataService = $marketDataService;
        $this->newsService = $newsService;
        $this->alpacaService = $alpacaService;
    }

    public function getQuote(string $symbol): JsonResponse
    {
        try {
            // Get quote from both Alpaca and Alpha Vantage
            $alpacaQuote = $this->alpacaService->getQuote($symbol);
            
            try {
                $alphaVantageQuote = $this->marketDataService->getQuote($symbol);
            } catch (\Exception $e) {
                $alphaVantageQuote = null;
            }

            $response = [
                'symbol' => strtoupper($symbol),
                'alpaca' => $alpacaQuote,
                'alpha_vantage' => $alphaVantageQuote,
                'timestamp' => now()->toISOString()
            ];

            // Store in local database
            MarketData::create([
                'symbol' => strtoupper($symbol),
                'data_type' => 'quote',
                'price' => $alpacaQuote['quote']['ap'] ?? null,
                'bid_price' => $alpacaQuote['quote']['bp'] ?? null,
                'ask_price' => $alpacaQuote['quote']['ap'] ?? null,
                'bid_size' => $alpacaQuote['quote']['bs'] ?? null,
                'ask_size' => $alpacaQuote['quote']['as'] ?? null,
                'market_timestamp' => $alpacaQuote['quote']['t'] ?? now(),
                'raw_data' => $response,
            ]);

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve quote',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getHistoricalData(string $symbol, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'timeframe' => 'nullable|in:1Day,1Hour,1Min,5Min,15Min,30Min',
            'start' => 'nullable|date',
            'end' => 'nullable|date|after:start',
            'limit' => 'nullable|integer|min:1|max:10000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $params = [
                'timeframe' => $request->get('timeframe', '1Day'),
                'limit' => $request->get('limit', 100)
            ];

            if ($request->has('start')) {
                $params['start'] = $request->get('start');
            }
            if ($request->has('end')) {
                $params['end'] = $request->get('end');
            }

            $data = $this->alpacaService->getBars($symbol, $params);

            return response()->json([
                'success' => true,
                'data' => $data,
                'params' => $params
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve historical data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTechnicalIndicators(string $symbol, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'function' => 'required|in:SMA,EMA,RSI,MACD,BBANDS,STOCH,ADX,CCI,AROON,MOM,BOP,ROC',
            'interval' => 'nullable|in:daily,weekly,monthly',
            'time_period' => 'nullable|integer|min:1|max:200',
            'series_type' => 'nullable|in:close,open,high,low'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $params = [
                'interval' => $request->get('interval', 'daily'),
                'time_period' => $request->get('time_period', 14),
                'series_type' => $request->get('series_type', 'close')
            ];

            $data = $this->marketDataService->getTechnicalIndicators(
                $symbol, 
                $request->get('function'), 
                $params
            );

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve technical indicators',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCompanyInfo(string $symbol): JsonResponse
    {
        try {
            $overview = $this->marketDataService->getCompanyOverview($symbol);

            return response()->json([
                'success' => true,
                'data' => $overview
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function searchSymbols(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'keywords' => 'required|string|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $results = $this->marketDataService->searchSymbols($request->get('keywords'));

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search symbols',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getNews(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:financial,market,stock,crypto',
            'symbol' => 'nullable|string|max:10',
            'page_size' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $type = $request->get('type', 'financial');
            $symbol = $request->get('symbol');
            $pageSize = $request->get('page_size', 20);

            switch ($type) {
                case 'stock':
                    if (!$symbol) {
                        throw new \InvalidArgumentException('Symbol is required for stock news');
                    }
                    $news = $this->newsService->getStockNews($symbol);
                    break;
                    
                case 'crypto':
                    $news = $this->newsService->getCryptoNews();
                    break;
                    
                case 'market':
                    $news = $this->newsService->getMarketNews();
                    break;
                    
                default:
                    $news = $this->newsService->getFinancialNews(['pageSize' => $pageSize]);
                    break;
            }

            // Add sentiment analysis
            if (isset($news['articles'])) {
                $sentimentAnalysis = $this->newsService->getSentimentAnalysis($news['articles']);
                $news['sentiment'] = [
                    'positive' => $sentimentAnalysis['positive'],
                    'negative' => $sentimentAnalysis['negative'],
                    'neutral' => $sentimentAnalysis['neutral'],
                    'total' => $sentimentAnalysis['total']
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $news
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve news',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCryptoPrices(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbols' => 'required|string',
            'vs_currency' => 'nullable|string|in:usd,eur,btc,eth'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $symbols = explode(',', $request->get('symbols'));
            $vsCurrency = $request->get('vs_currency', 'usd');
            $results = [];

            foreach ($symbols as $symbol) {
                $symbol = trim($symbol);
                try {
                    $data = $this->marketDataService->getCryptocurrencyData($symbol, $vsCurrency);
                    $results[$symbol] = $data;
                } catch (\Exception $e) {
                    $results[$symbol] = ['error' => $e->getMessage()];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve cryptocurrency prices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getMarketStatus(): JsonResponse
    {
        try {
            // This would typically come from a market data provider
            // For now, we'll create a simple market status response
            $now = now();
            $isWeekend = $now->isWeekend();
            $hour = $now->hour;
            $minute = $hour * 60 + $now->minute;
            
            // US market hours: 9:30 AM - 4:00 PM ET (14:30 - 21:00 UTC)
            $marketOpenMinutes = 14 * 60 + 30; // 14:30 UTC
            $marketCloseMinutes = 21 * 60; // 21:00 UTC
            $currentMinutes = $minute;

            $isOpen = !$isWeekend && 
                      $currentMinutes >= $marketOpenMinutes && 
                      $currentMinutes < $marketCloseMinutes;

            $status = [
                'is_open' => $isOpen,
                'is_weekend' => $isWeekend,
                'current_time' => $now->toISOString(),
                'timezone' => 'UTC',
                'next_open' => $isOpen ? null : $this->getNextMarketOpen($now),
                'next_close' => $isOpen ? $this->getNextMarketClose($now) : null,
                'markets' => [
                    'NYSE' => ['is_open' => $isOpen, 'timezone' => 'America/New_York'],
                    'NASDAQ' => ['is_open' => $isOpen, 'timezone' => 'America/New_York'],
                    'LSE' => ['is_open' => false, 'timezone' => 'Europe/London'], // Simplified
                    'TSE' => ['is_open' => false, 'timezone' => 'Asia/Tokyo'], // Simplified
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve market status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getNextMarketOpen($currentTime)
    {
        $nextOpen = $currentTime->copy();
        
        if ($currentTime->isWeekend()) {
            $nextOpen = $currentTime->next('Monday');
        } elseif ($currentTime->hour >= 21) {
            $nextOpen = $currentTime->addDay();
        }
        
        return $nextOpen->setTime(14, 30)->toISOString();
    }

    private function getNextMarketClose($currentTime)
    {
        if ($currentTime->hour < 21) {
            return $currentTime->copy()->setTime(21, 0)->toISOString();
        }
        
        return $currentTime->addDay()->setTime(21, 0)->toISOString();
    }
}