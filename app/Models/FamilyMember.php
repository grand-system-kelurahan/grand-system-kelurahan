<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    /** @use HasFactory<\Database\Factories\FamilyMemberFactory> */
    use HasFactory;

    protected $table = 'family_members';
    protected $guarded = [];

    public function residen(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
