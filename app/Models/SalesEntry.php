<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesEntry extends Model
{
    protected $fillable = ['period_id', 'date', 'amount', 'notes', 'created_by', 'updated_by'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $entry) {
            $entry->created_by = auth()->id();
            $entry->updated_by = auth()->id();
        });

        static::updating(function (self $entry) {
            $entry->updated_by = auth()->id();
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ExpensePeriod::class, 'period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
