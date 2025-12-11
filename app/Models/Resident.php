<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resident extends Model
{
    /** @use HasFactory<\Database\Factories\ResidenFactory> */
    use HasFactory;

    protected $table = 'residents';
    protected $guarded = [];

    /**
     * Get the region that owns the resident.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get the age of resident.
     */
    public function getAgeAttribute(): int
    {
        return now()->diffInYears($this->date_of_birth);
    }
}
