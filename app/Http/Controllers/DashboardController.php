<?php

namespace App\Http\Controllers;

use App\Models\{Task, Division, Competition, CommitteeMember, Announcement, ActivityLog, EventSchedule};
use App\Support\{Access, Workspace};

class DashboardController extends Controller
{
    public function __invoke()
    {
        Access::ensure('view');
        $period = Workspace::period();
        $taskBase = Task::where('event_period_id',$period->id);
        $stats = [
            'tasks'=>$taskBase->count(),
            'done'=>(clone $taskBase)->where('status','done')->count(),
            'blocked'=>(clone $taskBase)->where('status','blocked')->count(),
            'late'=>(clone $taskBase)->where('status','!=','done')->whereDate('due_date','<',today())->count(),
            'competitions'=>Competition::where('event_period_id',$period->id)->count(),
            'members'=>CommitteeMember::where('event_period_id',$period->id)->where('is_active',true)->distinct('user_id')->count('user_id'),
        ];
        $myTasks = (clone $taskBase)->where('assigned_to',auth()->id())->orderBy('due_date')->take(6)->get();
        $recent = ActivityLog::where('event_period_id',$period->id)->latest()->take(8)->get();
        $divisions = Division::where('event_period_id',$period->id)->get();
        $upcoming = EventSchedule::where('event_period_id',$period->id)->where('starts_at','>=',now())->orderBy('starts_at')->take(4)->get();
        return view('panitia.dashboard',compact('period','stats','myTasks','recent','divisions','upcoming'));
    }
}
