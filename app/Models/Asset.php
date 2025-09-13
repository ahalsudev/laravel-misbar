<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'name',
        'asset_class',
        'tradable',
        'marginable',
        'shortable',
        'min_order_size',
        'min_trade_increment',
        'attributes',
    ];

    protected $casts = [
        'tradable' => 'boolean',
        'marginable' => 'boolean',
        'shortable' => 'boolean',
        'min_order_size' => 'decimal:6',
        'min_trade_increment' => 'decimal:6',
        'attributes' => 'array',
    ];

    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function marketData(): HasMany
    {
        return $this->hasMany(MarketData::class, 'symbol', 'symbol');
    }

    public function getLatestQuote()
    {
        return $this->marketData()
                   ->where('data_type', 'quote')
                   ->orderBy('market_timestamp', 'desc')
                   ->first();
    }

    public function getTotalTradedVolume(): float
    {
        return $this->trades()
                   ->where('status', 'filled')
                   ->sum('filled_quantity');
    }

    public function getTotalTradeValue(): float
    {
        return $this->trades()
                   ->where('status', 'filled')
                   ->get()
                   ->sum(function ($trade) {
                       return $trade->filled_quantity * $trade->filled_avg_price;
                   });
    }

    public function getCurrentPosition()
    {
        return $this->positions()->first();
    }

    public function isActive(): bool
    {
        return $this->tradable;
    }

    public function getAssetClassLabel(): string
    {
        return match ($this->asset_class) {
            'us_equity' => 'US Equity',
            'crypto' => 'Cryptocurrency',
            'forex' => 'Foreign Exchange',
            'commodity' => 'Commodity',
            default => ucfirst(str_replace('_', ' ', $this->asset_class))
        };
    }

    public function scopeTradable($query)
    {
        return $query->where('tradable', true);
    }

    public function scopeByAssetClass($query, string $assetClass)
    {
        return $query->where('asset_class', $assetClass);
    }

    public function scopeEquities($query)
    {
        return $query->where('asset_class', 'us_equity');
    }

    public function scopeCrypto($query)
    {
        return $query->where('asset_class', 'crypto');
    }
}