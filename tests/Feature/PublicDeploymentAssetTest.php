<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDeploymentAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_missing_private_photos_are_replaced_by_designed_fallbacks(): void
    {
        $response = $this->get('/')->assertOk();
        $html = $response->getContent();

        $hero = collect(['hero-kemerdekaan.jpg', 'hero-kemerdekaan.jpeg', 'hero-kemerdekaan.webp', 'hero-kemerdekaan.png'])
            ->first(fn ($name) => is_file(public_path('images/rt03/'.$name)));
        if ($hero === null) {
            $this->assertStringContainsString('rt03-hero-photo-fallback', $html);
            $this->assertStringNotContainsString('<img class="rt03-hero-photo"', $html);
        } else {
            $this->assertStringContainsString(asset('images/rt03/'.$hero), $html);
        }

        $area = collect(['lingkungan-rt03.jpeg', 'lingkungan-rt03.jpg', 'lingkungan-rt03.webp', 'lingkungan-rt03.png'])
            ->first(fn ($name) => is_file(public_path('images/rt03/'.$name)));
        if ($area === null) {
            $this->assertStringContainsString('rt03-neighborhood-photo-fallback', $html);
            $this->assertStringNotContainsString('alt="Dokumentasi kebersamaan warga di lingkungan RT 03"', $html);
        } else {
            $this->assertStringContainsString(asset('images/rt03/'.$area), $html);
        }
    }

    public function test_public_favicon_never_points_at_nonexistent_photo(): void
    {
        $response = $this->get('/')->assertOk();
        $available = collect(['logo.jpeg','logo.jpg','logo.png','logo.webp'])
            ->first(fn ($name) => is_file(public_path('images/rt03/'.$name)));

        $response->assertSee(asset($available ? 'images/rt03/'.$available : 'favicon.ico'), false);
    }
}
