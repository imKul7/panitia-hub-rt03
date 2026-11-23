<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $announcements=Announcement::where('event_period_id',$period->id)->latest()->paginate(20);
        return view('panitia.announcements',compact('period','announcements'));
    }
    public function store(Request $request)
    {
        Access::ensure('announcement-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'title'=>['required','string','max:200'],
            'content'=>['required','string','max:12000'],
            'category'=>['required',Rule::in(['umum','penting','jadwal','hasil'])],
            'status'=>['required',Rule::in(['draft','published'])],
        ]);
        $item=Announcement::create([...$data,'event_period_id'=>$period->id,'created_by'=>auth()->id(),'published_at'=>$data['status']==='published'?now():null]);
        Workspace::log('announcement.created','Pengumuman disimpan: '.$item->title,$item->id,'announcement');
        return back()->with('success','Pengumuman berhasil disimpan.');
    }
}
