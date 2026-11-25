<?php

namespace Tests\Feature;

use App\Models\{User, CommitteeMember, Division, Document, EventPeriod, ExpenseRequest, FinancialTransaction, GalleryItem, Task};
use App\Support\LocalDemoResetService;
use Database\Seeders\{DatabaseSeeder, DemoRoleAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class EndToEndAuditRegressionTest extends TestCase
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
            'name' => 'Super Admin Asli',
            'email' => 'original-admin@example.test',
            'password' => 'my-real-admin-password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    private function member(string $role, ?string $division = null): User
    {
        $u = User::create([
            'name' => 'Test '.$role.uniqid(),
            'email' => uniqid('role', true).'@example.test',
            'password' => 'testing-password',
            'is_active' => true,
        ]);
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        CommitteeMember::create([
            'user_id' => $u->id,
            'event_period_id' => $period->id,
            'role' => $role,
            'division_id' => $division
                ? Division::where('event_period_id', $period->id)->where('slug', $division)->value('id')
                : null,
            'is_active' => true,
        ]);
        return $u;
    }

    public function test_real_superadmin_can_open_every_workspace_module(): void
    {
        $this->actingAs($this->admin());
        foreach ([
            '/app', '/app/profil', '/app/panitia', '/app/tugas',
            '/app/catatan', '/app/keuangan', '/app/pengajuan-dana',
            '/app/lomba', '/app/peserta', '/app/hasil', '/app/jadwal',
            '/app/pengumuman', '/app/mentor', '/app/dokumen',
            '/app/hadiah', '/app/inventaris', '/app/galeri',
            '/app/arsip', '/app/periode', '/app/panduan',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_each_of_nine_role_accounts_obeys_finance_and_period_boundaries(): void
    {
        $this->seed(DemoRoleAccountsSeeder::class);

        foreach ([
            'superadmin' => [200, 200],
            'ketua' => [200, 403],
            'wakil' => [200, 403],
            'sekretaris' => [403, 403],
            'bendahara' => [200, 403],
            'koordinator' => [403, 403],
            'anggota' => [403, 403],
            'mentor' => [403, 403],
            'pengurus_rt' => [403, 403],
        ] as $slug => [$financeStatus, $periodStatus]) {
            $user = User::where('email', 'dummy.'.$slug.'@rt03.test')->firstOrFail();
            $this->actingAs($user)->get('/app')->assertOk();
            $this->get('/app/keuangan')->assertStatus($financeStatus);
            $this->get('/app/periode')->assertStatus($periodStatus);
        }
    }

    public function test_old_task_assignee_loses_update_right_after_division_revoked(): void
    {
        $user = $this->member('anggota', 'lomba');
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        $lomba = Division::where('event_period_id', $period->id)
            ->where('slug', 'lomba')->firstOrFail();
        $another = Division::where('event_period_id', $period->id)
            ->where('slug', 'konsumsi')->firstOrFail();

        $task = Task::create([
            'event_period_id' => $period->id,
            'division_id' => $lomba->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'title' => 'Tugas Lombanya Dicabut',
            'priority' => 'high',
            'status' => 'todo',
        ]);

        $this->actingAs($user)->patch(route('tasks.update', $task), [
            'status' => 'in_progress',
        ])->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);

        $user->memberships()->where('division_id', $lomba->id)->update(['is_active' => false]);
        CommitteeMember::create([
            'event_period_id' => $period->id,
            'user_id' => $user->id,
            'division_id' => $another->id,
            'role' => 'anggota',
            'is_active' => true,
        ]);

        $this->patch(route('tasks.update', $task), ['status' => 'done'])
            ->assertForbidden();
        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_document_failed_database_write_does_not_leave_private_orphan_file(): void
    {
        Storage::fake('local');
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin());
        Document::creating(fn () => throw new RuntimeException('Simulated DB failure'));
        try {
            $this->post(route('documents.store'), [
                'title' => 'Dokumen gagal',
                'document_type' => 'surat',
                'file' => UploadedFile::fake()->create('dokumen.pdf', 25, 'application/pdf'),
            ]);
            $this->fail('Expected simulated failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated DB failure', $e->getMessage());
        } finally {
            Document::flushEventListeners();
        }
        $this->assertSame([], Storage::disk('local')->allFiles('private/documents'));
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_gallery_failed_database_write_does_not_leave_private_orphan_file(): void
    {
        Storage::fake('local');
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin());
        GalleryItem::creating(fn () => throw new RuntimeException('Simulated DB failure'));
        try {
            $this->post(route('gallery.store'), [
                'title' => 'Foto gagal',
                'consent_confirmed' => '1',
                'image' => UploadedFile::fake()->image('foto.jpg', 180, 120),
            ]);
            $this->fail('Expected simulated failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated DB failure', $e->getMessage());
        } finally {
            GalleryItem::flushEventListeners();
        }
        $this->assertSame([], Storage::disk('local')->allFiles('private/gallery'));
        $this->assertDatabaseCount('gallery_items', 0);
    }

    public function test_finance_failed_database_write_does_not_leave_private_orphan_proof(): void
    {
        Storage::fake('local');
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin());
        FinancialTransaction::creating(fn () => throw new RuntimeException('Simulated DB failure'));
        try {
            $this->post(route('finance.store'), [
                'transaction_type' => 'expense',
                'title' => 'Pembelian uji',
                'amount' => '50000',
                'transaction_date' => '2027-08-01',
                'proof' => UploadedFile::fake()->image('bukti.jpg', 180, 120),
            ]);
            $this->fail('Expected simulated failure');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated DB failure', $e->getMessage());
        } finally {
            FinancialTransaction::flushEventListeners();
        }
        $this->assertSame([], Storage::disk('local')->allFiles('private/finance'));
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_reset_service_erases_all_demo_history_and_preserves_real_admin(): void
    {
        $original = $this->admin();
        $id = $original->id;
        $passwordHash = $original->password;
        $this->seed(DemoRoleAccountsSeeder::class);
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        $division = Division::where('event_period_id', $period->id)->firstOrFail();
        $demo = User::where('email', 'dummy.ketua@rt03.test')->firstOrFail();

        Task::create([
            'event_period_id' => $period->id,
            'division_id' => $division->id,
            'created_by' => $demo->id,
            'title' => 'Tugas UJI DUMMY',
            'priority' => 'medium',
            'status' => 'todo',
        ]);
        ExpenseRequest::create([
            'event_period_id' => $period->id,
            'division_id' => $division->id,
            'requested_by' => $demo->id,
            'title' => 'Dana UJI DUMMY',
            'amount_requested' => 10000,
            'status' => 'pending',
        ]);
        DB::table('activity_logs')->insert([
            'event_period_id' => $period->id,
            'user_id' => $demo->id,
            'action' => 'test.dummy',
            'description' => 'Riwayat palsu',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new LocalDemoResetService())->reset([$id]);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($id, $original->fresh()->id);
        $this->assertSame($passwordHash, $original->fresh()->password);
        $this->assertTrue(Hash::check('my-real-admin-password', $original->fresh()->password));
        $this->assertDatabaseMissing('users', ['email' => 'dummy.ketua@rt03.test']);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('expense_requests', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertDatabaseCount('event_periods', 1);
        $this->assertDatabaseCount('divisions', 10);
        $this->assertDatabaseCount('sop_guides', 6);
        $this->assertDatabaseHas('event_periods', ['year' => 2027, 'status' => 'active']);
    }

    public function test_reset_rejects_missing_real_admin(): void
    {
        $this->expectException(RuntimeException::class);
        (new LocalDemoResetService())->reset([]);
    }

    public function test_reset_cli_cannot_run_in_testing_environment(): void
    {
        $this->admin();
        $this->artisan('rt03:reset-demo', [
            '--execute' => true, '--confirm' => 'RESET-RT03-LOCAL',
        ])->assertExitCode(1);
        $this->assertDatabaseCount('users', 1);
    }
}
