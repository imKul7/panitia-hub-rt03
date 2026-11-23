<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';
    protected $guarded = ['id'];

    public function division() { return $this->belongsTo(Division::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function updates() { return $this->hasMany(TaskUpdate::class)->latest(); }
    protected function casts(): array { return ['due_date'=>'date','completed_at'=>'datetime']; }
}
