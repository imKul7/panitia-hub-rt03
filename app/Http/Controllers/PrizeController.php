<?php

namespace App\Http\Controllers;

use App\Models\{Prize,PrizeDistribution,Competition};
use App\Support\{Access,Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PrizeController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $prizes=Prize::where('event_period_id',$period->id)->latest()->paginate(20);
        $competitions=Competition::where('event_period_id',$period->id)->get();
        $distributions=PrizeDistribution::whereHas('prize',fn($q)=>$q->where('event_period_id',$period->id))->latest()->take(20)->get();
        return view('panitia.prizes',compact('period','prizes','competitions','distributions'));
    }

    public function store(Request $request)
    {
        Access::ensure('prize-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'title'=>['required','string','max:190'],
            'quantity'=>['required','integer','min:1','max:100000'],
            'estimated_unit_cost'=>['nullable','numeric','min:0','max:999999999'],
            'competition_id'=>['nullable',Rule::exists('competitions','id')->where('event_period_id',$period->id)],
            'notes'=>['nullable','string','max:3000'],
        ]);
        $item=Prize::create([...$data,'event_period_id'=>$period->id,'created_by'=>auth()->id()]);
        Workspace::log('prize.created','Hadiah disiapkan: '.$item->title,$item->id,'prize');
        return back()->with('success','Daftar hadiah disimpan.');
    }

    public function distribute(Request $request, Prize $prize)
    {
        Access::ensure('prize-manage',write:true);
        $period=Workspace::period();
        abort_unless($prize->event_period_id===$period->id,404);
        $data=$request->validate([
            'recipient_name'=>['required','string','max:150'],
            'quantity'=>['required','integer','min:1','max:100000'],
        ]);
        DB::transaction(function()use($prize,$data) {
            $locked=Prize::lockForUpdate()->findOrFail($prize->id);
            if ($locked->distributed_quantity + $data['quantity'] > $locked->quantity) {
                throw \Illuminate\Validation\ValidationException::withMessages(['quantity'=>'Jumlah hadiah tidak mencukupi.']);
            }
            PrizeDistribution::create(['prize_id'=>$locked->id,'recipient_name'=>$data['recipient_name'],'quantity'=>$data['quantity'],'distributed_by'=>auth()->id(),'distributed_at'=>now()]);
            $locked->increment('distributed_quantity',$data['quantity']);
        });
        Workspace::log('prize.distributed','Penyerahan hadiah: '.$prize->title,$prize->id,'prize');
        return back()->with('success','Penyerahan hadiah tercatat.');
    }
}
