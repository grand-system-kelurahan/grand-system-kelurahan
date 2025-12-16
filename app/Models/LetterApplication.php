<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterApplication extends Model
{

    // protected $table = 'letter_applications';

    protected $fillable = [
        'resident_id',
        'letter_type_id',
        'letter_number',
        'submission_date',
        'approval_date',
        'status',
        'description',
        'approved_by_employee_id',
        'approved_by_employee_name'
    ];

    public function letterType()
    {
        return $this->belongsTo(LetterType::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }
}
