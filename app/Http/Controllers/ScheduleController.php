<?php

namespace App\Http\Controllers;

use App\Models\EventSchedule;
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $schedules=EventSchedule::where('event_period_id',$period->id)->orderBy('starts_at')->paginate(25);
        return view('panitia.schedules',compact('period','schedules'));
    }
    public function store(Request $request)
    {
        Access::ensure('schedule-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'title'=>['required','string','max:200'],
            'category'=>['required',Rule::in(['lomba','malam_puncak','umum'])],
            'starts_at'=>['required','date'],
            'ends_at'=>['nullable','date','after:starts_at'],
            'location'=>['nullable','string','max:190'],
            'description'=>['nullable','string','max:5000'],
        ]);
        $item=EventSchedule::create([...$data,'event_period_id'=>$period->id,'created_by'=>auth()->id(),'is_public'=>$request->boolean('is_public')]);
        Workspace::log('schedule.created','Agenda dibuat: '.$item->title,$item->id,'event_schedule');
        return back()->with('success','Jadwal berhasil ditambahkan.');
    }
}
