<?php

namespace Tests\Feature;

use App\Models\{User, EventPeriod, CommitteeMember};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalMotionDesignTest extends TestCase
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
            'name' => 'Super Admin Gerak',
            'email' => 'super-motion@example.test',
            'password' => 'testpass88',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    public function test_all_six_public_pages_have_one_nonblocking_morph_loader(): void
    {
        foreach ([
            '/', '/informasi/lomba', '/informasi/jadwal',
            '/informasi/pengumuman', '/informasi/hasil', '/galeri',
        ] as $url) {
            $response = $this->get($url)->assertOk()
                ->assertSee('data-rt03-loading-overlay', false)
                ->assertSee('rt03-loading-square', false)
                ->assertSee('rt03-loading-liquid', false)
                ->assertSee('rt03-loading-wave', false)
                ->assertSee('data-rt03-loading-label', false);
            $this->assertSame(1, substr_count($response->getContent(), 'data-rt03-loading-overlay'));
            $this->assertStringContainsString('data-rt03-loading-overlay hidden', $response->getContent());
        }
    }

    public function test_login_and_superadmin_workspace_use_same_loading_component(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('data-rt03-loading-overlay', false)
            ->assertSee('rt03-loading-square', false);

        $this->actingAs($this->admin());
        foreach (['/app', '/app/tugas', '/app/panitia', '/app/galeri', '/app/profil'] as $url) {
            $response = $this->get($url)->assertOk()
                ->assertSee('data-rt03-loading-overlay', false)
                ->assertSee('data-rt03-loading-label', false);
            $this->assertSame(1, substr_count($response->getContent(), 'data-rt03-loading-overlay'));
        }
    }

    public function test_internal_mentor_role_still_has_loader_without_new_privileges(): void
    {
        $mentor = User::create([
            'name' => 'Mentor Gerak', 'email' => 'mentor-motion@example.test',
            'password' => 'testpass88', 'is_active' => true,
        ]);
        $period = EventPeriod::where('status', 'active')->firstOrFail();
        CommitteeMember::create([
            'event_period_id' => $period->id, 'user_id' => $mentor->id,
            'role' => 'mentor', 'is_active' => true,
        ]);

        $this->actingAs($mentor)->get('/app/mentor')->assertOk()
            ->assertSee('data-rt03-loading-overlay', false);
        $this->get('/app/keuangan')->assertForbidden();
    }

    public function test_morph_animation_is_respectful_of_reduced_motion(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('@keyframes rt03-square-morph', $css);
        $this->assertStringContainsString('@keyframes rt03-liquid-fill', $css);
        $this->assertStringContainsString('@keyframes rt03-liquid-wave', $css);
        $this->assertStringContainsString('.rt03-loading-overlay[hidden]', $css);
        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $css);
    }

    public function test_public_3d_animation_is_scoped_to_resident_pages_and_composes_with_gsap(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('function rt03InitDepth', $js);
        $this->assertStringContainsString("document.body.classList.contains('public-body')", $js);
        $this->assertStringContainsString("'.listing-card', '.gallery-card'", $js);
        $this->assertStringContainsString("'rt03:public:after-swap'", $js);
        $this->assertStringContainsString("card.style.setProperty('--rt03-depth-scale'", $js);
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('scale:calc(var(--rt03-depth-scale', $css);
        $this->assertStringContainsString('rotate:x var(--rt03-depth-tilt', $css);
    }

    public function test_loading_only_tracks_actual_requests_without_intercepting_backend_forms(): void
    {
        $js = file_get_contents(resource_path('js/loading-animation.js'));
        $public = file_get_contents(resource_path('js/public-navigation.js'));
        $internal = file_get_contents(resource_path('js/workspace-navigation.js'));
        $this->assertStringContainsString('event.defaultPrevented', $js);
        $this->assertStringContainsString("form.dataset.rt03Confirmed !== '1'", $js);
        $this->assertStringNotContainsString('event.preventDefault()', $js);
        $this->assertStringContainsString("addEventListener('pageshow', finish)", $js);
        foreach ([$public, $internal] as $router) {
            $this->assertStringContainsString("'rt03:loading:start'", $router);
            $this->assertStringContainsString("'rt03:loading:end'", $router);
        }
    }
}
