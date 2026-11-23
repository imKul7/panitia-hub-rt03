<?php

namespace App\Http\Controllers;

use App\Models\{Announcement, Competition, CompetitionResult, EventSchedule};
use App\Support\PublicPeriod;

class PublicController extends Controller
{
    private function current()
    {
        return PublicPeriod::current();
    }

    public function home()
    {
        $period = $this->current();
        $competitions = $period ? Competition::where('event_period_id',$period->id)->where('is_published',true)->orderBy('starts_at')->take(4)->get() : collect();
        $announcements = $period ? Announcement::where('event_period_id',$period->id)->where('status','published')->whereNotNull('published_at')->where('published_at','<=',now())->latest('published_at')->take(3)->get() : collect();
        $schedules = $period ? EventSchedule::where('event_period_id',$period->id)->where('is_public',true)->orderBy('starts_at')->take(5)->get() : collect();
        return view('public.home',compact('period','competitions','announcements','schedules'));
    }

    public function section(string $section)
    {
        abort_unless(in_array($section,['lomba','jadwal','pengumuman','hasil'],true),404);
        $period = $this->current();
        $items = collect();
        if ($period) {
            $id = $period->id;
            $items = match($section) {
                'lomba'=> Competition::where('event_period_id',$id)->where('is_published',true)->orderBy('starts_at')->get(),
                'jadwal'=> EventSchedule::where('event_period_id',$id)->where('is_public',true)->orderBy('starts_at')->get(),
                'pengumuman'=> Announcement::where('event_period_id',$id)->where('status','published')->where('published_at','<=',now())->latest('published_at')->get(),
                'hasil'=> CompetitionResult::with('competition')->where('is_published',true)
                    ->whereHas('competition', fn($q)=>$q->where('event_period_id',$id)->where('is_published',true))
                    ->orderBy('competition_id')->orderBy('rank_position')->get(),
            };
        }
        return view('public.section',compact('period','section','items'));
    }
    public function gallery()
    {
        $period=$this->current();
        $items=$period ? \App\Models\GalleryItem::where('event_period_id',$period->id)->where('is_published',true)->where('consent_confirmed',true)->latest()->get() : collect();
        return view('public.gallery',compact('period','items'));
    }
}
