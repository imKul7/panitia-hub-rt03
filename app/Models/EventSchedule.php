<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventSchedule extends Model
{
    protected $table = 'event_schedules';
    protected $guarded = ['id'];
    protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime','is_public'=>'boolean']; }
}
