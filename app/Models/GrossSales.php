<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrossSales extends Model
{
    protected $fillable = ['period_id', 'branch_id', 'amount', 'vat_itr'];

    protected $casts = [
        'amount'  => 'decimal:2',
        'vat_itr' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(ExpensePeriod::class, 'period_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
