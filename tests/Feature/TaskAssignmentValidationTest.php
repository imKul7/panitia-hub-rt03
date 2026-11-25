<?php

namespace Tests\Feature;

use App\Models\{User, EventPeriod, Division, CommitteeMember, Task};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskAssignmentValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function member(string $role, ?string $division = null): User
    {
        $user = User::create([
            'name' => ucfirst($role).' Uji',
            'email' => $role.uniqid().'@example.test',
            'password' => 'test-password-rt03-aman',
            'is_active' => true,
        ]);
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        CommitteeMember::create([
            'user_id' => $user->id,
            'event_period_id' => $period->id,
            'role' => $role,
            'division_id' => $division ? Division::where('event_period_id', $period->id)
                ->where('slug', $division)->firstOrFail()->id : null,
            'is_active' => true,
        ]);
        return $user;
    }

    public function test_wrong_division_pic_returns_user_friendly_errors_and_preserves_input(): void
    {
        $chair = $this->member('ketua');
        $pic = $this->member('anggota', 'konsumsi');
        $division = Division::where('slug', 'hadiah')->firstOrFail();

        $this->actingAs($chair)->post('/app/tugas', [
            'title' => 'Tugas Belum Cocok',
            'description' => 'Catatan tetap tersimpan sementara',
            'division_id' => $division->id,
            'assigned_to' => $pic->id,
            'priority' => 'high',
        ])->assertRedirect()->assertSessionHasErrors('assigned_to');

        $this->assertDatabaseMissing('tasks', ['title' => 'Tugas Belum Cocok']);
        $this->get('/app/tugas')->assertOk()
            ->assertSee('Tugas Belum Cocok')
            ->assertSee('Catatan tetap tersimpan sementara')
            ->assertSee('PIC harus menjadi panitia aktif')
            ->assertSee('data-task-assignee', false)
            ->assertSee('data-task-division', false);
    }

    public function test_valid_division_pic_can_be_assigned(): void
    {
        $chair = $this->member('ketua');
        $pic = $this->member('anggota', 'hadiah');
        $division = Division::where('slug', 'hadiah')->firstOrFail();

        $this->actingAs($chair)->post('/app/tugas', [
            'title' => 'Siapkan Piala',
            'division_id' => $division->id,
            'assigned_to' => $pic->id,
            'priority' => 'medium',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Siapkan Piala',
            'assigned_to' => $pic->id,
            'division_id' => $division->id,
        ]);
    }

    public function test_second_active_division_is_available_to_same_pic(): void
    {
        $chair = $this->member('ketua');
        $pic = $this->member('koordinator', 'konsumsi');
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        $hadiah = Division::where('slug', 'hadiah')->firstOrFail();
        CommitteeMember::create([
            'user_id' => $pic->id,
            'event_period_id' => $period->id,
            'role' => 'anggota',
            'division_id' => $hadiah->id,
            'is_active' => true,
        ]);

        $this->actingAs($chair)->get('/app/tugas')->assertOk()
            ->assertSee('data-member-divisions=', false);

        $this->post('/app/tugas', [
            'title' => 'Distribusi Hadiah',
            'division_id' => $hadiah->id,
            'assigned_to' => $pic->id,
            'priority' => 'low',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Distribusi Hadiah',
            'assigned_to' => $pic->id,
            'division_id' => $hadiah->id,
        ]);
    }

    public function test_task_can_be_created_without_pic_and_assigned_later(): void
    {
        $chair = $this->member('ketua');
        $hadiah = Division::where('slug', 'hadiah')->firstOrFail();

        $this->actingAs($chair)->post('/app/tugas', [
            'title' => 'Tugas Menunggu PIC',
            'division_id' => $hadiah->id,
            'assigned_to' => '',
            'priority' => 'medium',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Tugas Menunggu PIC',
            'assigned_to' => null,
        ]);
    }

    public function test_mentor_is_not_assignable_or_allowed_to_create_tasks(): void
    {
        $chair = $this->member('ketua');
        $mentor = $this->member('mentor');

        $this->actingAs($chair)->post('/app/tugas', [
            'title' => 'Tidak untuk Mentor', 'assigned_to' => $mentor->id, 'priority' => 'medium',
        ])->assertRedirect()->assertSessionHasErrors('assigned_to');
        $this->assertDatabaseMissing('tasks', ['title' => 'Tidak untuk Mentor']);

        $this->actingAs($mentor)->post('/app/tugas', [
            'title' => 'Mentor Bukan PIC', 'priority' => 'medium',
        ])->assertForbidden();
        $this->assertDatabaseMissing('tasks', ['title' => 'Mentor Bukan PIC']);
    }

    public function test_inactive_assignee_is_rejected_before_creation(): void
    {
        $chair = $this->member('ketua');
        $pic = $this->member('anggota', 'hadiah');
        $pic->update(['is_active' => false]);

        $this->actingAs($chair)->post('/app/tugas', [
            'title' => 'PIC Sudah Nonaktif', 'assigned_to' => $pic->id, 'priority' => 'medium',
        ])->assertRedirect()->assertSessionHasErrors('assigned_to');
        $this->assertDatabaseMissing('tasks', ['title' => 'PIC Sudah Nonaktif']);
    }

    public function test_coordinator_cannot_create_for_unrelated_division(): void
    {
        $coordinator = $this->member('koordinator', 'lomba');
        $hadiah = Division::where('slug', 'hadiah')->firstOrFail();

        $this->actingAs($coordinator)->post('/app/tugas', [
            'title' => 'Tugas di luar wewenang', 'division_id' => $hadiah->id, 'priority' => 'medium',
        ])->assertForbidden();
        $this->assertDatabaseMissing('tasks', ['title' => 'Tugas di luar wewenang']);
    }

    public function test_known_http_errors_show_branded_workspace_instead_of_debug_page(): void
    {
        $chair = $this->member('ketua');
        $mentor = $this->member('mentor');
        $this->actingAs($mentor)->get('/app/keuangan')->assertForbidden()
            ->assertSee('Akses dibatasi')->assertSee('PANITIA HUB')
            ->assertDontSee('Exception trace');
        $this->actingAs($chair)->get('/app/ini-tidak-ada')->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertDontSee('Symfony\\Component\\HttpKernel\\Exception');
    }
}
