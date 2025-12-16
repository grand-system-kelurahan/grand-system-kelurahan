<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterType extends Model
{
    protected $fillable = [
        'letter_code',
        'letter_name',
        'description',
    ];

    public function applications()
    {
        return $this->hasMany(LetterApplication::class);
    }
}
