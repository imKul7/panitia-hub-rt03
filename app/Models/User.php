<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name','email','password','phone','is_active','is_super_admin','avatar_path'];
    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at'=>'datetime','password'=>'hashed','is_active'=>'boolean','is_super_admin'=>'boolean'];
    }

    public function memberships()
    {
        return $this->hasMany(CommitteeMember::class);
    }
}
