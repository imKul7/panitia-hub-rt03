<?php

namespace Tests\Feature;

use App\Models\{User, EventPeriod, CommitteeMember};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountIdLoginResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $name, string $password = 'oldPass88', bool $admin = false): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '', $name)).'@example.test',
            'password' => $password,
            'is_active' => true,
            'is_super_admin' => $admin,
        ]);
    }

    private function attachRole(User $user, string $role = 'anggota'): void
    {
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        CommitteeMember::create([
            'user_id' => $user->id, 'event_period_id' => $period->id,
            'role' => $role, 'is_active' => true,
        ]);
    }

    public function test_each_existing_database_user_has_stable_formatted_login_id(): void
    {
        $user = $this->user('Anggota Awal');
        $expected = 'RT03-'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT);
        $this->assertSame($expected, $user->login_id);
        $this->assertSame($expected, $user->fresh()->login_id);
        $this->assertSame($user->id, User::findOrFail($user->id)->id);
    }

    public function test_login_uses_id_and_password_without_email_field(): void
    {
        $admin = $this->user('Admin Login', 'adminPass88', true);
        $this->get('/login')->assertOk()
            ->assertSee('name="login_id"', false)
            ->assertSee('data-toggle-password', false)
            ->assertDontSee('name="email"', false);

        $this->post('/login', [
            'login_id' => $admin->login_id,
            'password' => 'adminPass88',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_rejects_wrong_id_wrong_password_and_legacy_email_submission(): void
    {
        $admin = $this->user('Admin Wrong', 'adminPass88', true);
        $this->post('/login', [
            'login_id' => $admin->login_id, 'password' => 'wrongpassword',
        ])->assertSessionHasErrors('login_id');
        $this->post('/login', [
            'login_id' => 'RT03-999999', 'password' => 'adminPass88',
        ])->assertSessionHasErrors('login_id');
        $this->post('/login', [
            'email' => $admin->email, 'password' => 'adminPass88',
        ])->assertSessionHasErrors('login_id');
        $this->assertGuest();
    }

    public function test_new_member_can_be_created_with_eight_character_password_and_login_id(): void
    {
        $admin = $this->user('Admin Creator', 'adminPass88', true);
        $this->actingAs($admin)->post('/app/panitia', [
            'name' => 'Anggota Baru',
            'email' => 'anggota-baru@example.test',
            'password' => 'Pass1234',
            'role' => 'ketua',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $member = User::where('email', 'anggota-baru@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Pass1234', $member->password));
        $this->assertNotSame('Pass1234', $member->password);
        $this->assertStringStartsWith('RT03-', $member->login_id);
    }

    public function test_fewer_than_eight_password_characters_are_rejected(): void
    {
        $admin = $this->user('Admin Short', 'adminPass88', true);
        $this->actingAs($admin)->post('/app/panitia', [
            'name' => 'Password Singkat', 'email' => 'short@example.test',
            'password' => '1234567', 'role' => 'ketua',
        ])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'short@example.test']);
    }

    public function test_profile_password_change_accepts_eight_character_password(): void
    {
        $admin = $this->user('Admin Profile', 'oldPass88', true);
        $this->actingAs($admin)->patch('/app/profil/password', [
            'current_password' => 'oldPass88',
            'password' => 'newPass8',
            'password_confirmation' => 'newPass8',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newPass8', $admin->fresh()->password));
        $this->get('/app/profil')->assertOk()
            ->assertSee($admin->login_id)
            ->assertSee('minlength="8"', false);
    }

    public function test_only_superadmin_sees_all_accounts_without_password_hashes(): void
    {
        $admin = $this->user('Admin Directory', 'adminPass88', true);
        $member = $this->user('Junior Directory', 'juniorPass88');
        $this->attachRole($member, 'ketua');

        $this->actingAs($admin)->get('/app/panitia')->assertOk()
            ->assertSee('Manajemen akun & reset kata sandi', false)
            ->assertSee($member->login_id)
            ->assertSee($member->email)
            ->assertDontSee($member->password)
            ->assertDontSee('juniorPass88');

        $this->actingAs($member)->get('/app/panitia')->assertOk()
            ->assertDontSee('Manajemen akun & reset kata sandi')
            ->assertDontSee(route('people.password.reset', $admin), false);
    }

    public function test_superadmin_can_reset_another_account_only_after_reauthentication(): void
    {
        $admin = $this->user('Admin Reset', 'adminPass88', true);
        $member = $this->user('Junior Reset', 'oldPass88');
        $oldHash = $member->password;

        $this->actingAs($admin)->patch(route('people.password.reset', $member), [
            'current_password' => 'adminPass88',
            'password' => 'newPass8',
            'password_confirmation' => 'newPass8',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotSame($oldHash, $member->fresh()->password);
        $this->assertTrue(Hash::check('newPass8', $member->fresh()->password));
        $this->assertNotEquals('newPass8', $member->fresh()->password);
        $this->assertDatabaseHas('activity_logs', ['action' => 'account.password.reset', 'subject_id' => $member->id]);
    }

    public function test_bad_superadmin_password_and_short_new_password_cannot_reset(): void
    {
        $admin = $this->user('Admin Guard', 'adminPass88', true);
        $member = $this->user('Member Guard', 'oldPass88');
        $oldHash = $member->password;
        $this->actingAs($admin)->patch(route('people.password.reset', $member), [
            'current_password' => 'bad-admin-pass',
            'password' => 'newPass8',
            'password_confirmation' => 'newPass8',
        ])->assertSessionHasErrors('current_password');

        $this->patch(route('people.password.reset', $member), [
            'current_password' => 'adminPass88',
            'password' => 'abc1234',
            'password_confirmation' => 'abc1234',
        ])->assertSessionHasErrors('password');

        $this->assertSame($oldHash, $member->fresh()->password);
    }

    public function test_chair_and_mentor_are_forbidden_to_reset_any_account(): void
    {
        $chair = $this->user('Chair Guard');
        $mentor = $this->user('Mentor Guard');
        $target = $this->user('Target Guard');
        $this->attachRole($chair, 'ketua');
        $this->attachRole($mentor, 'mentor');
        $oldHash = $target->password;

        foreach ([$chair, $mentor] as $actor) {
            $this->actingAs($actor)->patch(route('people.password.reset', $target), [
                'current_password' => 'oldPass88',
                'password' => 'newPass8',
                'password_confirmation' => 'newPass8',
            ])->assertForbidden();
        }
        $this->assertSame($oldHash, $target->fresh()->password);
    }

    public function test_admin_cannot_use_reset_endpoint_for_own_account(): void
    {
        $admin = $this->user('Admin Self', 'adminPass88', true);
        $this->actingAs($admin)->patch(route('people.password.reset', $admin), [
            'current_password' => 'adminPass88',
            'password' => 'newPass8',
            'password_confirmation' => 'newPass8',
        ])->assertStatus(422);
        $this->assertTrue(Hash::check('adminPass88', $admin->fresh()->password));
    }

    public function test_public_hero_has_instrumental_slot_but_no_forced_audio_autoplay(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('rt03-anthem-player', false)
            ->assertSee('Semangat Kemerdekaan')
            ->assertDontSee('autoplay', false);
    }
}
