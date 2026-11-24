<?php

namespace Tests\Feature;

use App\Models\{User, EventPeriod};
use App\Support\FormFeedback;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalValidationFeedbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin Form',
            'email' => 'admin-form@example.test',
            'password' => 'testing-password-2027',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    public function test_same_feedback_dialog_is_available_for_all_workspace_roles_and_login(): void
    {
        $this->get('/login')->assertOk()->assertSee('data-rt03-feedback-dialog', false);
        $this->actingAs($this->admin());
        foreach (['/app','/app/tugas','/app/lomba','/app/keuangan','/app/galeri','/app/profil'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('data-rt03-feedback-dialog', false)
                ->assertSee('data-rt03-feedback-edit', false)
                ->assertSee('data-rt03-auto-open="0"', false);
        }
    }

    public function test_invalid_task_form_shows_reason_suggestion_and_form_target(): void
    {
        $this->actingAs($this->admin())->post('/app/tugas', [
            'title' => '',
            'description' => 'Catatan lama yang perlu diselamatkan',
            'priority' => 'urgent',
            '_rt03_form_target' => 'http://localhost/app/tugas',
        ])->assertRedirect()->assertSessionHasErrors('title');

        $this->get('/app/tugas')->assertOk()
            ->assertSee('data-rt03-auto-open="1"', false)
            ->assertSee('data-rt03-feedback-form-target="http://localhost/app/tugas"', false)
            ->assertSee('data-rt03-feedback-field="title"', false)
            ->assertSee('Saran:')
            ->assertSee('Catatan lama yang perlu diselamatkan');
    }

    public function test_global_validation_applies_to_sixteen_modules(): void
    {
        $this->actingAs($this->admin());
        $modules = [
            ['/app/tugas','title'], ['/app/catatan','division_id'],
            ['/app/lomba','name'], ['/app/peserta','competition_id'],
            ['/app/hasil','competition_id'], ['/app/hadiah','title'],
            ['/app/inventaris','name'], ['/app/jadwal','title'],
            ['/app/pengumuman','title'], ['/app/galeri','title'],
            ['/app/dokumen','title'], ['/app/panduan','title'],
            ['/app/mentor','feedback_text'], ['/app/pengajuan-dana','title'],
            ['/app/keuangan','title'], ['/app/periode','year'],
        ];

        foreach ($modules as [$path,$field]) {
            $this->post($path, [])->assertRedirect()->assertSessionHasErrors($field);
            $this->get($path)->assertOk()
                ->assertSee('data-rt03-auto-open="1"', false)
                ->assertSee('DETAIL KESALAHAN')
                ->assertSee('SARAN PERBAIKAN');
        }
    }

    public function test_same_origin_browser_http_422_returns_with_actionable_feedback(): void
    {
        $this->actingAs($this->admin());
        $archived = EventPeriod::create([
            'year' => 2026, 'name' => 'Arsip 2026', 'status' => 'archived',
        ]);
        $this->from('http://localhost/app/periode')
            ->patch('/app/periode/'.$archived->id.'/aktifkan')
            ->assertRedirect('http://localhost/app/periode')
            ->assertSessionHas('rt03_rejection');

        $this->assertDatabaseHas('event_periods', ['id' => $archived->id, 'status' => 'archived']);
        $this->get('/app/periode')->assertOk()
            ->assertSee('data-rt03-auto-open="1"', false)
            ->assertSee('Hanya periode draf');
    }

    public function test_external_referer_cannot_trigger_open_redirect(): void
    {
        $this->actingAs($this->admin());
        $archived = EventPeriod::create([
            'year' => 2025, 'name' => 'Arsip 2025', 'status' => 'archived',
        ]);
        $this->from('https://attacker.example/app/periode')
            ->patch('/app/periode/'.$archived->id.'/aktifkan')
            ->assertStatus(422);
        $this->patch('/app/periode/'.$archived->id.'/aktifkan')->assertStatus(422);
    }

    public function test_json_http_errors_keep_original_status(): void
    {
        $this->actingAs($this->admin());
        $archived = EventPeriod::create([
            'year' => 2024, 'name' => 'Arsip 2024', 'status' => 'archived',
        ]);
        $this->withHeader('Referer', 'http://localhost/app/periode')
            ->withHeader('Accept', 'application/json')
            ->patch('/app/periode/'.$archived->id.'/aktifkan')->assertStatus(422);
    }

    public function test_rejected_authorization_does_not_create_data(): void
    {
        $user = User::create([
            'name' => 'Tidak Punya Jabatan', 'email' => 'no-role@example.test',
            'password' => 'testing-password-2027', 'is_active' => true,
        ]);
        $this->actingAs($user)->from('http://localhost/app/tugas')
            ->post('/app/tugas', ['title'=>'Tidak Sah','priority'=>'medium'])
            ->assertRedirect('http://localhost/app/tugas')
            ->assertSessionHas('rt03_rejection');
        $this->assertDatabaseMissing('tasks', ['title'=>'Tidak Sah']);
        $this->get('/app/tugas')->assertForbidden();
    }

    public function test_login_validation_uses_feedback_without_leaking_password(): void
    {
        $this->post('/login', ['email'=>'invalid-email','password'=>'password-sekali-pakai'])
            ->assertSessionHasErrors('email');
        $this->get('/login')->assertOk()
            ->assertSee('data-rt03-auto-open="1"', false)
            ->assertDontSee('password-sekali-pakai');
    }

    public function test_suggestions_depend_on_actual_business_constraint(): void
    {
        $this->assertStringContainsString('kuota', FormFeedback::suggestion('competition_id', 'Kuota lomba penuh.'));
        $this->assertStringContainsString('jumlah', FormFeedback::suggestion('quantity', 'Stok tidak mencukupi.'));
        $this->assertStringContainsString('divisi', FormFeedback::suggestion('assigned_to', 'PIC tidak aktif di divisi tugas.'));
        $this->assertStringContainsString('riwayat', FormFeedback::suggestion('', 'Inventaris dengan riwayat tidak dapat dihapus.'));
    }
}
