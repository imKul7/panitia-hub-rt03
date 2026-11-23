<?php

namespace App\Http\Controllers;

use App\Models\SopGuide;
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;

class GuideController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $guides=SopGuide::where(fn($q)=>$q->whereNull('event_period_id')->orWhere('event_period_id',$period->id))->orderBy('sort_order')->get();
        return view('panitia.guides',compact('period','guides'));
    }
    public function store(Request $request)
    {
        Access::ensure('guides-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate(['title'=>['required','string','max:200'],'category'=>['required','string','max:80'],'content'=>['required','string','max:16000']]);
        $guide=SopGuide::create([...$data,'event_period_id'=>$period->id]);
        Workspace::log('guide.created','Panduan ditambahkan: '.$guide->title,$guide->id,'sop_guide');
        return back()->with('success','Panduan berhasil disimpan.');
    }
}
