<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointOfInterest extends Model
{
    use HasFactory;

    protected $table = 'points_of_interest';

    protected $guarded = [];

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
