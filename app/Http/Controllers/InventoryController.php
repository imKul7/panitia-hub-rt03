<?php

namespace App\Http\Controllers;

use App\Models\{InventoryItem,InventoryMovement};
use App\Support\{Access,Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $items=InventoryItem::where('event_period_id',$period->id)->latest()->paginate(20);
        $movements=InventoryMovement::whereHas('item',fn($q)=>$q->where('event_period_id',$period->id))->latest()->take(20)->get();
        return view('panitia.inventory',compact('period','items','movements'));
    }
    public function store(Request $request)
    {
        Access::ensure('inventory-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'name'=>['required','string','max:190'],
            'quantity_total'=>['required','integer','min:1','max:1000000'],
            'condition'=>['required',Rule::in(['baik','perlu_pengecekan','rusak'])],
            'location'=>['nullable','string','max:190'],
        ]);
        $item=InventoryItem::create([...$data,'event_period_id'=>$period->id,'created_by'=>auth()->id()]);
        Workspace::log('inventory.created','Inventaris dicatat: '.$item->name,$item->id,'inventory_item');
        return back()->with('success','Inventaris berhasil didaftarkan.');
    }
    public function move(Request $request,InventoryItem $item)
    {
        Access::ensure('inventory-manage',write:true);
        abort_unless($item->event_period_id===Workspace::period()->id,404);
        $data=$request->validate(['movement_type'=>['required',Rule::in(['borrow','return'])], 'quantity'=>['required','integer','min:1'], 'notes'=>['nullable','string','max:190']]);
        DB::transaction(function()use($item,$data) {
            $locked=InventoryItem::lockForUpdate()->findOrFail($item->id);
            if ($data['movement_type']==='borrow' && $locked->quantity_in_use+$data['quantity']>$locked->quantity_total) {
                throw \Illuminate\Validation\ValidationException::withMessages(['quantity'=>'Jumlah barang tersedia tidak mencukupi.']);
            }
            if ($data['movement_type']==='return' && $data['quantity']>$locked->quantity_in_use) {
                throw \Illuminate\Validation\ValidationException::withMessages(['quantity'=>'Jumlah pengembalian melampaui yang dipinjam.']);
            }
            $change=$data['movement_type']==='borrow'?$data['quantity']:-$data['quantity'];
            $locked->increment('quantity_in_use',$change);
            InventoryMovement::create([...$data,'inventory_item_id'=>$locked->id,'user_id'=>auth()->id()]);
            Workspace::log('inventory.moved','Pergerakan inventaris: '.$locked->name,$locked->id,'inventory_item');
        });
        return back()->with('success','Peminjaman/pengembalian tercatat.');
    }
}
