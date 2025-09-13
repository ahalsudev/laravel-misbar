<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'asset_id',
        'symbol',
        'side',
        'type',
        'time_in_force',
        'quantity',
        'price',
        'stop_price',
        'filled_quantity',
        'filled_avg_price',
        'status',
        'submitted_at',
        'filled_at',
        'canceled_at',
        'expired_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'price' => 'decimal:6',
        'stop_price' => 'decimal:6',
        'filled_quantity' => 'decimal:6',
        'filled_avg_price' => 'decimal:6',
        'submitted_at' => 'datetime',
        'filled_at' => 'datetime',
        'canceled_at' => 'datetime',
        'expired_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function getFilledValue(): float
    {
        if (!$this->filled_quantity || !$this->filled_avg_price) {
            return 0;
        }

        return $this->filled_quantity * $this->filled_avg_price;
    }

    public function getUnfilledQuantity(): float
    {
        return $this->quantity - $this->filled_quantity;
    }

    public function getFillPercentage(): float
    {
        if ($this->quantity <= 0) {
            return 0;
        }

        return ($this->filled_quantity / $this->quantity) * 100;
    }

    public function isCompletelyFilled(): bool
    {
        return $this->status === 'filled' && $this->filled_quantity >= $this->quantity;
    }

    public function isPartiallyFilled(): bool
    {
        return $this->status === 'partially_filled' && $this->filled_quantity > 0;
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            'new', 'partially_filled', 'accepted', 'pending_new'
        ]);
    }

    public function canBeCanceled(): bool
    {
        return $this->isActive() && !in_array($this->status, [
            'pending_cancel', 'canceled', 'filled', 'expired'
        ]);
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'new' => 'New',
            'partially_filled' => 'Partially Filled',
            'filled' => 'Filled',
            'done_for_day' => 'Done for Day',
            'canceled' => 'Canceled',
            'expired' => 'Expired',
            'replaced' => 'Replaced',
            'pending_cancel' => 'Pending Cancel',
            'pending_replace' => 'Pending Replace',
            'accepted' => 'Accepted',
            'pending_new' => 'Pending New',
            'accepted_for_bidding' => 'Accepted for Bidding',
            'stopped' => 'Stopped',
            'rejected' => 'Rejected',
            'suspended' => 'Suspended',
            'calculated' => 'Calculated',
            default => ucfirst(str_replace('_', ' ', $this->status))
        };
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'market' => 'Market',
            'limit' => 'Limit',
            'stop' => 'Stop',
            'stop_limit' => 'Stop Limit',
            default => ucfirst(str_replace('_', ' ', $this->type))
        };
    }

    public function getSideLabel(): string
    {
        return match ($this->side) {
            'buy' => 'Buy',
            'sell' => 'Sell',
            default => ucfirst($this->side)
        };
    }

    public function getTimeInForceLabel(): string
    {
        return match ($this->time_in_force) {
            'day' => 'Day',
            'gtc' => 'Good Till Canceled',
            'ioc' => 'Immediate or Cancel',
            'fok' => 'Fill or Kill',
            default => strtoupper($this->time_in_force)
        };
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            'new', 'partially_filled', 'accepted', 'pending_new'
        ]);
    }

    public function scopeFilled($query)
    {
        return $query->where('status', 'filled');
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }

    public function scopeBySymbol($query, string $symbol)
    {
        return $query->where('symbol', strtoupper($symbol));
    }

    public function scopeBySide($query, string $side)
    {
        return $query->where('side', $side);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}