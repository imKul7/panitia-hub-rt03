<?php

namespace App\Http\Controllers;

use App\Support\{Access, Workspace};
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $memberships=auth()->user()->memberships()->with('division')->where('event_period_id',$period->id)->get();
        return view('panitia.profile',compact('period','memberships'));
    }

    public function update(Request $request)
    {
        Access::ensure('view');
        $data=$request->validate([
            'name'=>['required','string','max:120'],
            'phone'=>['nullable','string','max:30'],
        ]);
        auth()->user()->update($data);
        Workspace::log('profile.updated','Profil pengguna diperbarui');
        return back()->with('success','Profil berhasil disimpan.');
    }

    public function changePassword(Request $request)
    {
        Access::ensure('view');
        $data=$request->validate([
            'current_password'=>['required','current_password'],
            'password'=>['required','string','min:12','confirmed'],
        ]);
        auth()->user()->update(['password'=>$data['password']]);
        Workspace::log('password.changed','Pengguna mengganti kata sandi');
        $request->session()->regenerate();
        return back()->with('success','Kata sandi berhasil diganti.');
    }
}
