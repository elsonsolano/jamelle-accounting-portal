<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpensePeriod extends Model
{
    protected $fillable = ['branch_id', 'month', 'year', 'vat_itr_estimate'];

    protected $casts = [
        'vat_itr_estimate' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function expenseEntries(): HasMany
    {
        return $this->hasMany(ExpenseEntry::class, 'period_id')->orderBy('sort_order')->orderByDesc('date');
    }

    public function grossSales(): HasMany
    {
        return $this->hasMany(GrossSales::class, 'period_id');
    }

    public function getMonthNameAttribute(): string
    {
        return \Carbon\Carbon::create($this->year, $this->month)->format('F Y');
    }
}
