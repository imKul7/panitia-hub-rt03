<?php

namespace Tests\Feature;

use App\Models\{Document, EventPeriod, FinancialTransaction, User, CommitteeMember};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileDownloadLoaderRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Super Admin Bukti',
            'email' => 'download-admin@example.test',
            'password' => 'testpass88',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    private function period(): EventPeriod
    {
        return EventPeriod::where('status', 'active')->firstOrFail();
    }

    public function test_finance_proof_download_remains_private_and_does_not_trigger_navigation_loader(): void
    {
        $user = $this->admin();
        $path = 'private/finance/'.$this->period()->id.'/proof-test.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\nDummy receipt for regression test");
        $transaction = FinancialTransaction::create([
            'event_period_id' => $this->period()->id,
            'created_by' => $user->id,
            'transaction_type' => 'expense',
            'title' => 'Bukti Pengeluaran Test',
            'amount' => 10000,
            'transaction_date' => '2026-11-24',
            'proof_file' => $path,
            'proof_name' => 'nota-uji.pdf',
        ]);

        // The Blade link is marked as a download; the server still streams
        // the exact same private file via Storage::download.
        $this->actingAs($user)->get('/app/keuangan')->assertOk()
            ->assertSee(route('finance.proof', $transaction), false)
            ->assertSee('data-rt03-download data-rt03-no-loading', false);

        $response = $this->get(route('finance.proof', $transaction))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition', ''));
        $this->assertStringContainsString('nota-uji.pdf', $response->headers->get('content-disposition', ''));
        Storage::disk('local')->assertExists($path);
    }

    public function test_document_download_uses_the_same_loader_exclusion_without_changing_permissions(): void
    {
        $user = $this->admin();
        $path = 'private/documents/'.$this->period()->id.'/regression.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\nDocumentation regression");
        $document = Document::create([
            'event_period_id' => $this->period()->id,
            'uploaded_by' => $user->id,
            'title' => 'Panduan Testing',
            'document_type' => 'laporan',
            'file_path' => $path,
            'original_name' => 'laporan-test.pdf',
        ]);

        $this->actingAs($user)->get('/app/dokumen')->assertOk()
            ->assertSee(route('documents.download', $document), false)
            ->assertSee('data-rt03-download data-rt03-no-loading', false);

        $response = $this->get(route('documents.download', $document))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition', ''));
        $this->assertStringContainsString('laporan-test.pdf', $response->headers->get('content-disposition', ''));
        Storage::disk('local')->assertExists($path);
    }

    public function test_missing_finance_proof_never_shows_download_button_and_cannot_be_fetched(): void
    {
        $user = $this->admin();
        $transaction = FinancialTransaction::create([
            'event_period_id' => $this->period()->id,
            'created_by' => $user->id,
            'transaction_type' => 'income',
            'title' => 'Dana Masuk tanpa bukti',
            'amount' => 15000,
            'transaction_date' => '2026-11-24',
            'proof_file' => null,
        ]);
        $this->actingAs($user)->get('/app/keuangan')->assertOk()
            ->assertDontSee(route('finance.proof', $transaction), false);
        $this->get(route('finance.proof', $transaction))->assertNotFound();
    }

    public function test_finance_proof_stays_forbidden_to_mentor_even_when_file_exists(): void
    {
        $admin = $this->admin();
        $mentor = User::create([
            'name' => 'Senior Mentor Download', 'email' => 'mentor-download@example.test',
            'password' => 'testpass88', 'is_active' => true,
        ]);
        CommitteeMember::create([
            'user_id' => $mentor->id,
            'event_period_id' => $this->period()->id,
            'role' => 'mentor',
            'is_active' => true,
        ]);
        $path = 'private/finance/'.$this->period()->id.'/private-bukti.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\nPrivate data");
        $transaction = FinancialTransaction::create([
            'event_period_id' => $this->period()->id,
            'created_by' => $admin->id,
            'transaction_type' => 'expense',
            'title' => 'Bukti Privat',
            'amount' => 10000,
            'transaction_date' => '2026-11-24',
            'proof_file' => $path,
            'proof_name' => 'private-bukti.pdf',
        ]);

        $this->actingAs($mentor)->get(route('finance.proof', $transaction))->assertForbidden();
        $this->assertGuest() === false; // Unrelated session state is not modified by the download.
    }
}
