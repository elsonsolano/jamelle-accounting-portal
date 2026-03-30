<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymayaImport extends Model
{
    protected $fillable = ['gmail_message_id', 'subject', 'credit_date', 'status', 'notes', 'processed_at'];

    protected $casts = [
        'credit_date'  => 'date',
        'processed_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PaymayaImportLine::class, 'import_id');
    }
}
