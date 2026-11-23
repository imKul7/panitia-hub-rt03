<?php

namespace Tests\Feature;

use App\Models\{User,EventPeriod,Division,CommitteeMember,Competition,CompetitionRegistration,Participant,Announcement};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PanitiaHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function member(string $role, ?string $division = null): User
    {
        $user=User::create(['name'=>ucfirst($role),'email'=>uniqid($role, true).'@example.test','password'=>'testing-password-2027','is_active'=>true]);
        $period=EventPeriod::where('status','active')->firstOrFail();
        $divisionId=$division ? Division::where('event_period_id',$period->id)->where('slug',$division)->value('id') : null;
        CommitteeMember::create(['event_period_id'=>$period->id,'user_id'=>$user->id,'role'=>$role,'division_id'=>$divisionId,'is_active'=>true]);
        return $user;
    }

    public function test_portal_warga_is_public(): void
    {
        $this->get('/')->assertOk()->assertSee('Semangat');
        $this->get('/informasi/lomba')->assertOk();
    }

    public function test_mentor_cannot_access_finance_or_create_task_but_can_provide_feedback(): void
    {
        $user=$this->member('mentor');
        $this->actingAs($user)->get('/app/keuangan')->assertForbidden();
        $this->actingAs($user)->post('/app/tugas',['title'=>'Mengubah tugas','priority'=>'high'])->assertForbidden();
        $this->actingAs($user)->post('/app/mentor',['feedback_text'=>'Beri kesempatan junior mengambil keputusan.'])->assertRedirect();
        $this->assertDatabaseCount('mentor_feedback',1);
    }

    public function test_unpublished_competition_is_not_exposed(): void
    {
        $period=EventPeriod::where('status','active')->firstOrFail();
        Competition::create(['event_period_id'=>$period->id,'name'=>'Lomba Internal Rahasia','category'=>'Umum','is_published'=>false]);
        $this->get('/informasi/lomba')->assertDontSee('Lomba Internal Rahasia');
    }

    public function test_lomba_coordinator_can_register_a_participant(): void
    {
        $user=$this->member('koordinator','lomba');
        $period=EventPeriod::where('status','active')->firstOrFail();
        $competition=Competition::create(['event_period_id'=>$period->id,'name'=>'Balap Karung','category'=>'Anak','quota'=>20,'is_published'=>true]);
        $this->actingAs($user)->post('/app/peserta',[
            'competition_id'=>$competition->id,'full_name'=>'Peserta Uji','age_group'=>'Anak',
        ])->assertRedirect();
        $this->assertDatabaseHas('participants',['full_name'=>'Peserta Uji']);
        $this->assertDatabaseCount('competition_registrations',1);
    }

    public function test_ordinary_member_cannot_manage_competition(): void
    {
        $user=$this->member('anggota','konsumsi');
        $this->actingAs($user)->post('/app/lomba',['name'=>'Percobaan','category'=>'Umum'])->assertForbidden();
    }

    public function test_guest_cannot_read_internal_dashboard(): void
    {
        $this->get('/app')->assertRedirect('/login');
    }

    public function test_prize_distribution_cannot_exceed_available_quantity(): void
    {
        $user=$this->member('koordinator','hadiah');
        $period=EventPeriod::where('status','active')->firstOrFail();
        $prize=\App\Models\Prize::create(['event_period_id'=>$period->id,'created_by'=>$user->id,'title'=>'Hadiah Uji','quantity'=>2]);
        $this->actingAs($user)->post('/app/hadiah/'.$prize->id.'/distribusi',[
            'recipient_name'=>'Penerima Uji','quantity'=>3,
        ])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('prize_distributions',0);
    }

    public function test_public_gallery_requires_publication(): void
    {
        $user=$this->member('koordinator','publikasi');
        $period=EventPeriod::where('status','active')->firstOrFail();
        $item=\App\Models\GalleryItem::create([
            'event_period_id'=>$period->id, 'uploaded_by'=>$user->id,
            'title'=>'Foto Internal Rahasia', 'file_path'=>'private/no-file.jpg',
            'mime_type'=>'image/jpeg','consent_confirmed'=>true,'is_published'=>false,
        ]);
        $this->get('/galeri')->assertDontSee('Foto Internal Rahasia');
        $this->get('/foto/'.$item->id)->assertNotFound();
    }

    public function test_only_superadmin_can_manage_event_periods(): void
    {
        $chair=$this->member('ketua');
        $this->actingAs($chair)->get('/app/periode')->assertForbidden();
        $admin=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'testing-password-2027','is_active'=>true,'is_super_admin'=>true]);
        $this->actingAs($admin)->get('/app/periode')->assertOk();
    }

    public function test_mentor_cannot_delete_competition(): void
    {
        $mentor = $this->member('mentor');
        $period = EventPeriod::where('status','active')->firstOrFail();
        $competition = Competition::create(['event_period_id'=>$period->id,'name'=>'Lomba Test','category'=>'Umum']);
        $this->actingAs($mentor)->delete('/app/records/competitions/'.$competition->id)->assertForbidden();
        $this->assertDatabaseHas('competitions',['id'=>$competition->id]);
    }

    public function test_coordinator_can_delete_empty_competition_but_not_one_with_registrations(): void
    {
        $coordinator = $this->member('koordinator','lomba');
        $period = EventPeriod::where('status','active')->firstOrFail();
        $empty = Competition::create(['event_period_id'=>$period->id,'name'=>'Kosong','category'=>'Umum']);
        $this->actingAs($coordinator)->delete('/app/records/competitions/'.$empty->id)->assertRedirect();
        $this->assertDatabaseMissing('competitions',['id'=>$empty->id]);
        $filled = Competition::create(['event_period_id'=>$period->id,'name'=>'Berisi','category'=>'Umum']);
        $participant = Participant::create(['event_period_id'=>$period->id,'created_by'=>$coordinator->id,'full_name'=>'Peserta 1']);
        CompetitionRegistration::create(['competition_id'=>$filled->id,'participant_id'=>$participant->id,'registered_by'=>$coordinator->id,'registration_code'=>'RT03-TEST-DELETE','status'=>'confirmed']);
        $this->actingAs($coordinator)->delete('/app/records/competitions/'.$filled->id)->assertStatus(422);
        $this->assertDatabaseHas('competitions',['id'=>$filled->id]);
    }

    public function test_period_scope_prevents_deleting_another_years_data(): void
    {
        $admin = User::create(['name'=>'Admin','email'=>'superadmin-delete@example.test','password'=>'testing-password-2027','is_active'=>true,'is_super_admin'=>true]);
        $old = EventPeriod::create(['year'=>2028,'name'=>'2028','status'=>'draft']);
        $schedule = \App\Models\EventSchedule::create(['event_period_id'=>$old->id,'created_by'=>$admin->id,'title'=>'Agenda 2028','starts_at'=>now()]);
        $this->actingAs($admin)->delete('/app/records/schedules/'.$schedule->id)->assertNotFound();
        $this->assertDatabaseHas('event_schedules',['id'=>$schedule->id]);
    }

    public function test_financial_transaction_has_no_delete_endpoint(): void
    {
        $admin = User::create(['name'=>'Admin','email'=>'superadmin-audit@example.test','password'=>'testing-password-2027','is_active'=>true,'is_super_admin'=>true]);
        $this->actingAs($admin)->delete('/app/records/transactions/1')->assertNotFound();
    }


    public function test_gallery_admin_can_delete_published_photo_and_file(): void
    {
        Storage::fake('local');
        $admin = User::create(['name'=>'Admin','email'=>'admin-delete-photo@example.test','password'=>'testing-password-2027','is_active'=>true,'is_super_admin'=>true]);
        $period = EventPeriod::where('status','active')->firstOrFail();
        $path = 'private/gallery/'.$period->id.'/salah-upload.jpg';
        Storage::disk('local')->put($path, 'test-image-placeholder');
        $item = \App\Models\GalleryItem::create([
            'event_period_id'=>$period->id, 'uploaded_by'=>$admin->id,
            'title'=>'Foto Salah Upload', 'file_path'=>$path, 'mime_type'=>'image/jpeg',
            'consent_confirmed'=>true, 'is_published'=>true,
        ]);

        $this->get('/galeri')->assertSee('Foto Salah Upload');
        $this->actingAs($admin)->delete('/app/records/gallery/'.$item->id)->assertRedirect();
        $this->assertDatabaseMissing('gallery_items',['id'=>$item->id]);
        Storage::disk('local')->assertMissing($path);
        $this->get('/galeri')->assertDontSee('Foto Salah Upload');
        $this->get('/foto/'.$item->id)->assertNotFound();
        $this->assertDatabaseHas('activity_logs',[
            'subject_type'=>'gallery', 'subject_id'=>$item->id, 'action'=>'record.deleted',
        ]);
    }

    public function test_mentor_cannot_delete_gallery_photo_or_its_file(): void
    {
        Storage::fake('local');
        $mentor = $this->member('mentor');
        $period = EventPeriod::where('status','active')->firstOrFail();
        $path = 'private/gallery/'.$period->id.'/hanya-publikasi.jpg';
        Storage::disk('local')->put($path, 'test-image-placeholder');
        $item = \App\Models\GalleryItem::create([
            'event_period_id'=>$period->id, 'uploaded_by'=>$mentor->id,
            'title'=>'Foto Uji Izin', 'file_path'=>$path, 'mime_type'=>'image/jpeg',
            'consent_confirmed'=>true, 'is_published'=>false,
        ]);

        $this->actingAs($mentor)->delete('/app/records/gallery/'.$item->id)->assertForbidden();
        $this->assertDatabaseHas('gallery_items',['id'=>$item->id]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_gallery_delete_cannot_touch_another_period(): void
    {
        Storage::fake('local');
        $admin = User::create(['name'=>'Admin','email'=>'admin-delete-year@example.test','password'=>'testing-password-2027','is_active'=>true,'is_super_admin'=>true]);
        $other = EventPeriod::create(['year'=>2028,'name'=>'Kemerdekaan 2028','status'=>'draft']);
        $path = 'private/gallery/'.$other->id.'/foto-2028.jpg';
        Storage::disk('local')->put($path, 'test-image-placeholder');
        $item = \App\Models\GalleryItem::create([
            'event_period_id'=>$other->id, 'uploaded_by'=>$admin->id,
            'title'=>'Dokumentasi 2028', 'file_path'=>$path, 'mime_type'=>'image/jpeg',
            'consent_confirmed'=>true, 'is_published'=>false,
        ]);

        $this->actingAs($admin)->delete('/app/records/gallery/'.$item->id)->assertNotFound();
        $this->assertDatabaseHas('gallery_items',['id'=>$item->id]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_deleting_registration_preserves_participant_until_all_registrations_are_removed(): void
    {
        $coordinator = $this->member('koordinator','lomba');
        $period = EventPeriod::where('status','active')->firstOrFail();
        $a = Competition::create(['event_period_id'=>$period->id,'name'=>'Lomba A','category'=>'Umum']);
        $b = Competition::create(['event_period_id'=>$period->id,'name'=>'Lomba B','category'=>'Umum']);
        $participant = Participant::create([
            'event_period_id'=>$period->id,'created_by'=>$coordinator->id,'full_name'=>'Peserta Bersama',
        ]);
        $one = CompetitionRegistration::create([
            'competition_id'=>$a->id,'participant_id'=>$participant->id,
            'registered_by'=>$coordinator->id,'registration_code'=>'REG-A-DELETE-TEST',
        ]);
        $two = CompetitionRegistration::create([
            'competition_id'=>$b->id,'participant_id'=>$participant->id,
            'registered_by'=>$coordinator->id,'registration_code'=>'REG-B-DELETE-TEST',
        ]);

        $this->actingAs($coordinator)->delete('/app/records/registrations/'.$one->id)->assertRedirect();
        $this->assertDatabaseHas('participants',['id'=>$participant->id]);
        $this->actingAs($coordinator)->delete('/app/records/registrations/'.$two->id)->assertRedirect();
        $this->assertDatabaseMissing('participants',['id'=>$participant->id]);
    }

    public function test_every_public_navigation_page_uses_indonesian_theme(): void
    {
        foreach ([
            '/', '/informasi/lomba', '/informasi/jadwal',
            '/informasi/pengumuman', '/informasi/hasil', '/galeri',
        ] as $path) {
            $response = $this->get($path)->assertOk()
                ->assertSee('rt03-independence-theme')
                ->assertSee('@view-transition { navigation: auto; }', false);
            if ($path !== '/') {
                $response->assertSee('rt03-listing-hero');
            }
        }
    }


    public function test_secretary_can_write_secretariat_note_but_not_another_divisions_note(): void
    {
        $secretary=$this->member('sekretaris');
        $secretariat=Division::where('slug','sekretariat')->firstOrFail();
        $prizes=Division::where('slug','hadiah')->firstOrFail();
        $this->actingAs($secretary)->post('/app/catatan',[
            'division_id'=>$secretariat->id,'title'=>'Notulen rapat',
            'content'=>'Agenda dan keputusan rapat lingkungan.',
            'note_type'=>'decision',
        ])->assertRedirect();
        $this->assertDatabaseHas('division_notes',['title'=>'Notulen rapat','division_id'=>$secretariat->id]);
        $this->actingAs($secretary)->post('/app/catatan',[
            'division_id'=>$prizes->id,'title'=>'Catatan tanpa wewenang',
            'content'=>'Tidak boleh disimpan.','note_type'=>'info',
        ])->assertForbidden();
        $this->assertDatabaseMissing('division_notes',['title'=>'Catatan tanpa wewenang']);
    }

    public function test_assigning_an_inactive_membership_again_reactivates_it(): void
    {
        $admin=User::create(['name'=>'Super Admin','email'=>'role-reassign@example.test',
            'password'=>'testing-password-2027','is_active'=>true,'is_super_admin'=>true]);
        $secretary=$this->member('sekretaris');
        $membership=$secretary->memberships()->firstOrFail();
        $membership->update(['is_active'=>false]);
        $this->actingAs($admin)->post('/app/panitia/penugasan',[
            'user_id'=>$secretary->id,'role'=>'sekretaris',
        ])->assertRedirect();
        $this->assertDatabaseHas('committee_members',['id'=>$membership->id,'is_active'=>1]);
        $this->assertSame(1, CommitteeMember::where('user_id',$secretary->id)->where('role','sekretaris')->count());
    }

    public function test_archived_period_cannot_be_reactivated_and_draft_can(): void
    {
        $admin=User::create(['name'=>'Super Admin','email'=>'period-audit@example.test',
            'password'=>'testing-password-2027','is_active'=>true,'is_super_admin'=>true]);
        $archived=EventPeriod::create(['year'=>2026,'name'=>'Arsip 2026','status'=>'archived']);
        $draft=EventPeriod::create(['year'=>2028,'name'=>'Rencana 2028','status'=>'draft']);
        $this->actingAs($admin)->patch('/app/periode/'.$archived->id.'/aktifkan')->assertStatus(422);
        $this->assertDatabaseHas('event_periods',['year'=>2027,'status'=>'active']);
        $this->actingAs($admin)->patch('/app/periode/'.$draft->id.'/aktifkan')->assertRedirect();
        $this->assertDatabaseHas('event_periods',['year'=>2028,'status'=>'active']);
        $this->assertDatabaseHas('event_periods',['year'=>2027,'status'=>'archived']);
    }

    public function test_public_cannot_fetch_a_published_photo_from_an_archived_period(): void
    {
        Storage::fake('local');
        $u=$this->member('koordinator','publikasi');
        $older=EventPeriod::create(['year'=>2026,'name'=>'Lomba 2026','status'=>'archived']);
        $path='private/gallery/'.$older->id.'/foto-lama.jpg';
        Storage::disk('local')->put($path,'photo-placeholder');
        $image=\App\Models\GalleryItem::create([
            'event_period_id'=>$older->id,'uploaded_by'=>$u->id,
            'title'=>'Foto Arsip','file_path'=>$path,'mime_type'=>'image/jpeg',
            'consent_confirmed'=>true,'is_published'=>true,
        ]);
        $this->get('/foto/'.$image->id)->assertNotFound();
        Storage::disk('local')->assertExists($path);
    }

    public function test_chair_cannot_assign_task_to_person_outside_task_division(): void
    {
        $chair=$this->member('ketua');
        $other=$this->member('anggota','konsumsi');
        $hadiah=Division::where('slug','hadiah')->firstOrFail();
        $this->actingAs($chair)->post('/app/tugas',[
            'title'=>'Tugas tidak cocok divisi',
            'division_id'=>$hadiah->id,
            'assigned_to'=>$other->id,
            'priority'=>'medium',
        ])->assertStatus(422);
        $this->assertDatabaseMissing('tasks',['title'=>'Tugas tidak cocok divisi']);
    }

    public function test_testing_environment_refuses_to_use_file_based_database(): void
    {
        $original=config('database.connections.sqlite.database');
        try {
            config(['database.connections.sqlite.database'=>database_path('database.sqlite')]);
            $this->expectException(\RuntimeException::class);
            (new \App\Providers\AppServiceProvider(app()))->boot();
        } finally {
            config(['database.connections.sqlite.database'=>$original]);
        }
    }
}
