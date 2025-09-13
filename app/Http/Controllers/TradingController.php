<?php

namespace App\Http\Controllers;

use App\Services\TradingService;
use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TradingController extends Controller
{
    private TradingService $tradingService;

    public function __construct(TradingService $tradingService)
    {
        $this->tradingService = $tradingService;
    }

    public function submitOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'symbol' => 'required|string|max:10',
            'quantity' => 'required|numeric|min:0.000001',
            'side' => 'required|in:buy,sell',
            'type' => 'nullable|in:market,limit,stop,stop_limit',
            'time_in_force' => 'nullable|in:day,gtc,ioc,fok',
            'price' => 'nullable|numeric|min:0',
            'stop_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $trade = $this->tradingService->submitOrder($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Order submitted successfully',
                'data' => [
                    'trade_id' => $trade->id,
                    'external_id' => $trade->external_id,
                    'symbol' => $trade->symbol,
                    'side' => $trade->side,
                    'type' => $trade->type,
                    'quantity' => $trade->quantity,
                    'price' => $trade->price,
                    'status' => $trade->status,
                    'submitted_at' => $trade->submitted_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancelOrder(Trade $trade): JsonResponse
    {
        try {
            $success = $this->tradingService->cancelOrder($trade);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Order canceled successfully',
                    'data' => [
                        'trade_id' => $trade->id,
                        'status' => $trade->fresh()->status
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOrders(Request $request): JsonResponse
    {
        try {
            $query = Trade::with('asset');

            if ($request->has('symbol')) {
                $query->where('symbol', $request->get('symbol'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('side')) {
                $query->where('side', $request->get('side'));
            }

            $trades = $query->orderBy('created_at', 'desc')
                           ->paginate($request->get('per_page', 25));

            return response()->json([
                'success' => true,
                'data' => $trades
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOrder(Trade $trade): JsonResponse
    {
        try {
            $trade->load('asset');

            return response()->json([
                'success' => true,
                'data' => $trade
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function syncOrderStatus(Trade $trade): JsonResponse
    {
        try {
            $updatedTrade = $this->tradingService->syncOrderStatus($trade);

            return response()->json([
                'success' => true,
                'message' => 'Order status synchronized',
                'data' => $updatedTrade
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTradingStats(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('from', now()->subDays(30)->toDateString());
            $dateTo = $request->get('to', now()->toDateString());

            $stats = [
                'total_trades' => Trade::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                'filled_trades' => Trade::where('status', 'filled')
                                      ->whereBetween('created_at', [$dateFrom, $dateTo])
                                      ->count(),
                'canceled_trades' => Trade::where('status', 'canceled')
                                        ->whereBetween('created_at', [$dateFrom, $dateTo])
                                        ->count(),
                'total_volume' => Trade::where('status', 'filled')
                                      ->whereBetween('created_at', [$dateFrom, $dateTo])
                                      ->sum('filled_quantity'),
                'buy_orders' => Trade::where('side', 'buy')
                                   ->whereBetween('created_at', [$dateFrom, $dateTo])
                                   ->count(),
                'sell_orders' => Trade::where('side', 'sell')
                                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                                    ->count(),
                'most_traded_symbols' => Trade::whereBetween('created_at', [$dateFrom, $dateTo])
                                            ->selectRaw('symbol, COUNT(*) as trade_count, SUM(filled_quantity) as total_quantity')
                                            ->groupBy('symbol')
                                            ->orderBy('trade_count', 'desc')
                                            ->limit(10)
                                            ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trading stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}