<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    protected $table = 'financial_transactions';
    protected $guarded = ['id'];

    public function division() { return $this->belongsTo(Division::class); }
    protected function casts(): array { return ['transaction_date'=>'date']; }
}
