<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'symbol',
        'quantity',
        'side',
        'avg_entry_price',
        'market_value',
        'cost_basis',
        'unrealized_pl',
        'unrealized_plpc',
        'current_price',
        'last_updated',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'avg_entry_price' => 'decimal:6',
        'market_value' => 'decimal:2',
        'cost_basis' => 'decimal:2',
        'unrealized_pl' => 'decimal:2',
        'unrealized_plpc' => 'decimal:4',
        'current_price' => 'decimal:6',
        'last_updated' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function getAbsoluteQuantity(): float
    {
        return abs($this->quantity);
    }

    public function getMarketValueFormatted(): string
    {
        return '$' . number_format($this->market_value, 2);
    }

    public function getUnrealizedPlFormatted(): string
    {
        $prefix = $this->unrealized_pl >= 0 ? '+$' : '-$';
        return $prefix . number_format(abs($this->unrealized_pl), 2);
    }

    public function getUnrealizedPlpcFormatted(): string
    {
        $prefix = $this->unrealized_plpc >= 0 ? '+' : '';
        return $prefix . number_format($this->unrealized_plpc, 2) . '%';
    }

    public function getCurrentPriceFormatted(): string
    {
        return '$' . number_format($this->current_price, 2);
    }

    public function getAvgEntryPriceFormatted(): string
    {
        return '$' . number_format($this->avg_entry_price, 2);
    }

    public function getCostBasisFormatted(): string
    {
        return '$' . number_format($this->cost_basis, 2);
    }

    public function isLongPosition(): bool
    {
        return $this->side === 'long' && $this->quantity > 0;
    }

    public function isShortPosition(): bool
    {
        return $this->side === 'short' && $this->quantity > 0;
    }

    public function isProfitable(): bool
    {
        return $this->unrealized_pl > 0;
    }

    public function isLoss(): bool
    {
        return $this->unrealized_pl < 0;
    }

    public function getPositionValue(): float
    {
        return $this->quantity * $this->current_price;
    }

    public function getGainLoss(): float
    {
        return $this->market_value - $this->cost_basis;
    }

    public function getGainLossPercentage(): float
    {
        if ($this->cost_basis <= 0) {
            return 0;
        }

        return (($this->market_value - $this->cost_basis) / $this->cost_basis) * 100;
    }

    public function getWeight(float $totalPortfolioValue): float
    {
        if ($totalPortfolioValue <= 0) {
            return 0;
        }

        return (abs($this->market_value) / $totalPortfolioValue) * 100;
    }

    public function getDaysSinceEntry(): int
    {
        return $this->created_at->diffInDays(now());
    }

    public function isStale(): bool
    {
        return $this->last_updated && $this->last_updated->diffInMinutes(now()) > 15;
    }

    public function getStatusIndicator(): string
    {
        if ($this->isStale()) {
            return 'stale';
        }

        if ($this->unrealized_pl > 0) {
            return 'profit';
        } elseif ($this->unrealized_pl < 0) {
            return 'loss';
        }

        return 'neutral';
    }

    public function getSideLabel(): string
    {
        return match ($this->side) {
            'long' => 'Long',
            'short' => 'Short',
            default => ucfirst($this->side)
        };
    }

    public function getDiversificationRisk(): string
    {
        // This would typically use portfolio context, but for simplicity:
        $weight = abs($this->market_value);
        
        if ($weight > 50000) { // $50k+ position
            return 'high';
        } elseif ($weight > 20000) { // $20k+ position
            return 'medium';
        }
        
        return 'low';
    }

    public function scopeLong($query)
    {
        return $query->where('side', 'long')->where('quantity', '>', 0);
    }

    public function scopeShort($query)
    {
        return $query->where('side', 'short')->where('quantity', '>', 0);
    }

    public function scopeProfitable($query)
    {
        return $query->where('unrealized_pl', '>', 0);
    }

    public function scopeLosing($query)
    {
        return $query->where('unrealized_pl', '<', 0);
    }

    public function scopeBySymbol($query, string $symbol)
    {
        return $query->where('symbol', strtoupper($symbol));
    }

    public function scopeStale($query, int $minutes = 15)
    {
        return $query->where('last_updated', '<', now()->subMinutes($minutes));
    }

    public function scopeOrderByValue($query, string $direction = 'desc')
    {
        return $query->orderBy('market_value', $direction);
    }

    public function scopeOrderByPerformance($query, string $direction = 'desc')
    {
        return $query->orderBy('unrealized_plpc', $direction);
    }
}