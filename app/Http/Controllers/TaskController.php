<?php

namespace App\Http\Controllers;

use App\Models\{Task, TaskUpdate, Division, CommitteeMember};
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $tasks=Task::with(['division','assignee'])->where('event_period_id',$period->id)->orderByRaw("CASE status WHEN 'blocked' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'review' THEN 2 WHEN 'todo' THEN 3 ELSE 4 END")->orderBy('due_date')->paginate(20);
        $divisions=Division::where('event_period_id',$period->id)->get();
        $members=CommitteeMember::with('user')->where('event_period_id',$period->id)->where('is_active',true)->get()->unique('user_id');
        return view('panitia.tasks',compact('period','tasks','divisions','members'));
    }

    public function store(Request $request)
    {
        Access::ensure('task-create',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'title'=>['required','string','max:200'], 'description'=>['nullable','string','max:6000'],
            'division_id'=>['nullable',Rule::exists('divisions','id')->where('event_period_id',$period->id)],
            'assigned_to'=>['nullable',Rule::exists('committee_members','user_id')->where('event_period_id',$period->id)->where('is_active',true)],
            'priority'=>['required',Rule::in(['low','medium','high','urgent'])],
            'due_date'=>['nullable','date'],
        ]);
        $user=auth()->user();
        if (!Access::can($user,'task-update') && !Access::can($user,'task-update',($data['division_id']??null))) {
            abort(403,'Koordinator hanya boleh membuat tugas untuk divisinya.');
        }
        if (!empty($data['assigned_to'])) {
            $assigneeMembership=CommitteeMember::where('event_period_id',$period->id)
                ->where('user_id',$data['assigned_to'])->where('is_active',true);
            // A task with a division cannot be assigned outside that division.
            if (!empty($data['division_id'])) {
                $assigneeMembership->where('division_id',$data['division_id']);
            }
            abort_unless($assigneeMembership->exists(), 422, 'Penanggung jawab tidak memiliki penugasan aktif pada divisi tugas.');
        }
        $task=Task::create([...$data,'event_period_id'=>$period->id,'created_by'=>$user->id,'status'=>'todo']);
        Workspace::log('task.created','Tugas baru: '.$task->title,$task->id,'task');
        return back()->with('success','Tugas berhasil ditambahkan.');
    }

    public function update(Request $request, Task $task)
    {
        Access::ensure('view',write:true);
        $period=Workspace::period();
        abort_unless($task->event_period_id===$period->id,404);
        abort_unless(Access::can(auth()->user(),'task-update',$task->division_id) || ($task->assigned_to===auth()->id() && Access::can(auth()->user(),'task-assignee-update')),403);
        $data=$request->validate([
            'status'=>['required',Rule::in(['todo','in_progress','review','done','blocked'])],
            'note'=>['nullable','string','max:3000'],
        ]);
        $task->update(['status'=>$data['status'],'completed_at'=>$data['status']==='done'?now():null]);
        TaskUpdate::create(['task_id'=>$task->id,'user_id'=>auth()->id(),'status'=>$data['status'],'note'=>$data['note']??null]);
        Workspace::log('task.updated','Status tugas diperbarui: '.$task->title,$task->id,'task');
        return back()->with('success','Progres tugas berhasil disimpan.');
    }
}
