<?php

namespace App\Http\Controllers;

use App\Models\{EventPeriod,Division,Task,Competition,Document};
use App\Support\{Access,Workspace};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeriodController extends Controller
{
    public function index()
    {
        Access::ensure('manage-periods');
        $periods=EventPeriod::orderByDesc('year')->get();
        return view('panitia.periods',compact('periods'));
    }

    public function store(Request $request)
    {
        Access::ensure('manage-periods',write:true);
        $data=$request->validate([
            'year'=>['required','integer','between:2027,2100','unique:event_periods,year'],
            'name'=>['required','string','max:200'],
            'tagline'=>['nullable','string','max:200'],
            'event_date'=>['nullable','date'],
        ]);
        $source=Workspace::period();
        $new=DB::transaction(function()use($data,$source) {
            $created=EventPeriod::create([...$data,'status'=>'draft']);
            foreach(Division::where('event_period_id',$source->id)->get() as $division) {
                Division::create(['event_period_id'=>$created->id,'name'=>$division->name,'slug'=>$division->slug,'description'=>$division->description]);
            }
            return $created;
        });
        Workspace::log('period.created','Periode kepanitiaan baru: '.$new->year,$new->id,'event_period');
        return back()->with('success','Periode baru dibuat sebagai draf. Jabatan harus diatur ulang sesuai kepanitiaan baru.');
    }

    public function activate(EventPeriod $period)
    {
        Access::ensure('manage-periods', write:true);
        // Archived periods are historical records, not reusable drafts.
        abort_unless($period->status==='draft', 422, 'Hanya periode draf yang dapat diaktifkan.');
        DB::transaction(function()use($period) {
            $target = EventPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            abort_unless($target->status==='draft', 422, 'Hanya periode draf yang dapat diaktifkan.');
            EventPeriod::where('status','active')->update(['status'=>'archived']);
            $target->update(['status'=>'active']);
        });
        Workspace::log('period.activated','Periode diaktifkan: '.$period->year,$period->id,'event_period');
        return back()->with('success','Periode '.$period->year.' sekarang aktif. Periode sebelumnya tetap tersimpan sebagai arsip.');
    }

    public function archive()
    {
        Access::ensure('view');
        $periods=EventPeriod::orderByDesc('year')->get();
        $summaries=$periods->map(function($p) {
            return [
                'period'=>$p,
                'task_count'=>Task::where('event_period_id',$p->id)->count(),
                'finished'=>Task::where('event_period_id',$p->id)->where('status','done')->count(),
                'competition_count'=>Competition::where('event_period_id',$p->id)->count(),
                'document_count'=>Document::where('event_period_id',$p->id)->count(),
            ];
        });
        return view('panitia.archive',compact('summaries'));
    }
}
