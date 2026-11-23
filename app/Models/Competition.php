<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competition extends Model
{
    protected $table = 'competitions';
    protected $guarded = ['id'];

    public function registrations() { return $this->hasMany(CompetitionRegistration::class); }
    public function results() { return $this->hasMany(CompetitionResult::class); }
    protected function casts(): array { return ['starts_at'=>'datetime','is_published'=>'boolean']; }
}
