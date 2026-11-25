<?php

namespace App\Http\Controllers;

use App\Models\{Competition, Participant, CompetitionRegistration, CompetitionResult};
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompetitionController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $competitions=Competition::withCount('registrations')->where('event_period_id',$period->id)->latest()->paginate(12);
        return view('panitia.competitions',compact('period','competitions'));
    }

    public function store(Request $request)
    {
        Access::ensure('competition-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'name'=>['required','string','max:170'], 'category'=>['required','string','max:80'],
            'rules'=>['nullable','string','max:8000'], 'location'=>['nullable','string','max:190'],
            'starts_at'=>['nullable','date'], 'quota'=>['nullable','integer','min:1','max:100000'],
            'is_published'=>['sometimes','boolean'],
        ]);
        $competition=Competition::create([...$data,'event_period_id'=>$period->id,'is_published'=>$request->boolean('is_published')]);
        Workspace::log('competition.created','Lomba dibuat: '.$competition->name,$competition->id,'competition');
        return back()->with('success','Lomba berhasil ditambahkan.');
    }

    public function registrations()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $competitions=Competition::where('event_period_id',$period->id)->orderBy('name')->get();
        $participants=Participant::where('event_period_id',$period->id)->orderBy('full_name')->get();
        $registrations=CompetitionRegistration::with(['participant','competition'])->whereHas('competition',fn($q)=>$q->where('event_period_id',$period->id))->latest()->paginate(20);
        return view('panitia.registrations',compact('period','competitions','participants','registrations'));
    }

    public function register(Request $request)
    {
        Access::ensure('registration-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'competition_id'=>['required',Rule::exists('competitions','id')->where('event_period_id',$period->id)],
            'participant_id'=>['nullable',Rule::exists('participants','id')->where('event_period_id',$period->id)],
            'full_name'=>['required_without:participant_id','nullable','string','max:150'],
            'phone'=>['nullable','string','max:30'],
            'guardian_name'=>['nullable','string','max:150'],
            'guardian_phone'=>['nullable','string','max:30'],
            'age_group'=>['nullable','string','max:80'],
        ]);
        $registration=DB::transaction(function() use ($data,$period) {
            $competition=Competition::where('event_period_id',$period->id)->lockForUpdate()->findOrFail($data['competition_id']);
            $registered=CompetitionRegistration::where('competition_id',$competition->id)->where('status','!=','cancelled')->count();
            if ($competition->quota !== null && $registered >= $competition->quota) {
                throw \Illuminate\Validation\ValidationException::withMessages(['competition_id'=>'Kuota lomba sudah penuh.']);
            }
            $participant=isset($data['participant_id']) ? Participant::where('event_period_id',$period->id)->findOrFail($data['participant_id']) : Participant::create([
                'event_period_id'=>$period->id,'created_by'=>auth()->id(),'full_name'=>$data['full_name'],
                'phone'=>$data['phone']??null,'guardian_name'=>$data['guardian_name']??null,
                'guardian_phone'=>$data['guardian_phone']??null,'age_group'=>$data['age_group']??null,
            ]);
            $existing=CompetitionRegistration::where('competition_id',$competition->id)->where('participant_id',$participant->id)->first();
            if ($existing) {
                throw \Illuminate\Validation\ValidationException::withMessages(['participant_id'=>'Peserta sudah terdaftar pada lomba ini.']);
            }
            $registration = CompetitionRegistration::create([
                'competition_id'=>$competition->id,'participant_id'=>$participant->id,'registered_by'=>auth()->id(),
                'registration_code'=>'RT03-'.strtoupper((string)Str::ulid()),'status'=>'confirmed',
            ]);
            Workspace::log('registration.created','Pendaftaran peserta #'.$registration->id,$registration->id,'competition_registration');
            return $registration;
        });
        return back()->with('success','Peserta berhasil didaftarkan oleh panitia.');
    }

    public function results()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $competitions=Competition::where('event_period_id',$period->id)->orderBy('name')->get();
        $registrations=CompetitionRegistration::with('participant')->where('status','confirmed')
            ->whereHas('competition',fn($q)=>$q->where('event_period_id',$period->id))->orderBy('competition_id')->get();
        $results=CompetitionResult::with(['participant','competition'])->whereHas('competition',fn($q)=>$q->where('event_period_id',$period->id))->orderBy('competition_id')->orderBy('rank_position')->get();
        return view('panitia.results',compact('period','competitions','registrations','results'));
    }

    public function storeResult(Request $request)
    {
        Access::ensure('competition-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'competition_id'=>['required',Rule::exists('competitions','id')->where('event_period_id',$period->id)],
            'participant_id'=>['required','integer'],
            'rank_position'=>['required','integer','between:1,3'],
            'public_display_name'=>['nullable','string','max:150'],
            'is_published'=>['sometimes','boolean'],
            'public_consent_confirmed'=>['sometimes','accepted'],
        ]);
        $registration=CompetitionRegistration::where('competition_id',$data['competition_id'])->where('participant_id',$data['participant_id'])->where('status','confirmed')->first();
        if (!$registration) throw \Illuminate\Validation\ValidationException::withMessages(['participant_id'=>'Peserta harus sudah terdaftar dan terkonfirmasi untuk lomba tersebut.']);
        $publish=$request->boolean('is_published');
        if ($publish && (!$request->boolean('public_consent_confirmed') || empty($data['public_display_name']) || !Competition::whereKey($data['competition_id'])->where('is_published',true)->exists())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['public_display_name'=>'Untuk publikasi, isi nama tampilan yang disetujui peserta/wali dan pastikan lomba sudah dipublikasikan.']);
        }
        $exists=CompetitionResult::where('competition_id',$data['competition_id'])->where(fn($q)=>$q->where('participant_id',$data['participant_id'])->orWhere('rank_position',$data['rank_position']))->exists();
        if ($exists) throw \Illuminate\Validation\ValidationException::withMessages(['rank_position'=>'Juara atau peserta ini sudah memiliki hasil; edit hasil melalui prosedur koreksi.']);
        unset($data['public_consent_confirmed']);
        $result=CompetitionResult::create([...$data,'created_by'=>auth()->id(),'is_published'=>$publish,
            'publication_consent_at'=>$publish?now():null,
            'publication_confirmed_by'=>$publish?auth()->id():null,
        ]);
        Workspace::log('result.created','Hasil lomba dicatat #'.$result->id,$result->id,'competition_result');
        return back()->with('success','Hasil lomba tersimpan.');
    }
}
