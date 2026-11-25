<?php

namespace Tests\Feature;

use App\Models\{CommitteeMember, Division, EventPeriod, User};
use Database\Seeders\{DatabaseSeeder, DemoRoleAccountsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoRoleAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_default_seeder_never_creates_known_password_accounts(): void
    {
        $this->assertDatabaseCount('users', 0);
    }

    public function test_creates_nine_dummy_accounts_and_all_permissions_are_scoped(): void
    {
        $this->seed(DemoRoleAccountsSeeder::class);

        $expected = [
            'superadmin' => null,
            'ketua' => 'ketua',
            'wakil' => 'wakil',
            'sekretaris' => 'sekretaris',
            'bendahara' => 'bendahara',
            'koordinator' => 'koordinator',
            'anggota' => 'anggota',
            'mentor' => 'mentor',
            'pengurus_rt' => 'pengurus_rt',
        ];

        $period = EventPeriod::where('status', 'active')->firstOrFail();
        $lombaId = Division::where('event_period_id', $period->id)
            ->where('slug', 'lomba')->value('id');

        $this->assertDatabaseCount('users', count($expected));
        $this->assertDatabaseCount('committee_members', count($expected) - 1);

        foreach ($expected as $slug => $role) {
            $user = User::where('email', 'dummy.'.$slug.'@rt03.test')->firstOrFail();
            $this->assertTrue($user->is_active);
            $this->assertSame($role === null, $user->is_super_admin);
            $this->assertStringStartsWith('RT03-', $user->login_id);
            $this->assertTrue(Hash::check('remajart03', $user->password));
            $this->assertNotSame('remajart03', $user->getRawOriginal('password'));

            $memberships = $user->memberships()->where('event_period_id', $period->id)->get();
            if ($role === null) {
                $this->assertCount(0, $memberships);
            } else {
                $this->assertCount(1, $memberships);
                $this->assertSame($role, $memberships->first()->role);
                $this->assertTrue((bool) $memberships->first()->is_active);
                $this->assertEquals(
                    in_array($slug, ['koordinator', 'anggota'], true) ? $lombaId : null,
                    $memberships->first()->division_id
                );
            }
        }

        $bendahara = User::where('email', 'dummy.bendahara@rt03.test')->firstOrFail();
        $mentor = User::where('email', 'dummy.mentor@rt03.test')->firstOrFail();
        $super = User::where('email', 'dummy.superadmin@rt03.test')->firstOrFail();

        $this->actingAs($bendahara)->get('/app/keuangan')->assertOk();
        $this->actingAs($mentor)->get('/app/keuangan')->assertForbidden();
        $this->actingAs($super)->get('/app/periode')->assertOk();
    }

    public function test_repeated_seeding_is_idempotent_and_real_accounts_untouched(): void
    {
        $real = User::create([
            'name' => 'Akun Panitia Asli',
            'email' => 'real@rt03.test',
            'password' => 'unrelated-secret-123',
            'is_active' => true,
        ]);
        $password = $real->password;

        $this->seed(DemoRoleAccountsSeeder::class);
        $ids = User::where('email', 'like', 'dummy.%@rt03.test')->pluck('id')->sort()->values()->all();
        $this->seed(DemoRoleAccountsSeeder::class);

        $this->assertDatabaseCount('users', 10);
        $this->assertDatabaseCount('committee_members', 8);
        $this->assertSame($ids, User::where('email', 'like', 'dummy.%@rt03.test')
            ->pluck('id')->sort()->values()->all());
        $this->assertSame($password, $real->fresh()->password);
        $this->assertTrue(Hash::check('unrelated-secret-123', $real->fresh()->password));
    }

    public function test_seed_refuses_to_take_over_existing_real_account(): void
    {
        User::create([
            'name' => 'Akun Asli',
            'email' => 'dummy.ketua@rt03.test',
            'password' => 'real-password-keep',
            'is_active' => true,
        ]);

        try {
            $this->seed(DemoRoleAccountsSeeder::class);
            $this->fail('Expected a collision to abort the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('bukan dummy', $exception->getMessage());
        }

        // Entire transaction rolled back: no partial Super Admin dummy.
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('committee_members', 0);
        $this->assertTrue(Hash::check(
            'real-password-keep',
            User::where('email', 'dummy.ketua@rt03.test')->firstOrFail()->password
        ));
    }

    public function test_production_environment_rejected(): void
    {
        $previous = $this->app['env'];
        $thrown = false;

        try {
            $this->app['env'] = 'production';
            // Directly execute seeder to test the guard without Artisan's
            // interactive production db:seed confirmation prompt.
            app(DemoRoleAccountsSeeder::class)->run();
        } catch (RuntimeException $exception) {
            $thrown = true;
            $this->assertStringContainsString('Production ditolak', $exception->getMessage());
        } finally {
            $this->app['env'] = $previous;
        }

        $this->assertTrue($thrown);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_short_rt03_id_can_login_with_the_shared_demo_password(): void
    {
        $this->seed(DemoRoleAccountsSeeder::class);
        $coordinator = User::where('email', 'dummy.koordinator@rt03.test')->firstOrFail();

        $this->post(route('login.submit'), [
            'login_id' => $coordinator->login_id,
            'password' => 'remajart03',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($coordinator);
    }
}
