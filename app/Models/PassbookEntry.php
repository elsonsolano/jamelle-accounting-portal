<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassbookEntry extends Model
{
    protected $fillable = [
        'passbook_id', 'date', 'particulars', 'type', 'amount',
        'linked_entry_id', 'expense_entry_id', 'created_by', 'updated_by',
    ];

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

    public function passbook(): BelongsTo
    {
        return $this->belongsTo(Passbook::class);
    }

    public function linkedEntry(): BelongsTo
    {
        return $this->belongsTo(PassbookEntry::class, 'linked_entry_id');
    }

    public function expenseEntry(): BelongsTo
    {
        return $this->belongsTo(ExpenseEntry::class, 'expense_entry_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isCredit(): bool
    {
        return in_array($this->type, ['deposit', 'transfer_in', 'interest']);
    }

    public function isDebit(): bool
    {
        return in_array($this->type, ['withdrawal', 'transfer_out', 'bank_charge']);
    }
}
