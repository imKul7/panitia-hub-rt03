<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPeriod extends Model
{
    protected $table = 'event_periods';
    protected $guarded = ['id'];
    protected function casts(): array { return ['event_date'=>'date']; }
}
