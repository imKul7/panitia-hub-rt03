<?php

namespace App\Http\Controllers;

use App\Models\{Task, TaskUpdate, Division, CommitteeMember, User};
use App\Support\{Access, Workspace};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    // Mentor/Pengurus RT monitor, bukan pelaksana operasional.
    private const ASSIGNABLE_ROLES = ['ketua', 'wakil', 'sekretaris', 'bendahara', 'koordinator', 'anggota'];

    public function index()
    {
        Access::ensure('view');
        $period = Workspace::period();
        $tasks = Task::with(['division', 'assignee'])
            ->where('event_period_id', $period->id)
            ->orderByRaw("CASE status WHEN 'blocked' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'review' THEN 2 WHEN 'todo' THEN 3 ELSE 4 END")
            ->orderBy('due_date')->paginate(20);
        $divisions = Division::where('event_period_id', $period->id)->orderBy('name')->get();

        // Collect ALL active divisions for each PIC; unique('user_id') would
        // incorrectly hide a person's secondary division assignment.
        $members = CommitteeMember::with('user')
            ->where('event_period_id', $period->id)
            ->where('is_active', true)
            ->whereIn('role', self::ASSIGNABLE_ROLES)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->get()->groupBy('user_id')
            ->filter(fn ($roles) => $roles->first()->user !== null
                && Access::can($roles->first()->user, 'task-assignee-update'))
            ->map(fn ($roles) => [
                'user_id' => $roles->first()->user_id,
                'name' => $roles->first()->user->name,
                'division_ids' => $roles->pluck('division_id')->filter()->unique()->values()->implode(','),
            ])->sortBy('name')->values();

        return view('panitia.tasks', compact('period', 'tasks', 'divisions', 'members'));
    }

    public function store(Request $request)
    {
        Access::ensure('task-create', write:true);
        $period = Workspace::period();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:6000'],
            'division_id' => ['nullable', Rule::exists('divisions', 'id')->where('event_period_id', $period->id)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'due_date' => ['nullable', 'date'],
        ]);
        $user = auth()->user();
        if (!Access::can($user, 'task-update')
            && !Access::can($user, 'task-update', ($data['division_id'] ?? null))) {
            abort(403, 'Koordinator hanya boleh membuat tugas untuk divisinya.');
        }
        if (!empty($data['assigned_to'])) {
            $membership = CommitteeMember::where('event_period_id', $period->id)
                ->where('user_id', $data['assigned_to'])->where('is_active', true)
                ->whereIn('role', self::ASSIGNABLE_ROLES);

            // A divisional task needs an active member of that very division.
            // General tasks can go to any active operational committee member.
            if (!empty($data['division_id'])) {
                $membership->where('division_id', $data['division_id']);
            }
            $assignee = User::find($data['assigned_to']);
            if (!$membership->exists() || !$assignee
                || !Access::can($assignee, 'task-assignee-update')) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'PIC harus menjadi panitia aktif pada divisi yang dipilih. Ubah divisi, pilih PIC yang tersedia, atau biarkan PIC kosong.',
                ]);
            }
        }

        $task = Task::create([...$data, 'event_period_id' => $period->id,
            'created_by' => $user->id, 'status' => 'todo']);
        Workspace::log('task.created', 'Tugas baru: '.$task->title, $task->id, 'task');

        return back()->with('success', 'Tugas berhasil ditambahkan.');
    }

    public function update(Request $request, Task $task)
    {
        Access::ensure('view', write:true);
        $period = Workspace::period();
        abort_unless($task->event_period_id === $period->id, 404);
        $canManage = Access::can(auth()->user(), 'task-update', $task->division_id);
        // A former assignee must not retain edit privileges after being
        // removed from the task's division (or from the active committee).
        $isActiveAssignee = $task->assigned_to === auth()->id()
            && Access::can(auth()->user(), 'task-assignee-update')
            && ($task->division_id === null
                || CommitteeMember::where('event_period_id', $period->id)
                    ->where('user_id', auth()->id())
                    ->where('division_id', $task->division_id)
                    ->where('is_active', true)
                    ->whereIn('role', self::ASSIGNABLE_ROLES)->exists()
                || Access::can(auth()->user(), 'task-update'));
        abort_unless($canManage || $isActiveAssignee, 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['todo', 'in_progress', 'review', 'done', 'blocked'])],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
        DB::transaction(function () use ($task, $data): void {
            $task->update(['status' => $data['status'],
                'completed_at' => $data['status'] === 'done' ? now() : null]);
            TaskUpdate::create(['task_id' => $task->id, 'user_id' => auth()->id(),
                'status' => $data['status'], 'note' => $data['note'] ?? null]);
            Workspace::log('task.updated', 'Status tugas diperbarui: '.$task->title, $task->id, 'task');
        });

        return back()->with('success', 'Progres tugas berhasil disimpan.');
    }
}
