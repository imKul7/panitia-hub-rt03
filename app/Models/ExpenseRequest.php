<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseRequest extends Model
{
    protected $table = 'expense_requests';
    protected $guarded = ['id'];
    protected function casts(): array { return ['reviewed_at'=>'datetime']; }
}
