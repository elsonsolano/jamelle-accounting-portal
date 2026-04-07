<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositSlipSubmission extends Model
{
    protected $fillable = [
        'fb_sender_id', 'messenger_staff_id', 'branch_id',
        'bank_name', 'account_number', 'amount', 'deposit_date', 'reference_number', 'depositor_name',
        'parse_status', 'confidence_notes', 'is_duplicate', 'admin_status',
        'passbook_id', 'passbook_entry_id', 'image_path',
        'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'deposit_date' => 'date',
        'is_duplicate' => 'boolean',
        'reviewed_at'  => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(MessengerStaff::class, 'messenger_staff_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function passbook(): BelongsTo
    {
        return $this->belongsTo(Passbook::class);
    }

    public function passbookEntry(): BelongsTo
    {
        return $this->belongsTo(PassbookEntry::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReviewed(): bool
    {
        return $this->admin_status !== 'pending';
    }

    public function isRejected(): bool
    {
        return $this->admin_status === 'rejected';
    }

    public function isApproved(): bool
    {
        return $this->admin_status === 'approved';
    }

    public function adminBadgeClass(): string
    {
        return match ($this->admin_status) {
            'approved' => 'bg-green-100 text-green-800',
            'rejected' => 'bg-red-100 text-red-800',
            default    => 'bg-gray-100 text-gray-600',
        };
    }

    public function adminStatusLabel(): string
    {
        return match ($this->admin_status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default    => 'Pending',
        };
    }

    public function parseBadgeClass(): string
    {
        if ($this->is_duplicate) return 'bg-orange-100 text-orange-800';
        return match ($this->parse_status) {
            'success'        => 'bg-blue-100 text-blue-800',
            'low_confidence' => 'bg-yellow-100 text-yellow-800',
            default          => 'bg-red-100 text-red-800',
        };
    }

    public function parseStatusLabel(): string
    {
        if ($this->is_duplicate) return 'Duplicate';
        return match ($this->parse_status) {
            'success'        => 'Parsed',
            'low_confidence' => 'Low Confidence',
            default          => 'Parse Failed',
        };
    }
}
