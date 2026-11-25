<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMobileLoginEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_mobile_login_entry_is_present_on_every_resident_page(): void
    {
        foreach ([
            '/', '/informasi/lomba', '/informasi/jadwal',
            '/informasi/pengumuman', '/informasi/hasil', '/galeri',
        ] as $url) {
            $response = $this->get($url)->assertOk()
                ->assertSee('Area Panitia')
                ->assertSee('class="rt03-mobile-login-link"', false)
                ->assertSee('data-rt03-full-navigation', false)
                ->assertSee(route('login'), false)
                ->assertSee('data-mobile-menu', false);
            // One mobile CTA and a separate desktop CTA.
            $this->assertSame(1, substr_count($response->getContent(), 'rt03-mobile-login-link'));
            $this->assertSame(1, substr_count($response->getContent(), 'header-login'));
        }
    }

    public function test_mobile_login_does_not_break_six_page_instant_navigation(): void
    {
        $router = file_get_contents(resource_path('js/public-navigation.js'));
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('a[href]:not([data-rt03-full-navigation])', $router);
        $this->assertStringContainsString('const eligiblePaths = new Set(menuLinks', $router);
        $this->assertStringContainsString('.public-nav.is-open .rt03-mobile-login-link', $css);
        $this->assertStringContainsString('.public-nav .rt03-mobile-login-link{display:none}', $css);
        $this->get('/login')->assertOk()->assertSee('name="login_id"', false);
    }
}
