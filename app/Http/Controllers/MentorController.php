<?php

namespace App\Http\Controllers;

use App\Models\{MentorFeedback, Division, Task};
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MentorController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $feedback=MentorFeedback::with('author')->where('event_period_id',$period->id)->latest()->paginate(20);
        $divisions=Division::where('event_period_id',$period->id)->get();
        $issues=Task::with('division')->where('event_period_id',$period->id)->whereIn('status',['blocked','review'])->latest()->get();
        return view('panitia.mentor',compact('period','feedback','divisions','issues'));
    }
    public function store(Request $request)
    {
        Access::ensure('mentor-feedback',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'division_id'=>['nullable',Rule::exists('divisions','id')->where('event_period_id',$period->id)],
            'task_id'=>['nullable',Rule::exists('tasks','id')->where('event_period_id',$period->id)],
            'feedback_text'=>['required','string','max:5000'],
        ]);
        if (!empty($data['task_id'])) {
            $task = Task::where('event_period_id', $period->id)
                ->findOrFail($data['task_id']);
            if ($task->division_id !== null && !empty($data['division_id'])
                && (int) $data['division_id'] !== (int) $task->division_id) {
                throw ValidationException::withMessages([
                    'division_id' => 'Divisi yang dipilih tidak sesuai dengan divisi tugas terkait.',
                ]);
            }
            // Selecting a division task automatically tags feedback to its
            // division when the mentor left the optional field empty.
            if (empty($data['division_id']) && $task->division_id !== null) {
                $data['division_id'] = $task->division_id;
            }
        }
        $item=MentorFeedback::create([...$data,'event_period_id'=>$period->id,'created_by'=>auth()->id()]);
        Workspace::log('mentor.feedback','Mentor memberikan masukan #'.$item->id,$item->id,'mentor_feedback');
        return back()->with('success','Masukan mentor berhasil dikirim.');
    }
}
