<?php

namespace Tests\Feature;

use App\Models\{EventPeriod, GalleryItem, User};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmationDialogTest extends TestCase
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
            'name' => 'Admin Modal',
            'email' => 'admin-modal@example.test',
            'password' => 'safe-test-password-2027',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    public function test_each_workspace_page_uses_one_shared_branded_dialog(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        foreach (['/app', '/app/galeri', '/app/periode', '/app/profil'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('rt03-confirm-dialog', false)
                ->assertSee('data-rt03-confirm-dialog', false)
                ->assertSee('data-rt03-confirm-proceed', false)
                ->assertSee('data-rt03-confirm-cancel', false)
                ->assertDontSee('return confirm(', false);
        }
    }

    public function test_gallery_delete_uses_confirmation_and_keeps_csrf_delete_method(): void
    {
        $admin = $this->admin();
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        $photo = GalleryItem::create([
            'event_period_id' => $period->id,
            'uploaded_by' => $admin->id,
            'title' => 'Foto Uji Modal',
            'file_path' => 'private/gallery/'.$period->id.'/contoh.jpg',
            'mime_type' => 'image/jpeg',
            'consent_confirmed' => true,
            'is_published' => false,
        ]);

        $this->actingAs($admin)->get('/app/galeri')
            ->assertOk()
            ->assertSee('Foto Uji Modal')
            ->assertSee('data-confirm-title="Hapus foto kegiatan?"', false)
            ->assertSee('data-confirm-action="Hapus foto"', false)
            ->assertSee('data-confirm-variant="danger"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('onsubmit="return confirm', false);

        $this->assertDatabaseHas('gallery_items', ['id' => $photo->id]);
    }

    public function test_period_activation_uses_warning_dialog_and_does_not_activate_early(): void
    {
        $admin = $this->admin();
        EventPeriod::create(['year' => 2028, 'name' => 'Rencana 2028', 'status' => 'draft']);

        $this->actingAs($admin)->get('/app/periode')
            ->assertOk()
            ->assertSee('data-confirm-title="Aktifkan periode 2028?"', false)
            ->assertSee('data-confirm-variant="warning"', false)
            ->assertSee('data-confirm-action="Ya, aktifkan"', false)
            ->assertDontSee('onsubmit="return confirm', false);

        $this->assertDatabaseHas('event_periods', ['year' => 2027, 'status' => 'active']);
        $this->assertDatabaseHas('event_periods', ['year' => 2028, 'status' => 'draft']);
    }

    public function test_profile_photo_removal_uses_custom_confirmation(): void
    {
        $admin = $this->admin();
        $admin->update(['avatar_path' => 'avatars/'.$admin->id.'/photo.jpg']);

        $this->actingAs($admin)->get('/app/profil')
            ->assertOk()
            ->assertSee('data-confirm-title="Hapus foto profil?"', false)
            ->assertSee('data-confirm-action="Hapus foto"', false)
            ->assertDontSee('onsubmit="return confirm', false);
        $this->assertNotNull($admin->fresh()->avatar_path);
    }

    public function test_confirmation_is_only_visual_and_does_not_weaken_backend_role_checks(): void
    {
        $admin = $this->admin();
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        $photo = GalleryItem::create([
            'event_period_id' => $period->id,
            'uploaded_by' => $admin->id,
            'title' => 'Foto Terlindungi',
            'file_path' => 'private/gallery/'.$period->id.'/protected.jpg',
            'mime_type' => 'image/jpeg',
            'consent_confirmed' => true,
            'is_published' => false,
        ]);

        $viewer = User::create([
            'name' => 'No Role', 'email' => 'viewer@example.test',
            'password' => 'safe-test-password-2027', 'is_active' => true,
        ]);

        $this->actingAs($viewer)
            ->delete('/app/records/gallery/'.$photo->id)
            ->assertForbidden();
        $this->assertDatabaseHas('gallery_items', ['id' => $photo->id]);
    }
}
