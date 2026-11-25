<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, CommitteeMember, Competition, CompetitionRegistration, Division, EventPeriod, ExpenseRequest, InventoryItem, InventoryMovement, Participant, Prize, PrizeDistribution, Task, TaskUpdate, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CriticalAuditAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::create([
            'name' => 'Admin Uji Atomik',
            'email' => 'atomic-admin@example.test',
            'password' => 'not-a-real-password',
            'is_active' => true,
            'is_super_admin' => true,
        ]));
    }

    private function period(): EventPeriod
    {
        return EventPeriod::where('status', 'active')->firstOrFail();
    }

    private function simulateAuditWriteFailure(callable $act): void
    {
        $this->withoutExceptionHandling();
        ActivityLog::creating(function () {
            throw new RuntimeException('Audit log failure for regression test');
        });

        try {
            $act();
            $this->fail('The simulated audit logging failure should have been propagated.');
        } catch (RuntimeException $error) {
            $this->assertSame('Audit log failure for regression test', $error->getMessage());
        } finally {
            ActivityLog::flushEventListeners();
        }

        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_expense_request_does_not_exist_without_its_audit_log(): void
    {
        $division = Division::where('event_period_id', $this->period()->id)->firstOrFail();
        $this->simulateAuditWriteFailure(fn () => $this->post(route('expenses.store'), [
            'title' => 'Dana lomba uji atomik',
            'amount_requested' => 25000,
            'division_id' => $division->id,
        ]));
        $this->assertDatabaseCount('expense_requests', 0);
    }

    public function test_registration_and_new_participant_both_roll_back_if_audit_fails(): void
    {
        $competition = Competition::create([
            'event_period_id' => $this->period()->id,
            'name' => 'Lomba Audit',
            'category' => 'Umum',
            'quota' => 2,
        ]);
        $this->simulateAuditWriteFailure(fn () => $this->post(route('registrations.store'), [
            'competition_id' => $competition->id,
            'full_name' => 'Peserta Uji Audit',
        ]));
        $this->assertDatabaseCount('participants', 0);
        $this->assertDatabaseCount('competition_registrations', 0);
    }

    public function test_inventory_quantity_and_movement_roll_back_with_audit(): void
    {
        $item = InventoryItem::create([
            'event_period_id' => $this->period()->id,
            'created_by' => auth()->id(),
            'name' => 'Kursi uji',
            'quantity_total' => 12,
            'quantity_in_use' => 0,
            'condition' => 'baik',
        ]);
        $this->simulateAuditWriteFailure(fn () => $this->post(route('inventory.move', $item), [
            'movement_type' => 'borrow',
            'quantity' => 3,
        ]));
        $this->assertSame(0, (int) $item->fresh()->quantity_in_use);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_prize_stock_and_distribution_roll_back_with_audit(): void
    {
        $prize = Prize::create([
            'event_period_id' => $this->period()->id,
            'created_by' => auth()->id(),
            'title' => 'Hadiah uji',
            'quantity' => 5,
            'distributed_quantity' => 0,
        ]);
        $this->simulateAuditWriteFailure(fn () => $this->post(route('prizes.distribute', $prize), [
            'recipient_name' => 'Penerima Uji',
            'quantity' => 2,
        ]));
        $this->assertSame(0, (int) $prize->fresh()->distributed_quantity);
        $this->assertDatabaseCount('prize_distributions', 0);
    }

    public function test_task_status_and_progress_both_roll_back_with_audit(): void
    {
        $task = Task::create([
            'event_period_id' => $this->period()->id,
            'created_by' => auth()->id(),
            'title' => 'Tugas uji atomik',
            'priority' => 'medium',
            'status' => 'todo',
        ]);
        $this->simulateAuditWriteFailure(fn () => $this->patch(route('tasks.update', $task), [
            'status' => 'done',
            'note' => 'Berhasil',
        ]));
        $this->assertSame('todo', $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertDatabaseCount('task_updates', 0);
    }

    public function test_role_assignment_rolls_back_with_audit(): void
    {
        $user = User::create([
            'name' => 'Calon sekretaris',
            'email' => 'atomic-member@example.test',
            'password' => 'not-a-real-password',
            'is_active' => true,
        ]);
        $this->simulateAuditWriteFailure(fn () => $this->post(route('people.assign'), [
            'user_id' => $user->id,
            'role' => 'sekretaris',
        ]));
        $this->assertDatabaseCount('committee_members', 0);
    }
}
