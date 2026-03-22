<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['name', 'is_cost_center'];

    protected $casts = ['is_cost_center' => 'boolean'];

    public function expensePeriods(): HasMany
    {
        return $this->hasMany(ExpensePeriod::class);
    }

    public function grossSales(): HasMany
    {
        return $this->hasMany(GrossSales::class);
    }
}
