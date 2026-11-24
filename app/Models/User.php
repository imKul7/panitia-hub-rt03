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

    /**
     * Immutable, human-readable login ID derived from the existing primary
     * key. Existing users need no migration, re-seeding, or credentials reset.
     */
    public function getLoginIdAttribute(): string
    {
        return 'RT03-'.str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
    }

    public function memberships()
    {
        return $this->hasMany(CommitteeMember::class);
    }
}
