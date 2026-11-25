<?php

namespace Tests\Feature;

use App\Models\{User, EventPeriod, Division, CommitteeMember};
use App\Support\Access;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspacePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function userForRole(string $role, ?string $divisionSlug = null): User
    {
        $user = User::create([
            'name' => 'Akun '.ucwords(str_replace('_', ' ', $role)),
            'email' => $role.'_perf@example.test',
            'password' => 'valid-test-password-2027',
            'is_active' => true,
        ]);
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        $divisionId = $divisionSlug
            ? Division::where('event_period_id', $period->id)->where('slug', $divisionSlug)->value('id')
            : null;
        CommitteeMember::create([
            'user_id' => $user->id,
            'event_period_id' => $period->id,
            'role' => $role,
            'division_id' => $divisionId,
            'is_active' => true,
        ]);
        return $user;
    }

    public function test_workspace_fast_navigation_shell_is_available_for_all_roles(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'super_perf@example.test',
            'password' => 'valid-test-password-2027',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
        $users = [$admin];
        foreach (['ketua', 'wakil', 'sekretaris', 'bendahara', 'koordinator', 'anggota', 'mentor', 'pengurus_rt'] as $role) {
            $users[] = $this->userForRole(
                $role, in_array($role, ['koordinator', 'anggota'], true) ? 'lomba' : null,
            );
        }

        foreach ($users as $user) {
            $this->actingAs($user)->get('/app')->assertOk()
                ->assertSee('data-rt03-workspace-router', false)
                ->assertSee('data-rt03-workspace-progress', false)
                ->assertSee('sidebar-nav', false)
                ->assertSee('page-content', false)
                ->assertSee('data-rt03-confirm-dialog', false);
            $this->actingAs($user)->get('/app/profil')->assertOk()
                ->assertSee('data-rt03-workspace-router', false)
                ->assertSee('data-profile-photo-input', false);
        }
    }

    public function test_workspace_fast_links_keep_login_and_role_access_protected(): void
    {
        $this->get('/app')->assertRedirect('/login');
        $this->get('/app/keuangan')->assertRedirect('/login');

        $mentor = $this->userForRole('mentor');
        $this->actingAs($mentor)->get('/app')->assertOk();
        $this->actingAs($mentor)->get('/app/keuangan')->assertForbidden();
        $this->actingAs($mentor)->get('/app/panitia')->assertOk()
            ->assertDontSee('Tambah panitia');
    }

    public function test_permission_queries_are_reused_only_within_the_same_request(): void
    {
        $member = $this->userForRole('koordinator', 'lomba');

        $membershipQueries = 0;
        $periodQueries = 0;
        DB::listen(function ($query) use (&$membershipQueries, &$periodQueries): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'committee_members') && str_contains($sql, 'from')) {
                $membershipQueries++;
            }
            if (str_contains($sql, 'event_periods') && str_contains($sql, 'from')) {
                $periodQueries++;
            }
        });

        $this->assertTrue(Access::can($member, 'view'));
        $this->assertTrue(Access::can($member, 'competition-manage'));
        $this->assertFalse(Access::can($member, 'finance-view'));
        $this->assertFalse(Access::can($member, 'gallery-manage'));

        $this->assertSame(1, $membershipQueries);
        $this->assertSame(1, $periodQueries);
    }

    public function test_revoking_role_takes_effect_on_next_http_response(): void
    {
        $member = $this->userForRole('koordinator', 'lomba');

        $this->actingAs($member)->get('/app/lomba')->assertOk();
        $member->memberships()->update(['is_active' => false]);

        // A new HTTP request never reuses cached memberships.
        $this->actingAs($member)->get('/app/lomba')->assertForbidden();
    }

    public function test_native_mutation_forms_still_have_csrf_and_html_methods(): void
    {
        $admin = User::create([
            'name' => 'Test Super Admin',
            'email' => 'admin_mutations@example.test',
            'password' => 'valid-test-password-2027',
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin)->get('/app/profil')->assertOk()
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="PATCH"', false)
            ->assertSee('data-rt03-workspace-router', false);
        $this->actingAs($admin)->get('/app/periode')->assertOk()
            ->assertSee('data-rt03-confirm', false);
    }
}
