<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InventoryMovement extends Model {
    protected $table = 'inventory_movements';
    public function item() { return $this->belongsTo(InventoryItem::class,'inventory_item_id'); }
    protected $guarded = ['id'];
}
