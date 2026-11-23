<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeMember extends Model
{
    protected $table = 'committee_members';
    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }
    public function division() { return $this->belongsTo(Division::class); }
}
