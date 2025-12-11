<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyCard extends Model
{
    /** @use HasFactory<\Database\Factories\FamilyCardFactory> */
    use HasFactory;

    protected $table = 'family_cards';
    protected $guarded = [];
}
