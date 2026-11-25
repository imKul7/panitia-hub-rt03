<?php

namespace Tests\Feature;

use App\Models\{User, EventPeriod, Division, CommitteeMember, ExpenseRequest, Task, GalleryItem};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditFlowIntegrityTest extends TestCase
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
            'name' => 'Admin Uji Audit',
            'email' => 'audit-admin@example.test',
            'password' => 'password-for-testing',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    private function mentor(): User
    {
        $user = User::create([
            'name' => 'Mentor Uji Audit',
            'email' => 'audit-mentor@example.test',
            'password' => 'password-for-testing',
            'is_active' => true,
        ]);
        CommitteeMember::create([
            'user_id' => $user->id,
            'event_period_id' => EventPeriod::where('status', 'active')->firstOrFail()->id,
            'role' => 'mentor',
            'is_active' => true,
        ]);
        return $user;
    }

    public function test_finance_review_cannot_be_overwritten_by_a_second_decision(): void
    {
        $admin = $this->admin();
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        $division = Division::where('event_period_id', $period->id)->firstOrFail();
        $request = ExpenseRequest::create([
            'event_period_id' => $period->id,
            'division_id' => $division->id,
            'requested_by' => $admin->id,
            'title' => 'Dana konsumsi audit',
            'amount_requested' => 50000,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->patch(route('expenses.review', $request), [
            'status' => 'approved',
            'review_note' => 'Disetujui pertama kali',
        ])->assertRedirect();

        $this->patch(route('expenses.review', $request), [
            'status' => 'rejected',
            'review_note' => 'Harus ditolak oleh keputusan kedua',
        ])->assertStatus(422);

        $this->assertDatabaseHas('expense_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'review_note' => 'Disetujui pertama kali',
            'reviewed_by' => $admin->id,
        ]);
        $this->assertNotNull($request->fresh()->reviewed_at);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'expense.reviewed',
            'subject_id' => $request->id,
        ]);
    }

    public function test_failed_membership_rolls_back_account_and_log(): void
    {
        $admin = $this->admin();
        $this->withoutExceptionHandling();

        // Simulate a database/observer failure at the second operation.
        CommitteeMember::creating(function () {
            throw new RuntimeException('Simulated membership insert failure');
        });
        try {
            $this->actingAs($admin)->post(route('people.store'), [
                'name' => 'Akun Harus Rollback',
                'email' => 'rollback@example.test',
                'password' => 'testing-password-2027',
                'role' => 'sekretaris',
            ]);
            $this->fail('Membership failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated membership insert failure', $exception->getMessage());
        } finally {
            CommitteeMember::flushEventListeners();
        }

        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.test']);
        $this->assertDatabaseCount('committee_members', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_mentor_cannot_attach_a_task_to_an_unrelated_division(): void
    {
        $mentor = $this->mentor();
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        $lomba = Division::where('event_period_id', $period->id)
            ->where('slug', 'lomba')->firstOrFail();
        $acara = Division::where('event_period_id', $period->id)
            ->where('slug', 'acara')->firstOrFail();
        $task = Task::create([
            'event_period_id' => $period->id,
            'division_id' => $lomba->id,
            'created_by' => $mentor->id,
            'title' => 'Tugas Divisi Lomba',
            'priority' => 'medium',
            'status' => 'review',
        ]);

        $this->actingAs($mentor)->post(route('mentor.store'), [
            'task_id' => $task->id,
            'division_id' => $acara->id,
            'feedback_text' => 'Pastikan penanggung jawab sesuai divisi.',
        ])->assertSessionHasErrors('division_id');

        $this->assertDatabaseCount('mentor_feedback', 0);

        // Leaving the division blank automatically infers the task division.
        $this->post(route('mentor.store'), [
            'task_id' => $task->id,
            'feedback_text' => 'Diskusikan hambatan lomba.',
        ])->assertRedirect();

        $this->assertDatabaseHas('mentor_feedback', [
            'task_id' => $task->id,
            'division_id' => $lomba->id,
            'feedback_text' => 'Diskusikan hambatan lomba.',
        ]);
    }

    public function test_public_gallery_is_paginated_and_does_not_disclose_private_items(): void
    {
        $admin = $this->admin();
        $period = EventPeriod::where('status', 'active')->firstOrFail();

        for ($i = 1; $i <= 26; $i++) {
            GalleryItem::create([
                'event_period_id' => $period->id,
                'uploaded_by' => $admin->id,
                'title' => 'Foto publik '.$i,
                'file_path' => 'private/gallery/'.$period->id.'/foto-'.$i.'.jpg',
                'mime_type' => 'image/jpeg',
                'consent_confirmed' => true,
                'is_published' => true,
            ]);
        }
        GalleryItem::create([
            'event_period_id' => $period->id,
            'uploaded_by' => $admin->id,
            'title' => 'RAHASIA BELUM DISETUJUI',
            'file_path' => 'private/gallery/'.$period->id.'/private.jpg',
            'mime_type' => 'image/jpeg',
            'consent_confirmed' => false,
            'is_published' => true,
        ]);
        GalleryItem::create([
            'event_period_id' => $period->id,
            'uploaded_by' => $admin->id,
            'title' => 'RAHASIA DRAFT',
            'file_path' => 'private/gallery/'.$period->id.'/draft.jpg',
            'mime_type' => 'image/jpeg',
            'consent_confirmed' => true,
            'is_published' => false,
        ]);

        $page1 = $this->get('/galeri')->assertOk()
            ->assertSee('26 foto')
            ->assertDontSee('RAHASIA BELUM DISETUJUI')
            ->assertDontSee('RAHASIA DRAFT');
        $this->assertSame(24, substr_count($page1->getContent(), '<figure class="gallery-card reveal-child">'));

        $page2 = $this->get('/galeri?page=2')->assertOk()
            ->assertSee('26 foto')
            ->assertDontSee('RAHASIA BELUM DISETUJUI')
            ->assertDontSee('RAHASIA DRAFT');
        $this->assertSame(2, substr_count($page2->getContent(), '<figure class="gallery-card reveal-child">'));
    }
}
