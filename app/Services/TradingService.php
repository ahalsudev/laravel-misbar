<?php

namespace App\Services;

use App\Models\Trade;
use App\Models\Position;
use App\Models\Asset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TradingService
{
    private AlpacaService $alpacaService;
    private MarketDataService $marketDataService;

    public function __construct(
        AlpacaService $alpacaService, 
        MarketDataService $marketDataService
    ) {
        $this->alpacaService = $alpacaService;
        $this->marketDataService = $marketDataService;
    }

    public function submitOrder(array $orderData): Trade
    {
        DB::beginTransaction();
        
        try {
            // Validate order data
            $this->validateOrderData($orderData);
            
            // Get or create asset
            $asset = $this->getOrCreateAsset($orderData['symbol']);
            
            // Submit order to Alpaca
            $alpacaOrder = $this->alpacaService->submitOrder([
                'symbol' => $orderData['symbol'],
                'qty' => $orderData['quantity'],
                'side' => $orderData['side'],
                'type' => $orderData['type'] ?? 'market',
                'time_in_force' => $orderData['time_in_force'] ?? 'day',
                'limit_price' => $orderData['price'] ?? null,
                'stop_price' => $orderData['stop_price'] ?? null,
            ]);

            // Create local trade record
            $trade = Trade::create([
                'external_id' => $alpacaOrder['id'],
                'asset_id' => $asset->id,
                'symbol' => $orderData['symbol'],
                'side' => $orderData['side'],
                'type' => $orderData['type'] ?? 'market',
                'time_in_force' => $orderData['time_in_force'] ?? 'day',
                'quantity' => $orderData['quantity'],
                'price' => $orderData['price'] ?? null,
                'stop_price' => $orderData['stop_price'] ?? null,
                'status' => $alpacaOrder['status'],
                'submitted_at' => now(),
                'metadata' => [
                    'alpaca_order' => $alpacaOrder,
                    'user_metadata' => $orderData['metadata'] ?? []
                ]
            ]);

            DB::commit();
            
            Log::info('Order submitted successfully', [
                'trade_id' => $trade->id,
                'external_id' => $trade->external_id,
                'symbol' => $trade->symbol,
                'side' => $trade->side,
                'quantity' => $trade->quantity
            ]);

            return $trade;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to submit order', [
                'error' => $e->getMessage(),
                'order_data' => $orderData
            ]);
            
            throw $e;
        }
    }

    public function cancelOrder(Trade $trade): bool
    {
        try {
            if (!$trade->external_id) {
                throw new \Exception('Trade has no external ID');
            }

            $this->alpacaService->cancelOrder($trade->external_id);
            
            $trade->update([
                'status' => 'canceled',
                'canceled_at' => now()
            ]);

            Log::info('Order canceled successfully', [
                'trade_id' => $trade->id,
                'external_id' => $trade->external_id
            ]);

            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to cancel order', [
                'trade_id' => $trade->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    public function syncOrderStatus(Trade $trade): Trade
    {
        try {
            if (!$trade->external_id) {
                throw new \Exception('Trade has no external ID');
            }

            $alpacaOrder = $this->alpacaService->getOrder($trade->external_id);
            
            $updates = [
                'status' => $alpacaOrder['status'],
                'filled_quantity' => $alpacaOrder['filled_qty'] ?? 0,
                'filled_avg_price' => $alpacaOrder['filled_avg_price'] ?? null,
            ];

            if (isset($alpacaOrder['filled_at']) && $alpacaOrder['filled_at']) {
                $updates['filled_at'] = $alpacaOrder['filled_at'];
            }

            if (isset($alpacaOrder['canceled_at']) && $alpacaOrder['canceled_at']) {
                $updates['canceled_at'] = $alpacaOrder['canceled_at'];
            }

            if (isset($alpacaOrder['expired_at']) && $alpacaOrder['expired_at']) {
                $updates['expired_at'] = $alpacaOrder['expired_at'];
            }

            $trade->update($updates);

            // Update position if order is filled
            if (in_array($alpacaOrder['status'], ['filled', 'partially_filled'])) {
                $this->updatePosition($trade);
            }

            return $trade->fresh();
            
        } catch (\Exception $e) {
            Log::error('Failed to sync order status', [
                'trade_id' => $trade->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    public function updatePositions(): void
    {
        try {
            $alpacaPositions = $this->alpacaService->getPositions();
            
            foreach ($alpacaPositions as $alpacaPosition) {
                $asset = $this->getOrCreateAsset($alpacaPosition['symbol']);
                
                Position::updateOrCreate(
                    ['symbol' => $alpacaPosition['symbol']],
                    [
                        'asset_id' => $asset->id,
                        'quantity' => abs($alpacaPosition['qty']),
                        'side' => $alpacaPosition['side'],
                        'avg_entry_price' => $alpacaPosition['avg_entry_price'],
                        'market_value' => $alpacaPosition['market_value'],
                        'cost_basis' => $alpacaPosition['cost_basis'],
                        'unrealized_pl' => $alpacaPosition['unrealized_pl'],
                        'unrealized_plpc' => $alpacaPosition['unrealized_plpc'],
                        'current_price' => $alpacaPosition['current_price'] ?? $alpacaPosition['lastday_price'],
                        'last_updated' => now(),
                    ]
                );
            }

            // Remove positions that no longer exist in Alpaca
            $currentSymbols = collect($alpacaPositions)->pluck('symbol')->toArray();
            Position::whereNotIn('symbol', $currentSymbols)->delete();
            
        } catch (\Exception $e) {
            Log::error('Failed to update positions', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    public function getPortfolioSummary(): array
    {
        try {
            $account = $this->alpacaService->getAccount();
            $positions = Position::with('asset')->get();
            
            return [
                'account' => [
                    'equity' => $account['equity'],
                    'cash' => $account['cash'],
                    'buying_power' => $account['buying_power'],
                    'portfolio_value' => $account['portfolio_value'],
                    'day_trade_count' => $account['day_trade_count'],
                    'pattern_day_trader' => $account['pattern_day_trader'] ?? false,
                ],
                'positions' => $positions->map(function ($position) {
                    return [
                        'symbol' => $position->symbol,
                        'quantity' => $position->quantity,
                        'side' => $position->side,
                        'market_value' => $position->market_value,
                        'unrealized_pl' => $position->unrealized_pl,
                        'unrealized_plpc' => $position->unrealized_plpc,
                        'current_price' => $position->current_price,
                        'asset' => $position->asset,
                    ];
                })->toArray(),
                'total_positions' => $positions->count(),
                'total_unrealized_pl' => $positions->sum('unrealized_pl'),
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get portfolio summary', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function validateOrderData(array $orderData): void
    {
        $required = ['symbol', 'quantity', 'side'];
        
        foreach ($required as $field) {
            if (!isset($orderData[$field]) || empty($orderData[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        if (!in_array($orderData['side'], ['buy', 'sell'])) {
            throw new \InvalidArgumentException("Invalid side: {$orderData['side']}");
        }

        if (!is_numeric($orderData['quantity']) || $orderData['quantity'] <= 0) {
            throw new \InvalidArgumentException("Invalid quantity: {$orderData['quantity']}");
        }
    }

    private function getOrCreateAsset(string $symbol): Asset
    {
        $asset = Asset::where('symbol', $symbol)->first();
        
        if (!$asset) {
            try {
                $alpacaAsset = $this->alpacaService->getAsset($symbol);
                
                $asset = Asset::create([
                    'symbol' => $alpacaAsset['symbol'],
                    'name' => $alpacaAsset['name'],
                    'asset_class' => $alpacaAsset['class'],
                    'tradable' => $alpacaAsset['tradable'],
                    'marginable' => $alpacaAsset['marginable'],
                    'shortable' => $alpacaAsset['shortable'],
                    'min_order_size' => $alpacaAsset['min_order_size'] ?? null,
                    'min_trade_increment' => $alpacaAsset['price_increment'] ?? null,
                    'attributes' => $alpacaAsset['attributes'] ?? null,
                ]);
                
            } catch (\Exception $e) {
                // Fallback: create basic asset record
                $asset = Asset::create([
                    'symbol' => $symbol,
                    'name' => $symbol,
                    'asset_class' => 'us_equity',
                    'tradable' => true,
                ]);
            }
        }
        
        return $asset;
    }

    private function updatePosition(Trade $trade): void
    {
        // This would typically be handled by syncing with Alpaca positions
        // but we can implement basic position tracking here
        Log::info('Position update triggered for trade', [
            'trade_id' => $trade->id,
            'symbol' => $trade->symbol
        ]);
    }
}