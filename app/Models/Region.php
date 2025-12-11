<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    /** @use HasFactory<\Database\Factories\RegionFactory> */
    use HasFactory;

    protected $table = 'regions';
    protected $guarded = [];

    /**
     * Get the residents for the region.
     */
    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }
}
