<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResidentHouse extends Model
{
    use HasFactory;

    protected $table = 'resident_houses';

    protected $guarded = [];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }
}
