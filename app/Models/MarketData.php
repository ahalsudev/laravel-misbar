<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MarketData extends Model
{
    use HasFactory;

    protected $table = 'market_data';

    protected $fillable = [
        'symbol',
        'data_type',
        'price',
        'bid_price',
        'ask_price',
        'bid_size',
        'ask_size',
        'volume',
        'high',
        'low',
        'open',
        'close',
        'vwap',
        'market_timestamp',
        'raw_data',
    ];

    protected $casts = [
        'price' => 'decimal:6',
        'bid_price' => 'decimal:6',
        'ask_price' => 'decimal:6',
        'high' => 'decimal:6',
        'low' => 'decimal:6',
        'open' => 'decimal:6',
        'close' => 'decimal:6',
        'vwap' => 'decimal:6',
        'market_timestamp' => 'datetime',
        'raw_data' => 'array',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'symbol', 'symbol');
    }

    public function getSpread(): float
    {
        if (!$this->bid_price || !$this->ask_price) {
            return 0;
        }

        return $this->ask_price - $this->bid_price;
    }

    public function getSpreadPercentage(): float
    {
        if (!$this->bid_price || !$this->ask_price || $this->bid_price <= 0) {
            return 0;
        }

        return ($this->getSpread() / $this->bid_price) * 100;
    }

    public function getMidPrice(): float
    {
        if (!$this->bid_price || !$this->ask_price) {
            return $this->price ?? 0;
        }

        return ($this->bid_price + $this->ask_price) / 2;
    }

    public function getDayRange(): array
    {
        return [
            'low' => $this->low,
            'high' => $this->high,
            'range' => $this->high && $this->low ? $this->high - $this->low : 0,
            'range_percent' => $this->low && $this->low > 0 ? 
                (($this->high - $this->low) / $this->low) * 100 : 0
        ];
    }

    public function getDayChange(): array
    {
        if (!$this->open || !$this->price) {
            return ['change' => 0, 'change_percent' => 0];
        }

        $change = $this->price - $this->open;
        $changePercent = $this->open > 0 ? ($change / $this->open) * 100 : 0;

        return [
            'change' => $change,
            'change_percent' => $changePercent,
            'is_positive' => $change >= 0
        ];
    }

    public function getVolumeInfo(): array
    {
        return [
            'volume' => $this->volume ?? 0,
            'volume_formatted' => $this->formatVolume($this->volume ?? 0),
            'has_volume' => ($this->volume ?? 0) > 0
        ];
    }

    public function isQuoteData(): bool
    {
        return $this->data_type === 'quote';
    }

    public function isTradeData(): bool
    {
        return $this->data_type === 'trade';
    }

    public function isBarData(): bool
    {
        return $this->data_type === 'bar';
    }

    public function isNewsData(): bool
    {
        return $this->data_type === 'news';
    }

    public function isStale(int $minutes = 5): bool
    {
        return $this->market_timestamp && 
               $this->market_timestamp->diffInMinutes(now()) > $minutes;
    }

    public function getAgeInMinutes(): int
    {
        return $this->market_timestamp ? 
               $this->market_timestamp->diffInMinutes(now()) : 0;
    }

    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->price ?? 0, 2);
    }

    public function getFormattedSpread(): string
    {
        return '$' . number_format($this->getSpread(), 4);
    }

    private function formatVolume(int $volume): string
    {
        if ($volume >= 1000000000) {
            return number_format($volume / 1000000000, 1) . 'B';
        } elseif ($volume >= 1000000) {
            return number_format($volume / 1000000, 1) . 'M';
        } elseif ($volume >= 1000) {
            return number_format($volume / 1000, 1) . 'K';
        }

        return number_format($volume);
    }

    public function scopeQuotes($query)
    {
        return $query->where('data_type', 'quote');
    }

    public function scopeTrades($query)
    {
        return $query->where('data_type', 'trade');
    }

    public function scopeBars($query)
    {
        return $query->where('data_type', 'bar');
    }

    public function scopeBySymbol($query, string $symbol)
    {
        return $query->where('symbol', strtoupper($symbol));
    }

    public function scopeRecent($query, int $minutes = 60)
    {
        return $query->where('market_timestamp', '>=', now()->subMinutes($minutes));
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('market_timestamp', 'desc');
    }

    public function scopeForTimeRange($query, $start, $end)
    {
        return $query->whereBetween('market_timestamp', [$start, $end]);
    }
}