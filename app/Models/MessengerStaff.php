<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessengerStaff extends Model
{
    protected $table = 'messenger_staff';

    protected $fillable = [
        'fb_sender_id', 'fb_name', 'employee_code', 'branch_id', 'state', 'registered_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(DepositSlipSubmission::class);
    }

    public function isActive(): bool
    {
        return $this->state === 'active';
    }
}
