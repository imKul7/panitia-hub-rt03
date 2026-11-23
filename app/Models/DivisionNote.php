<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DivisionNote extends Model
{
    protected $table = 'division_notes';
    protected $guarded = ['id'];

    public function division() { return $this->belongsTo(Division::class); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    protected function casts(): array { return ['is_pinned'=>'boolean']; }
}
