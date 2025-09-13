<?php

namespace App\Http\Controllers;

use App\Services\TradingService;
use App\Services\MarketDataService;
use App\Services\NewsService;
use App\Models\Trade;
use App\Models\Position;
use App\Models\MarketData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private TradingService $tradingService;
    private MarketDataService $marketDataService;
    private NewsService $newsService;

    public function __construct(
        TradingService $tradingService,
        MarketDataService $marketDataService,
        NewsService $newsService
    ) {
        $this->tradingService = $tradingService;
        $this->marketDataService = $marketDataService;
        $this->newsService = $newsService;
    }

    public function getDashboard(): JsonResponse
    {
        try {
            $cacheKey = 'dashboard_data_' . auth()->id() ?? 'default';
            
            $dashboardData = Cache::remember($cacheKey, 300, function() {
                return [
                    'portfolio' => $this->getPortfolioSummary(),
                    'recent_trades' => $this->getRecentTrades(),
                    'market_overview' => $this->getMarketOverview(),
                    'news_headlines' => $this->getNewsHeadlines(),
                    'performance' => $this->getPerformanceMetrics(),
                    'watchlist' => $this->getWatchlist(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAnalytics(): JsonResponse
    {
        try {
            $analytics = [
                'trading_volume' => $this->getTradingVolumeAnalytics(),
                'win_rate' => $this->calculateWinRate(),
                'sector_allocation' => $this->getSectorAllocation(),
                'risk_metrics' => $this->getRiskMetrics(),
                'monthly_performance' => $this->getMonthlyPerformance(),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getPortfolioSummary(): array
    {
        try {
            return $this->tradingService->getPortfolioSummary();
        } catch (\Exception $e) {
            return [
                'account' => ['equity' => 0, 'cash' => 0, 'buying_power' => 0],
                'positions' => [],
                'total_positions' => 0,
                'total_unrealized_pl' => 0,
                'error' => 'Failed to load portfolio data'
            ];
        }
    }

    private function getRecentTrades(): array
    {
        return Trade::with('asset')
                   ->orderBy('created_at', 'desc')
                   ->limit(10)
                   ->get()
                   ->map(function ($trade) {
                       return [
                           'id' => $trade->id,
                           'symbol' => $trade->symbol,
                           'side' => $trade->side,
                           'type' => $trade->type,
                           'quantity' => $trade->quantity,
                           'price' => $trade->price,
                           'status' => $trade->status,
                           'created_at' => $trade->created_at,
                       ];
                   })
                   ->toArray();
    }

    private function getMarketOverview(): array
    {
        $indices = ['SPY', 'QQQ', 'IWM', 'VIX'];
        $overview = [];

        foreach ($indices as $symbol) {
            try {
                $quote = Cache::remember("market_overview_{$symbol}", 300, function() use ($symbol) {
                    return $this->marketDataService->getQuote($symbol);
                });

                if (isset($quote['Global Quote'])) {
                    $data = $quote['Global Quote'];
                    $overview[$symbol] = [
                        'price' => $data['05. price'] ?? 0,
                        'change' => $data['09. change'] ?? 0,
                        'change_percent' => $data['10. change percent'] ?? '0%',
                        'volume' => $data['06. volume'] ?? 0,
                    ];
                }
            } catch (\Exception $e) {
                $overview[$symbol] = ['error' => 'Data unavailable'];
            }
        }

        return $overview;
    }

    private function getNewsHeadlines(): array
    {
        try {
            $news = Cache::remember('dashboard_news', 900, function() {
                return $this->newsService->getFinancialNews(['pageSize' => 5]);
            });

            if (isset($news['articles'])) {
                return array_map(function($article) {
                    return [
                        'title' => $article['title'],
                        'description' => $article['description'],
                        'url' => $article['url'],
                        'published_at' => $article['publishedAt'],
                        'source' => $article['source']['name'] ?? 'Unknown',
                    ];
                }, array_slice($news['articles'], 0, 5));
            }

            return [];
        } catch (\Exception $e) {
            return [['title' => 'News unavailable', 'error' => $e->getMessage()]];
        }
    }

    private function getPerformanceMetrics(): array
    {
        $positions = Position::all();
        
        if ($positions->isEmpty()) {
            return [
                'total_value' => 0,
                'total_return' => 0,
                'total_return_percent' => 0,
                'day_change' => 0,
                'day_change_percent' => 0,
            ];
        }

        $totalValue = $positions->sum('market_value');
        $totalCostBasis = $positions->sum('cost_basis');
        $totalUnrealizedPL = $positions->sum('unrealized_pl');

        return [
            'total_value' => $totalValue,
            'total_return' => $totalUnrealizedPL,
            'total_return_percent' => $totalCostBasis > 0 ? ($totalUnrealizedPL / $totalCostBasis) * 100 : 0,
            'day_change' => 0, // Would need historical data to calculate
            'day_change_percent' => 0,
        ];
    }

    private function getWatchlist(): array
    {
        // Default watchlist - in a real app, this would be user-specific
        $symbols = ['AAPL', 'GOOGL', 'MSFT', 'TSLA', 'AMZN'];
        $watchlist = [];

        foreach ($symbols as $symbol) {
            try {
                $quote = Cache::remember("watchlist_{$symbol}", 180, function() use ($symbol) {
                    return $this->marketDataService->getQuote($symbol);
                });

                if (isset($quote['Global Quote'])) {
                    $data = $quote['Global Quote'];
                    $watchlist[] = [
                        'symbol' => $symbol,
                        'price' => $data['05. price'] ?? 0,
                        'change' => $data['09. change'] ?? 0,
                        'change_percent' => $data['10. change percent'] ?? '0%',
                    ];
                }
            } catch (\Exception $e) {
                $watchlist[] = [
                    'symbol' => $symbol,
                    'error' => 'Data unavailable'
                ];
            }
        }

        return $watchlist;
    }

    private function getTradingVolumeAnalytics(): array
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        $volumeData = Trade::where('status', 'filled')
                          ->where('created_at', '>=', $thirtyDaysAgo)
                          ->selectRaw('DATE(created_at) as date, SUM(filled_quantity) as volume')
                          ->groupBy('date')
                          ->orderBy('date')
                          ->get()
                          ->toArray();

        return [
            'daily_volume' => $volumeData,
            'total_volume' => array_sum(array_column($volumeData, 'volume')),
            'avg_daily_volume' => count($volumeData) > 0 ? array_sum(array_column($volumeData, 'volume')) / count($volumeData) : 0,
        ];
    }

    private function calculateWinRate(): array
    {
        $filledTrades = Trade::where('status', 'filled')
                           ->where('created_at', '>=', now()->subDays(90))
                           ->get();

        if ($filledTrades->isEmpty()) {
            return ['win_rate' => 0, 'total_trades' => 0, 'winning_trades' => 0];
        }

        // Simplified win rate calculation
        // In reality, you'd need to track P&L per trade
        $totalTrades = $filledTrades->count();
        $winningTrades = $filledTrades->where('side', 'sell')->count(); // Simplified assumption
        
        return [
            'win_rate' => $totalTrades > 0 ? ($winningTrades / $totalTrades) * 100 : 0,
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades,
            'losing_trades' => $totalTrades - $winningTrades,
        ];
    }

    private function getSectorAllocation(): array
    {
        // Simplified sector allocation - would need additional asset metadata
        $positions = Position::with('asset')->get();
        
        $sectorMap = [
            'AAPL' => 'Technology',
            'MSFT' => 'Technology',
            'GOOGL' => 'Technology',
            'TSLA' => 'Consumer Discretionary',
            'AMZN' => 'Consumer Discretionary',
            'JPM' => 'Financials',
            'JNJ' => 'Healthcare',
            'PG' => 'Consumer Staples',
            'XOM' => 'Energy',
        ];

        $sectors = [];
        $totalValue = $positions->sum('market_value');

        foreach ($positions as $position) {
            $sector = $sectorMap[$position->symbol] ?? 'Other';
            
            if (!isset($sectors[$sector])) {
                $sectors[$sector] = ['value' => 0, 'percentage' => 0];
            }
            
            $sectors[$sector]['value'] += $position->market_value;
        }

        foreach ($sectors as $sector => $data) {
            $sectors[$sector]['percentage'] = $totalValue > 0 ? ($data['value'] / $totalValue) * 100 : 0;
        }

        return $sectors;
    }

    private function getRiskMetrics(): array
    {
        $positions = Position::all();
        
        if ($positions->isEmpty()) {
            return [
                'beta' => 0,
                'sharpe_ratio' => 0,
                'max_drawdown' => 0,
                'concentration_risk' => 'low',
            ];
        }

        $totalValue = $positions->sum('market_value');
        $largestPosition = $positions->max('market_value');
        $concentrationPercent = $totalValue > 0 ? ($largestPosition / $totalValue) * 100 : 0;

        $concentrationRisk = 'low';
        if ($concentrationPercent > 25) {
            $concentrationRisk = 'high';
        } elseif ($concentrationPercent > 15) {
            $concentrationRisk = 'medium';
        }

        return [
            'beta' => 1.0, // Simplified - would need market correlation analysis
            'sharpe_ratio' => 0.0, // Would need historical returns and risk-free rate
            'max_drawdown' => 0.0, // Would need historical portfolio values
            'concentration_risk' => $concentrationRisk,
            'largest_position_percent' => round($concentrationPercent, 2),
        ];
    }

    private function getMonthlyPerformance(): array
    {
        // Simplified monthly performance - would need historical portfolio values
        $months = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $months[] = [
                'month' => $date->format('Y-m'),
                'return' => rand(-5, 10), // Placeholder data
                'trades' => Trade::whereYear('created_at', $date->year)
                                ->whereMonth('created_at', $date->month)
                                ->count(),
            ];
        }

        return $months;
    }
}