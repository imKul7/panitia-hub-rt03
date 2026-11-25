<?php

namespace App\Http\Controllers;

use App\Models\{Document, Division};
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $query=Document::where('event_period_id',$period->id);
        if (!Access::can(auth()->user(),'documents-manage')) {
            $divisionIds=auth()->user()->memberships()->where('event_period_id',$period->id)->where('is_active',true)->whereNotNull('division_id')->pluck('division_id');
            $query->where(fn($q)=>$q->where('uploaded_by',auth()->id())->orWhereIn('division_id',$divisionIds));
        }
        $documents=$query->latest()->paginate(20);
        $divisions=Division::where('event_period_id',$period->id)->get();
        return view('panitia.documents',compact('period','documents','divisions'));
    }
    public function store(Request $request)
    {
        Access::ensure('documents-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'title'=>['required','string','max:200'],
            'document_type'=>['required',Rule::in(['proposal','surat','notulen','laporan','lainnya'])],
            'division_id'=>['nullable',Rule::exists('divisions','id')->where('event_period_id',$period->id)],
            'file'=>['required','file','mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png','max:10240'],
        ]);
        $file=$request->file('file');
        $path=$file->store('private/documents/'.$period->id,'local');
        abort_unless($path, 500, 'Berkas gagal disimpan.');
        unset($data['file']);
        try {
            $item = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $period, $path, $file): Document {
                $item = Document::create([...$data,'event_period_id'=>$period->id,'uploaded_by'=>auth()->id(),'file_path'=>$path,'original_name'=>$file->getClientOriginalName()]);
                Workspace::log('document.created','Dokumen diunggah: '.$item->title,$item->id,'document');
                return $item;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        return back()->with('success','Dokumen berhasil disimpan secara privat.');
    }
    public function download(Document $document)
    {
        Access::ensure('view');
        abort_unless($document->event_period_id===Workspace::period()->id,404);
        abort_unless(Access::can(auth()->user(),'documents-manage') || $document->uploaded_by===auth()->id() || ($document->division_id !== null && auth()->user()->memberships()->where('event_period_id',Workspace::period()->id)->where('is_active',true)->where('division_id',$document->division_id)->exists()),403);
        abort_unless(Storage::disk('local')->exists($document->file_path),404);
        return Storage::disk('local')->download($document->file_path,$document->original_name);
    }
}
