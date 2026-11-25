<?php

namespace App\Http\Controllers;

use App\Models\{Announcement, CommitteeMember, Competition, CompetitionRegistration, CompetitionResult, DivisionNote, Document, EventSchedule, ExpenseRequest, FinancialTransaction, GalleryItem, Participant, InventoryItem, InventoryMovement, MentorFeedback, Prize, PrizeDistribution, SopGuide, Task, TaskUpdate};
use App\Support\{Access, Workspace};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class RecordDeletionController extends Controller
{
    /** Only explicitly approved, period-scoped records can be deleted. */
    private const RESOURCES = [
        'members' => [CommitteeMember::class, 'manage-members'],
        'tasks' => [Task::class, 'task-update'],
        'notes' => [DivisionNote::class, 'notes-create'],
        'competitions' => [Competition::class, 'competition-manage'],
        'registrations' => [CompetitionRegistration::class, 'registration-manage'],
        'results' => [CompetitionResult::class, 'competition-manage'],
        'prizes' => [Prize::class, 'prize-manage'],
        'inventory' => [InventoryItem::class, 'inventory-manage'],
        'schedules' => [EventSchedule::class, 'schedule-manage'],
        'announcements' => [Announcement::class, 'announcement-manage'],
        'gallery' => [GalleryItem::class, 'gallery-manage'],
        'documents' => [Document::class, 'documents-manage'],
        'guides' => [SopGuide::class, 'guides-manage'],
        'feedback' => [MentorFeedback::class, 'mentor-feedback'],
        'expenses' => [ExpenseRequest::class, 'expenses-request'],
    ];

    public function __invoke(string $resource, int $id): RedirectResponse
    {
        Access::ensure('view', write: true);
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        [$modelClass, $permission] = self::RESOURCES[$resource];
        $period = Workspace::period();
        $query = $modelClass::query();

        if (in_array($resource, ['registrations', 'results'], true)) {
            $query->whereHas('competition', fn ($q) => $q->where('event_period_id', $period->id));
        } else {
            $query->where('event_period_id', $period->id);
        }
        $record = $query->findOrFail($id);

        $divisionId = in_array($resource, ['tasks', 'notes', 'expenses'], true)
            ? $record->division_id : null;
        Access::ensure($permission, $divisionId, true);

        switch ($resource) {
            case 'members':
                abort_if($record->user_id === auth()->id(), 422, 'Tidak bisa mencabut jabatan sendiri.');
                break;
            case 'tasks':
                abort_if(TaskUpdate::where('task_id', $record->id)->exists() || MentorFeedback::where('task_id', $record->id)->exists(), 422, 'Tugas yang sudah memiliki riwayat progres atau masukan mentor tidak boleh dihapus.');
                break;
            case 'competitions':
                abort_if(
                    CompetitionRegistration::where('competition_id', $record->id)->exists()
                    || CompetitionResult::where('competition_id', $record->id)->exists()
                    || Prize::where('competition_id', $record->id)->exists(),
                    422, 'Lomba sudah terhubung dengan peserta, hasil, atau hadiah. Tidak dapat dihapus.'
                );
                break;
            case 'registrations':
                abort_if(CompetitionResult::where('competition_id', $record->competition_id)->where('participant_id', $record->participant_id)->exists(), 422, 'Pendaftaran sudah memiliki hasil lomba.');
                break;
            case 'results':
                abort_if($record->is_published, 422, 'Hasil yang telah dipublikasikan wajib melalui prosedur koreksi.');
                break;
            case 'prizes':
                abort_if($record->distributed_quantity > 0 || PrizeDistribution::where('prize_id', $record->id)->exists(), 422, 'Hadiah yang pernah diserahkan tidak boleh dihapus.');
                break;
            case 'inventory':
                abort_if($record->quantity_in_use > 0 || InventoryMovement::where('inventory_item_id', $record->id)->exists(), 422, 'Inventaris yang mempunyai riwayat peminjaman tidak boleh dihapus.');
                break;
            case 'expenses':
                abort_unless(Access::can(auth()->user(), 'expenses-request-for', $record->division_id), 403);
                abort_unless($record->requested_by === auth()->id() || Access::can(auth()->user(), 'finance-approve') || auth()->user()->is_super_admin, 403);
                abort_if($record->status !== 'pending' || FinancialTransaction::where('expense_request_id', $record->id)->exists(), 422, 'Pengajuan yang sudah diputuskan/terhubung transaksi tidak boleh dihapus.');
                break;
            case 'feedback':
                abort_unless($record->created_by === auth()->id() || auth()->user()->is_super_admin, 403);
                break;
        }

        // Only files uploaded through the private gallery/document modules may be unlinked.
        $filePath = in_array($resource, ['gallery', 'documents'], true) ? $record->file_path : null;
        if ($filePath !== null) {
            $directory = $resource === 'gallery' ? 'gallery' : 'documents';
            abort_unless(str_starts_with($filePath, 'private/'.$directory.'/'.$period->id.'/'), 422, 'Lokasi file tidak valid.');
        }

        DB::transaction(function () use ($record, $resource): void {
            $id = $record->id;
            $participantId = $resource === 'registrations' ? $record->participant_id : null;
            $record->delete();
            // A participant can join several contests. Remove personal data only when orphaned.
            if ($participantId
                && !CompetitionRegistration::where('participant_id', $participantId)->exists()
                && !CompetitionResult::where('participant_id', $participantId)->exists()) {
                Participant::whereKey($participantId)->delete();
            }
            Workspace::log('record.deleted', 'Data '.$resource.' #'.$id.' dihapus', $id, $resource);
        });

        if ($filePath !== null) {
            try {
                if (Storage::disk('local')->exists($filePath)
                    && !Storage::disk('local')->delete($filePath)) {
                    return back()->withErrors(['file' => 'Data berhasil dihapus, tetapi berkas gagal dibersihkan. Hubungi admin.']);
                }
            } catch (\Throwable $e) {
                report($e);
                return back()->withErrors(['file' => 'Data berhasil dihapus, tetapi penyimpanan berkas perlu diperiksa admin.']);
            }
        }

        return back()->with('success', 'Data berhasil dihapus dan tercatat pada log aktivitas.');
    }
}
