<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Resident extends Model
{
    /** @use HasFactory<\Database\Factories\ResidenFactory> */
    use HasFactory;

    protected $table   = 'residents';
    protected $guarded = [];


    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get the houses for the resident.
     */
    public function houses()
    {
        return $this->hasMany(ResidentHouse::class);
    }

    /**
     * Get the age of resident.
     */
    public function familyMember(): HasOne
    {
        return $this->hasOne(FamilyMember::class, 'resident_id');
    }


    public function getAgeAttribute(): int
    {
        return now()->diffInYears($this->date_of_birth);
    }

    public function letterApplications()
    {
        return $this->hasMany(LetterApplication::class);
    }
}
