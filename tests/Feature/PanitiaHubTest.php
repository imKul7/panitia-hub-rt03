<?php

namespace Tests\Feature;

use App\Models\{User,EventPeriod,Division,CommitteeMember,Competition,Participant,Announcement};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $admin=User::create(['name'=>'Admin','email'=>'admin@example.test','password'=>'testing-password-2027','is_super_admin'=>true]);
        $this->actingAs($admin)->get('/app/periode')->assertOk();
    }
}
