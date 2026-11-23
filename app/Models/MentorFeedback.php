<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MentorFeedback extends Model
{
    protected $table = 'mentor_feedback';
    protected $guarded = ['id'];

    public function author() { return $this->belongsTo(User::class, 'created_by'); }
}
