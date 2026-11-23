<?php

namespace App\Http\Controllers;

use App\Models\{Division, DivisionNote};
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $notes=DivisionNote::with(['division','author'])->where('event_period_id',$period->id)->orderByDesc('is_pinned')->latest()->paginate(20);
        $divisions=Division::where('event_period_id',$period->id)->get();
        return view('panitia.notes',compact('period','notes','divisions'));
    }

    public function store(Request $request)
    {
        $period=Workspace::period();
        $data=$request->validate([
            'division_id'=>['required',Rule::exists('divisions','id')->where('event_period_id',$period->id)],
            'title'=>['required','string','max:190'],
            'content'=>['required','string','max:12000'],
            'note_type'=>['required',Rule::in(['info','decision','issue','follow_up'])],
        ]);
        Access::ensure('notes-create',(int)$data['division_id'],true);
        $note=DivisionNote::create([...$data,'event_period_id'=>$period->id,'created_by'=>auth()->id()]);
        Workspace::log('note.created','Catatan divisi: '.$note->title,$note->id,'division_note');
        return back()->with('success','Catatan berhasil disimpan.');
    }
}
