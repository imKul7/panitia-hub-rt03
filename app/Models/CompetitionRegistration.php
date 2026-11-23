<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionRegistration extends Model
{
    protected $table = 'competition_registrations';
    protected $guarded = ['id'];

    public function competition() { return $this->belongsTo(Competition::class); }
    public function participant() { return $this->belongsTo(Participant::class); }
}
