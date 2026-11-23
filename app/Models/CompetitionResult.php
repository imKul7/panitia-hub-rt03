<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionResult extends Model
{
    protected $table = 'competition_results';
    protected $guarded = ['id'];

    public function participant() { return $this->belongsTo(Participant::class); }
    public function competition() { return $this->belongsTo(Competition::class); }
    protected function casts(): array { return ['is_published'=>'boolean']; }
}
