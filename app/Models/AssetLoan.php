<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLoan extends Model
{
    protected $fillable = [
        'asset_id',
        'resident_id',
        'quantity',
        'loan_date',
        'planned_return_date',
        'actual_return_date',
        'loan_status',
        'loan_reason',
        'rejected_reason',
    ];

    protected $casts = [
        'loan_date' => 'date',
        'planned_return_date' => 'date',
        'actual_return_date' => 'date',
    ];

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_BORROWED  = 'borrowed';
    public const STATUS_RETURNED  = 'returned';
    public const STATUS_REJECTED  = 'rejected';

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
