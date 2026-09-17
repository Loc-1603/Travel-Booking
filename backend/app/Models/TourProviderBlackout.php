<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourProviderBlackout extends Model
{
    protected $fillable = ['provider_id', 'start_date', 'end_date', 'reason'];

    public function provider()
    {
        return $this->belongsTo(TourProvider::class);
    }
}
