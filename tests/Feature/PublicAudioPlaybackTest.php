<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\DatabaseSeeder;

class PublicAudioPlaybackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function withAudioAsset(callable $assertions): void
    {
        $path = public_path('audio/indonesia-raya-instrumental.mp3');
        $temporary = !is_file($path);

        if ($temporary) {
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
            file_put_contents($path, 'ID3'); // Blade only checks file presence; no decoding required.
        }

        try {
            $assertions();
        } finally {
            // Never modify or delete a real soundtrack installed by the user.
            if ($temporary && is_file($path)) unlink($path);
        }
    }

    public function test_home_makes_global_audio_available_to_controls_outside_swapped_main(): void
    {
        $this->withAudioAsset(function (): void {
            $response = $this->get('/')->assertOk()
                ->assertSee('id="rt03-public-anthem"', false)
                ->assertSee('data-rt03-anthem-audio', false)
                ->assertSee('preload="auto"', false)
                ->assertSee('data-rt03-anthem-toggle', false)
                ->assertSee('audio/indonesia-raya-instrumental.mp3', false)
                ->assertSee('Semangat Kemerdekaan')
                ->assertDontSee('autoplay', false);

            $html = $response->getContent();
            $this->assertSame(1, substr_count($html, 'data-rt03-anthem-audio'));
            $this->assertLessThan(
                strpos($html, 'data-rt03-anthem-audio'),
                strpos($html, '</main>'),
                'Audio must be outside <main> to survive the instant public router.',
            );
        });
    }

    public function test_all_six_resident_routes_have_the_same_persistent_audio_shell(): void
    {
        $this->withAudioAsset(function (): void {
            foreach ([
                '/', '/informasi/lomba', '/informasi/jadwal',
                '/informasi/pengumuman', '/informasi/hasil', '/galeri',
            ] as $route) {
                $this->get($route)->assertOk()
                    ->assertSee('id="rt03-public-anthem"', false)
                    ->assertSee('data-rt03-anthem-status', false)
                    ->assertSee('rt03-anthem-dock', false)
                    ->assertSee('data-rt03-anthem-toggle', false);
            }
        });
    }

    public function test_when_audio_file_is_missing_the_site_still_renders_without_broken_player(): void
    {
        $path = public_path('audio/indonesia-raya-instrumental.mp3');
        if (is_file($path)) {
            $this->markTestSkipped('The actual MP3 is installed; do not touch the user asset.');
        }

        $this->get('/')->assertOk()
            ->assertSee('Instrumental segera hadir')
            ->assertDontSee('id="rt03-public-anthem"', false)
            ->assertDontSee('rt03-anthem-dock', false);
        $this->get('/informasi/lomba')->assertOk()
            ->assertDontSee('id="rt03-public-anthem"', false);
    }

    public function test_workspace_and_login_never_mount_public_music_player(): void
    {
        $this->get('/login')->assertOk()
            ->assertDontSee('id="rt03-public-anthem"', false)
            ->assertDontSee('rt03-anthem-dock', false);
        $this->get('/app')->assertRedirect('/login');
    }
}
