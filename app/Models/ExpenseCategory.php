<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'notes'];

    public function expenseEntries(): HasMany
    {
        return $this->hasMany(ExpenseEntry::class, 'category_id');
    }
}
