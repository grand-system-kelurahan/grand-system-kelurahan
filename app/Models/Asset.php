<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'asset_code',
        'asset_name',
        'description',
        'asset_type',
        'total_stock',
        'available_stock',
        'location',
        'asset_status',
    ];

    protected $appends = ['borrowed_stock'];

    public const TYPE_ITEM = 'item';
    public const TYPE_ROOM = 'room';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public function loans(): HasMany
    {
        return $this->hasMany(AssetLoan::class);
    }


    public function getBorrowedStockAttribute()
    {
        return $this->total_stock - $this->available_stock;
    }
}
