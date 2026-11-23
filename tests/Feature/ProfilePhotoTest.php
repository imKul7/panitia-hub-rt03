<?php

namespace Tests\Feature;

use App\Models\{CommitteeMember, EventPeriod, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    private function user(bool $admin = true): User
    {
        $user = User::create([
            'name' => 'Panitia RT03',
            'email' => 'user'.User::count().'@example.test',
            'password' => 'profile-password-original',
            'is_active' => true,
            'is_super_admin' => $admin,
        ]);

        if (!$admin) {
            CommitteeMember::create([
                'event_period_id' => EventPeriod::where('status', 'active')->firstOrFail()->id,
                'user_id' => $user->id,
                'role' => 'mentor',
                'is_active' => true,
            ]);
        }
        return $user;
    }

    public function test_profile_has_new_account_sections_and_optional_photo(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/app/profil')
            ->assertOk()
            ->assertSee('Data akun')
            ->assertSee('Jabatan saya')
            ->assertSee('Foto profil')
            ->assertSee('Kata sandi')
            ->assertSee('Belum ada foto')
            ->assertSee('Pilih foto');
    }

    public function test_guest_cannot_read_or_change_an_avatar(): void
    {
        $this->get('/app/profil/foto')->assertRedirect('/login');
        $this->post('/app/profil/foto', [])->assertRedirect('/login');
        $this->delete('/app/profil/foto')->assertRedirect('/login');
    }

    public function test_superadmin_can_upload_private_avatar(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/app/profil/foto', [
            'photo' => UploadedFile::fake()->image('profil.jpg', 300, 300),
        ])->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        $this->assertStringStartsWith('avatars/'.$user->id.'/', $user->avatar_path);
        Storage::disk('local')->assertExists($user->avatar_path);
        $this->get('/app/profil/foto')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/app/profil')->assertOk()->assertSee('Foto terpasang');
    }

    public function test_replacement_deletes_previous_file_only(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/app/profil/foto', [
            'photo' => UploadedFile::fake()->image('lama.png', 250, 250),
        ])->assertRedirect();
        $old = $user->fresh()->avatar_path;
        Storage::disk('local')->assertExists($old);
        $this->post('/app/profil/foto', [
            'photo' => UploadedFile::fake()->image('baru.jpg', 300, 300),
        ])->assertRedirect();
        $new = $user->fresh()->avatar_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
    }

    public function test_photo_delete_restores_initials_and_preserves_other_users_files(): void
    {
        $user = $this->user();
        $other = $this->user();
        $pathOther = 'avatars/'.$other->id.'/foto-tetap.png';
        Storage::disk('local')->put($pathOther, 'not-a-photo');
        $other->update(['avatar_path' => $pathOther]);

        $this->actingAs($user)->post('/app/profil/foto', [
            'photo' => UploadedFile::fake()->image('hapus.png', 300, 300),
        ])->assertRedirect();
        $ownPath = $user->fresh()->avatar_path;
        $this->delete('/app/profil/foto')->assertRedirect();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('local')->assertMissing($ownPath);
        Storage::disk('local')->assertExists($pathOther);
        $this->get('/app/profil/foto')->assertNotFound();
        $this->get('/app/profil')->assertSee('Belum ada foto');
    }

    public function test_mentor_can_update_only_their_own_avatar(): void
    {
        $mentor = $this->user(false);
        $this->actingAs($mentor)->post('/app/profil/foto', [
            'photo' => UploadedFile::fake()->image('mentor.jpg', 240, 240),
        ])->assertRedirect();
        Storage::disk('local')->assertExists($mentor->fresh()->avatar_path);
    }

    public function test_invalid_files_and_oversized_images_are_rejected(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post('/app/profil/foto', [
            'photo' => UploadedFile::fake()->create('file.pdf', 120, 'application/pdf'),
        ])->assertSessionHasErrors('photo');
        $this->post('/app/profil/foto', [
            'photo' => UploadedFile::fake()->image('terlalu-besar.jpg', 300, 300)->size(2500),
        ])->assertSessionHasErrors('photo');
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_profile_name_and_password_flows_still_work(): void
    {
        $user = $this->user();
        $this->actingAs($user)->patch('/app/profil', [
            'name' => 'Panitia Profesional',
            'phone' => '081234567891',
        ])->assertRedirect();
        $this->assertSame('Panitia Profesional', $user->fresh()->name);
        $this->patch('/app/profil/password', [
            'current_password' => 'salah',
            'password' => 'password-baru-rt03-aman',
            'password_confirmation' => 'password-baru-rt03-aman',
        ])->assertSessionHasErrors('current_password');
        $this->patch('/app/profil/password', [
            'current_password' => 'profile-password-original',
            'password' => 'password-baru-rt03-aman',
            'password_confirmation' => 'password-baru-rt03-aman',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('password-baru-rt03-aman', $user->fresh()->password));
    }

    public function test_foreign_storage_path_cannot_be_served_or_deleted(): void
    {
        $user = $this->user();
        $other = $this->user();
        $path = 'avatars/'.$other->id.'/protected.jpg';
        Storage::disk('local')->put($path, 'protected');
        $user->update(['avatar_path' => $path]);
        $this->actingAs($user)->get('/app/profil/foto')->assertNotFound();
        $this->delete('/app/profil/foto')->assertRedirect();
        Storage::disk('local')->assertExists($path);
        $this->assertNull($user->fresh()->avatar_path);
    }
}
