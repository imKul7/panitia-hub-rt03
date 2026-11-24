<?php

namespace App\Http\Controllers;

use App\Models\{CommitteeMember, Division, User};
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class PeopleController extends Controller
{
    private const ROLES = ['ketua','wakil','sekretaris','bendahara','koordinator','anggota','mentor','pengurus_rt'];

    public function index()
    {
        Access::ensure('view');
        $period = Workspace::period();
        $members = CommitteeMember::with(['user','division'])->where('event_period_id',$period->id)->where('is_active',true)->orderBy('role')->get();
        $divisions = Division::where('event_period_id',$period->id)->orderBy('name')->get();
        $users = User::where('is_active',true)->orderBy('name')->get(['id','name','email','is_active']);
        // Full account directory is restricted to Super Admin; no password
        // hashes or remember tokens are selected or serialized to the view.
        $accounts = auth()->user()->is_super_admin
            ? User::query()->select(['id','name','email','is_active','is_super_admin'])
                ->orderBy('id')->paginate(20, ['*'], 'accounts_page')
            : null;
        return view('panitia.people',compact('period','members','divisions','users','accounts'));
    }

    public function store(Request $request)
    {
        Access::ensure('manage-members',write:true);
        $period = Workspace::period();
        $data = $request->validate([
            'name'=>['required','string','max:120'],
            'email'=>['required','email','max:190','unique:users,email'],
            'password'=>['required','string','min:8'],
            'role'=>['required',Rule::in(self::ROLES)],
            'division_id'=>['nullable','integer',Rule::exists('divisions','id')->where('event_period_id',$period->id)],
        ]);
        if (in_array($data['role'],['koordinator','anggota'],true)) {
            $request->validate(['division_id'=>['required']]);
        }
        if (!in_array($data['role'],['koordinator','anggota'],true)) $data['division_id']=null;
        $user=User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>$data['password'],'is_active'=>true]);
        CommitteeMember::create(['user_id'=>$user->id,'event_period_id'=>$period->id,'division_id'=>$data['division_id'],'role'=>$data['role'],'position_title'=>ucwords(str_replace('_',' ',$data['role']))]);
        Workspace::log('member.created','Akun panitia dibuat: '.$user->name,$user->id,'user');
        return back()->with('success','Akun panitia dibuat. ID login: '.$user->login_id.'. Berikan ID dan kata sandi awal secara pribadi.');
    }

    public function assign(Request $request)
    {
        Access::ensure('manage-members',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'user_id'=>['required',Rule::exists('users','id')->where('is_active',true)],
            'role'=>['required',Rule::in(self::ROLES)],
            'division_id'=>['nullable',Rule::exists('divisions','id')->where('event_period_id',$period->id)],
        ]);
        if (in_array($data['role'],['koordinator','anggota'],true)) $request->validate(['division_id'=>['required']]);
        else $data['division_id']=null;
        // Restoring a previously disabled membership must actually reactivate it.
        CommitteeMember::updateOrCreate([
            'user_id'=>$data['user_id'],'event_period_id'=>$period->id,
            'role'=>$data['role'],'division_id'=>$data['division_id'],
        ],['is_active'=>true,'position_title'=>ucwords(str_replace('_',' ',$data['role']))]);
        Workspace::log('member.assigned','Penugasan role ditambahkan',$data['user_id'],'user');
        return back()->with('success','Jabatan berhasil ditambahkan ke akun.');
    }
}
