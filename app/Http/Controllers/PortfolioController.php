<?php

namespace App\Http\Controllers;

use App\Services\TradingService;
use App\Models\Position;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PortfolioController extends Controller
{
    private TradingService $tradingService;

    public function __construct(TradingService $tradingService)
    {
        $this->tradingService = $tradingService;
    }

    public function getPortfolio(): JsonResponse
    {
        try {
            $summary = $this->tradingService->getPortfolioSummary();

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve portfolio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPositions(): JsonResponse
    {
        try {
            $positions = Position::with('asset')
                               ->orderBy('market_value', 'desc')
                               ->get();

            $totalValue = $positions->sum('market_value');
            $totalUnrealizedPL = $positions->sum('unrealized_pl');

            return response()->json([
                'success' => true,
                'data' => [
                    'positions' => $positions,
                    'summary' => [
                        'total_positions' => $positions->count(),
                        'total_value' => $totalValue,
                        'total_unrealized_pl' => $totalUnrealizedPL,
                        'total_unrealized_pl_percent' => $totalValue > 0 ? ($totalUnrealizedPL / ($totalValue - $totalUnrealizedPL)) * 100 : 0
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve positions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPosition(string $symbol): JsonResponse
    {
        try {
            $position = Position::with('asset')->where('symbol', $symbol)->first();

            if (!$position) {
                return response()->json([
                    'success' => false,
                    'message' => 'Position not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $position
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve position',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function syncPositions(): JsonResponse
    {
        try {
            $this->tradingService->updatePositions();

            return response()->json([
                'success' => true,
                'message' => 'Positions synchronized successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync positions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPerformance(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 30);
            $startDate = now()->subDays($days);

            // Get current positions
            $currentPositions = Position::with('asset')->get();
            
            // Calculate performance metrics
            $performance = [
                'current_value' => $currentPositions->sum('market_value'),
                'total_unrealized_pl' => $currentPositions->sum('unrealized_pl'),
                'total_cost_basis' => $currentPositions->sum('cost_basis'),
                'positions_count' => $currentPositions->count(),
                'top_performers' => $currentPositions->sortByDesc('unrealized_plpc')->take(5)->values(),
                'worst_performers' => $currentPositions->sortBy('unrealized_plpc')->take(5)->values(),
                'asset_allocation' => $this->calculateAssetAllocation($currentPositions),
                'period_days' => $days
            ];

            // Add additional metrics
            if ($performance['total_cost_basis'] > 0) {
                $performance['total_return_percent'] = ($performance['total_unrealized_pl'] / $performance['total_cost_basis']) * 100;
            } else {
                $performance['total_return_percent'] = 0;
            }

            return response()->json([
                'success' => true,
                'data' => $performance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve performance data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDiversification(): JsonResponse
    {
        try {
            $positions = Position::with('asset')->get();
            
            if ($positions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_positions' => 0,
                        'concentration_risk' => 'low',
                        'largest_position_percent' => 0,
                        'top_5_concentration' => 0,
                        'asset_classes' => []
                    ]
                ]);
            }

            $totalValue = $positions->sum('market_value');
            $sortedPositions = $positions->sortByDesc('market_value');
            
            $largestPosition = $sortedPositions->first();
            $largestPositionPercent = $totalValue > 0 ? ($largestPosition->market_value / $totalValue) * 100 : 0;
            
            $top5Value = $sortedPositions->take(5)->sum('market_value');
            $top5Concentration = $totalValue > 0 ? ($top5Value / $totalValue) * 100 : 0;

            // Determine concentration risk
            $concentrationRisk = 'low';
            if ($largestPositionPercent > 25) {
                $concentrationRisk = 'high';
            } elseif ($largestPositionPercent > 15 || $top5Concentration > 60) {
                $concentrationRisk = 'medium';
            }

            // Asset class breakdown
            $assetClasses = $positions->groupBy('asset.asset_class')->map(function ($group) use ($totalValue) {
                $classValue = $group->sum('market_value');
                return [
                    'value' => $classValue,
                    'percentage' => $totalValue > 0 ? ($classValue / $totalValue) * 100 : 0,
                    'positions_count' => $group->count()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_positions' => $positions->count(),
                    'concentration_risk' => $concentrationRisk,
                    'largest_position_percent' => round($largestPositionPercent, 2),
                    'top_5_concentration' => round($top5Concentration, 2),
                    'asset_classes' => $assetClasses,
                    'position_weights' => $sortedPositions->take(10)->map(function ($position) use ($totalValue) {
                        return [
                            'symbol' => $position->symbol,
                            'value' => $position->market_value,
                            'percentage' => $totalValue > 0 ? ($position->market_value / $totalValue) * 100 : 0
                        ];
                    })->values()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve diversification data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateAssetAllocation($positions): array
    {
        $totalValue = $positions->sum('market_value');
        
        if ($totalValue == 0) {
            return [];
        }

        return $positions->groupBy('asset.asset_class')->map(function ($group, $assetClass) use ($totalValue) {
            $classValue = $group->sum('market_value');
            return [
                'asset_class' => $assetClass,
                'value' => $classValue,
                'percentage' => ($classValue / $totalValue) * 100,
                'positions_count' => $group->count()
            ];
        })->values()->toArray();
    }
}