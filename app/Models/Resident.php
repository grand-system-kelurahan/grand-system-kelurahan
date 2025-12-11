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


    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
    }


    public function getAgeAttribute(): int
    {
        return now()->diffInYears($this->date_of_birth);
    }
}
