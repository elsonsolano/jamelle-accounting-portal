<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Passbook extends Model
{
    protected $fillable = ['branch_id', 'bank_name', 'account_number', 'account_name', 'opening_balance', 'opening_date'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_date'    => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PassbookEntry::class)->orderBy('date')->orderBy('id');
    }

    public function currentBalance(): string
    {
        $credits = $this->entries()->whereIn('type', ['deposit', 'transfer_in', 'interest'])->sum('amount');
        $debits  = $this->entries()->whereIn('type', ['withdrawal', 'transfer_out', 'bank_charge'])->sum('amount');

        return number_format((float) $this->opening_balance + $credits - $debits, 2);
    }

    public function label(): string
    {
        return $this->bank_name . ($this->account_number ? ' — ' . $this->account_number : '');
    }
}
