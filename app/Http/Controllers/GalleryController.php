<?php

namespace App\Http\Controllers;

use App\Models\GalleryItem;
use App\Support\PublicPeriod;
use App\Support\{Access,Workspace};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function index()
    {
        Access::ensure('view');
        $period=Workspace::period();
        $items=GalleryItem::where('event_period_id',$period->id)->latest()->paginate(18);
        return view('panitia.gallery',compact('period','items'));
    }

    public function store(Request $request)
    {
        Access::ensure('gallery-manage',write:true);
        $period=Workspace::period();
        $data=$request->validate([
            'title'=>['required','string','max:190'],
            'image'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'consent_confirmed'=>['accepted'],
        ],['consent_confirmed.accepted'=>'Pastikan foto layak dipublikasikan dan izin penggunaan telah diperoleh.']);
        $file=$request->file('image');
        $item=GalleryItem::create([
            'event_period_id'=>$period->id,'uploaded_by'=>auth()->id(),
            'title'=>$data['title'], 'file_path'=>$file->store('private/gallery/'.$period->id,'local'),
            'mime_type'=>$file->getMimeType(), 'consent_confirmed'=>true,
            'is_published'=>$request->boolean('is_published'),
        ]);
        Workspace::log('gallery.created','Foto galeri diunggah: '.$item->title,$item->id,'gallery_item');
        return back()->with('success','Foto berhasil disimpan.');
    }

    public function photo(GalleryItem $item)
    {
        $period = PublicPeriod::current();
        abort_unless($period && $item->event_period_id === $period->id, 404);
        abort_unless($item->is_published && $item->consent_confirmed,404);
        abort_unless(Storage::disk('local')->exists($item->file_path),404);
        return Storage::disk('local')->response($item->file_path,'foto-rt03-'.$item->id, ['Content-Type'=>$item->mime_type, 'X-Content-Type-Options'=>'nosniff']);
    }
}
