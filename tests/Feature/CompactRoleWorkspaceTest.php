<?php

namespace Tests\Feature;

use App\Models\{CommitteeMember, Division, EventPeriod, User};
use App\Support\{Access, RoleMenu};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactRoleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function account(string $name, bool $super = false): User
    {
        return User::create([
            'name' => $name,
            'email' => str_replace(' ', '-', strtolower($name)).'@example.test',
            'password' => 'newPass88',
            'is_active' => true,
            'is_super_admin' => $super,
        ]);
    }

    private function period(): EventPeriod
    {
        return EventPeriod::where('status', 'active')->firstOrFail();
    }

    private function assign(User $user, string $role, ?string $division = null): void
    {
        $divisionId = $division ? Division::where('event_period_id', $this->period()->id)
            ->where('slug', $division)->firstOrFail()->id : null;

        CommitteeMember::create([
            'event_period_id' => $this->period()->id,
            'user_id' => $user->id,
            'role' => $role,
            'division_id' => $divisionId,
            'is_active' => true,
        ]);
    }

    private function routes(array $items): array
    {
        return array_column($items, 'route');
    }

    public function test_short_login_id_is_automatic_and_does_not_require_schema_changes(): void
    {
        $first = $this->account('Pengurus Satu');
        $second = $this->account('Pengurus Dua');
        $this->assertSame('RT03-'.str_pad((string) $first->id, 2, '0', STR_PAD_LEFT), $first->login_id);
        $this->assertSame('RT03-'.str_pad((string) $second->id, 2, '0', STR_PAD_LEFT), $second->login_id);
        $this->assertSame($second->login_id, $second->fresh()->login_id);
        $this->assertNotEquals($first->login_id, $second->login_id);
        $this->assertSame('RT03-'.max(100, $second->id + 100), // 3+ digits keep their width
            'RT03-'.str_pad((string) max(100, $second->id + 100), 2, '0', STR_PAD_LEFT));
    }

    public function test_all_accounts_remain_listed_compactly_to_super_admin_only(): void
    {
        $admin = $this->account('Admin Directory', true);
        $member = $this->account('Ketua Directory');
        $this->assign($member, 'ketua');

        $this->actingAs($admin)->get('/app/panitia')->assertOk()
            ->assertSee('rt03-account-compact-header', false)
            ->assertSee('rt03-account-table', false)
            ->assertSee($member->login_id)
            ->assertSee('Manajemen akun & reset kata sandi', false)
            ->assertSee('Reset')
            ->assertDontSee($member->password);

        $this->actingAs($member)->get('/app/panitia')->assertOk()
            ->assertDontSee('rt03-account-table', false)
            ->assertDontSee('Manajemen akun & reset kata sandi', false);
    }

    public function test_super_admin_priority_menu_has_account_finance_and_period(): void
    {
        $admin = $this->account('Super Active', true);
        $menu = RoleMenu::forUser($admin, $this->period());
        $primary = $this->routes($menu['primary']);
        $this->assertSame('Super Admin', $menu['label']);
        $this->assertContains('people.index', $primary);
        $this->assertContains('finance.index', $primary);
        $this->assertContains('periods.index', $primary);
        $this->assertContains('profile.index', $primary);
        $this->assertGreaterThan(0, count($menu['more']));

        $this->actingAs($admin)->get('/app')->assertOk()
            ->assertSee('PERAN AKTIF')
            ->assertSee('Menu lainnya')
            ->assertSee('rt03-role-identity', false);
    }

    public function test_mentor_has_mentoring_priority_and_cannot_see_finance_or_manage_period(): void
    {
        $mentor = $this->account('Senior Mentor');
        $this->assign($mentor, 'mentor');
        $menu = RoleMenu::forUser($mentor, $this->period());
        $primary = $this->routes($menu['primary']);
        $all = array_merge($primary, $this->routes($menu['more']));
        $this->assertSame('Mentor', $menu['label']);
        $this->assertContains('mentor.index', $primary);
        $this->assertNotContains('finance.index', $all);
        $this->assertNotContains('periods.index', $all);
        $this->assertNotContains('expenses.index', $all);

        $this->actingAs($mentor)->get('/app')->assertOk()
            ->assertSee('Pantau progres &amp; beri arahan.', false)
            ->assertDontSee('Kelola Periode');
        $this->get('/app/keuangan')->assertForbidden();
        $this->assertFalse(Access::can($mentor, 'manage-members'));
    }

    public function test_bendahara_sees_finance_as_main_work_without_super_admin_tools(): void
    {
        $bendahara = $this->account('Bendahara RT');
        $this->assign($bendahara, 'bendahara');
        $menu = RoleMenu::forUser($bendahara, $this->period());
        $primary = $this->routes($menu['primary']);
        $this->assertSame('Bendahara', $menu['label']);
        $this->assertContains('finance.index', $primary);
        $this->assertContains('expenses.index', $primary);
        $this->assertNotContains('periods.index', $primary);
        $this->assertNotContains('people.index', $primary);
        $this->actingAs($bendahara)->get('/app')->assertOk()
            ->assertSee('Kelola anggaran &amp; transaksi.', false);
        $this->get('/app/keuangan')->assertOk();
        $this->get('/app/periode')->assertForbidden();
    }

    public function test_secretary_prioritizes_documents_schedules_and_notes(): void
    {
        $secretary = $this->account('Sekretaris RT');
        $this->assign($secretary, 'sekretaris');
        $menu = RoleMenu::forUser($secretary, $this->period());
        $primary = $this->routes($menu['primary']);
        $this->assertSame('Sekretaris', $menu['label']);
        $this->assertContains('documents.index', $primary);
        $this->assertContains('notes.index', $primary);
        $this->assertContains('schedules.index', $primary);
        $this->assertNotContains('finance.index', $this->routes($menu['more']));
    }

    public function test_coordinator_menu_follows_division_instead_of_showing_all_as_primary(): void
    {
        $lomba = $this->account('Koordinator Lomba');
        $this->assign($lomba, 'koordinator', 'lomba');
        $menu = RoleMenu::forUser($lomba, $this->period());
        $primary = $this->routes($menu['primary']);
        $this->assertStringContainsString('Lomba', $menu['label']);
        $this->assertContains('registrations.index', $primary);
        $this->assertContains('competitions.index', $primary);
        $this->assertContains('results.index', $primary);
        $this->assertNotContains('inventory.index', $primary);

        $publikasi = $this->account('Koordinator Publikasi');
        $this->assign($publikasi, 'koordinator', 'publikasi');
        $different = $this->routes(RoleMenu::forUser($publikasi, $this->period())['primary']);
        $this->assertContains('gallery.index', $different);
        $this->assertContains('announcements.index', $different);
        $this->assertNotContains('competitions.index', $different);
    }

    public function test_multiple_active_assignments_combine_responsibilities(): void
    {
        $user = $this->account('Multi Divisi');
        $this->assign($user, 'koordinator', 'lomba');
        $this->assign($user, 'koordinator', 'acara');
        $primary = $this->routes(RoleMenu::forUser($user, $this->period())['primary']);
        $this->assertContains('competitions.index', $primary);
        $this->assertContains('schedules.index', $primary);
        $this->assertContains('tasks.index', $primary);
    }

    public function test_role_deletion_is_reflected_on_next_request(): void
    {
        $user = $this->account('Berpindah Peran');
        $this->assign($user, 'bendahara');
        $member = $user->memberships()->firstOrFail();
        $this->assertContains('finance.index', $this->routes(RoleMenu::forUser($user, $this->period())['primary']));
        $member->update(['is_active' => false]);
        $this->assertNotContains('finance.index', $this->routes(RoleMenu::forUser($user, $this->period())['primary']));
        $this->actingAs($user)->get('/app')->assertForbidden();
    }
}
