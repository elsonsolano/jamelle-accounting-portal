<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymayaImportLine extends Model
{
    protected $fillable = ['import_id', 'bank_account', 'amount', 'credit_date', 'passbook_id', 'passbook_entry_id', 'status'];

    protected $casts = [
        'amount'      => 'decimal:2',
        'credit_date' => 'date',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(PaymayaImport::class, 'import_id');
    }

    public function passbook(): BelongsTo
    {
        return $this->belongsTo(Passbook::class);
    }

    public function passbookEntry(): BelongsTo
    {
        return $this->belongsTo(PassbookEntry::class);
    }
}
