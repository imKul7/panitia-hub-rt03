<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PrizeDistribution extends Model {
    protected $table = 'prize_distributions';
    public function prize() { return $this->belongsTo(Prize::class); }
    protected $guarded = ['id'];
}
