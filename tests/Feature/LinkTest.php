<?php

namespace Tests\Feature;

use App\Helpers\LinkHelper;
use App\Models\Click;
use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee("<h1 class='title'>Polr Test</h1>", false)
            ->assertSee('<meta name="csrf-token"', false)
            ->assertSee('>Sign In</a>', false);
    }

    public function test_shorten_with_counter_ending(): void
    {
        $this->post('/shorten', ['link-url' => 'https://example.com/a'])
            ->assertOk()
            ->assertSee('http://polr.test/0');
        $this->post('/shorten', ['link-url' => 'https://example.com/b'])
            ->assertSee('http://polr.test/1');

        $link = Link::where('short_url', '1')->first();
        $this->assertSame('https://example.com/b', $link->long_url);
        $this->assertSame(sprintf('%u', crc32('https://example.com/b')), $link->long_url_hash);
        $this->assertSame('', $link->creator);
        $this->assertSame('', $link->secret_key);
    }

    public function test_duplicate_long_url_returns_existing_link(): void
    {
        $this->post('/shorten', ['link-url' => 'https://example.com/a'])->assertSee('http://polr.test/0');
        $this->post('/shorten', ['link-url' => 'https://example.com/a'])->assertSee('http://polr.test/0');

        $this->assertSame(1, Link::count());
    }

    public function test_custom_ending(): void
    {
        $this->post('/shorten', ['link-url' => 'https://example.com/a', 'custom-ending' => 'mine'])
            ->assertSee('http://polr.test/mine');

        $this->post('/shorten', ['link-url' => 'https://example.com/b', 'custom-ending' => 'mine'])
            ->assertRedirect('/')
            ->assertSessionHas('error', 'This URL ending is already in use.');
    }

    public function test_secret_link(): void
    {
        $response = $this->post('/shorten', ['link-url' => 'https://example.com/s', 'options' => 's'])->assertOk();

        $link = Link::first();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{4}$/', $link->secret_key);
        $response->assertSee("http://polr.test/{$link->short_url}/{$link->secret_key}");
    }

    public function test_invalid_url_is_rejected(): void
    {
        $this->from('/')->post('/shorten', ['link-url' => 'not a url'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('link-url');
        $this->assertSame(0, Link::count());
    }

    public function test_already_shortened_urls_are_rejected(): void
    {
        $this->assertTrue(LinkHelper::checkIfAlreadyShortened('https://bit.ly/1PUf6Sw'));
        $this->assertTrue(LinkHelper::checkIfAlreadyShortened('http://polr.test/1'));
        $this->assertFalse(LinkHelper::checkIfAlreadyShortened('https://example.com/google'));

        $this->post('/shorten', ['link-url' => 'https://goo.gl/2pSp9f'])
            ->assertSessionHas('error');
    }

    public function test_shorten_requires_login_when_configured(): void
    {
        config(['polr.setting_shorten_permission' => true]);

        $this->post('/shorten', ['link-url' => 'https://example.com/a'])
            ->assertRedirect('/')
            ->assertSessionHas('error', 'You must be logged in to shorten links.');

        $this->actingAsPolrUser($this->createUser('alice'))
            ->post('/shorten', ['link-url' => 'https://example.com/a'])
            ->assertOk();
        $this->assertSame('alice', Link::first()->creator);
    }

    public function test_redirect_counts_click_and_is_not_cacheable(): void
    {
        $link = $this->createLink('https://example.com/target', ['ending' => 'go']);

        $response = $this->get('/go');

        $response->assertStatus(301)->assertRedirect('https://example.com/target');
        // Polr 2.x sent no-cache, so browsers come back to Polr on every click.
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame(1, $link->fresh()->clicks);
        $this->assertSame(0, Click::count(), 'no click details without advanced analytics');
    }

    public function test_secret_link_requires_correct_key(): void
    {
        $link = $this->createLink('https://example.com/secret', ['secret' => true]);

        $this->get("/{$link->short_url}")->assertStatus(403);
        $this->get("/{$link->short_url}/0000")->assertStatus(403);
        $this->get("/{$link->short_url}/{$link->secret_key}")->assertRedirect('https://example.com/secret');
    }

    public function test_disabled_link(): void
    {
        $link = $this->createLink('https://example.com/off', ['ending' => 'off']);
        $link->is_disabled = 1;
        $link->save();

        $this->get('/off')->assertOk()->assertSee('this link has been disabled');

        config(['polr.setting_redirect_404' => true]);
        $this->get('/off')->assertRedirect('https://example.com/home');
    }

    public function test_unknown_link_shows_404_page_or_redirects(): void
    {
        $this->get('/does-not-exist')->assertNotFound()->assertSee('This page could not be found.');
        $this->get('/a/b/c')->assertNotFound();

        config(['polr.setting_redirect_404' => true]);
        $this->get('/does-not-exist')->assertRedirect('https://example.com/home');
    }

    public function test_click_details_with_advanced_analytics(): void
    {
        $this->useGeoIpDatabase(__DIR__.'/../fixtures/geoip/GeoIP2-City-Test.mmdb');
        $link = $this->createLink('https://example.com/target', ['ending' => 'go']);

        $this->withHeaders(['X-Forwarded-For' => '81.2.69.142', 'Referer' => 'https://news.example.com/a?b=c'])
            ->get('/go')
            ->assertRedirect('https://example.com/target');

        $click = Click::first();
        $this->assertSame($link->id, $click->link_id);
        $this->assertSame('81.2.69.142', $click->ip);
        $this->assertSame('GB', $click->country);
        $this->assertSame('https://news.example.com/a?b=c', $click->referer);
        $this->assertSame('news.example.com', $click->referer_host);
    }

    public function test_private_ip_is_recorded_without_country(): void
    {
        $this->useGeoIpDatabase(__DIR__.'/../fixtures/geoip/GeoIP2-City-Test.mmdb');
        $this->createLink('https://example.com/target', ['ending' => 'go']);

        $this->withHeaders(['X-Forwarded-For' => '10.1.2.3'])->get('/go')->assertStatus(301);

        $this->assertNull(Click::first()->country);
    }

    public function test_broken_geoip_database_does_not_break_redirects(): void
    {
        $broken = tempnam(sys_get_temp_dir(), 'geoip');
        file_put_contents($broken, 'not a database');
        $this->useGeoIpDatabase($broken);
        $this->createLink('https://example.com/target', ['ending' => 'go']);

        $this->withHeaders(['X-Forwarded-For' => '81.2.69.142'])->get('/go')
            ->assertRedirect('https://example.com/target');
        $this->withHeaders(['X-Forwarded-For' => '81.2.69.142'])->get('/go')
            ->assertStatus(301);

        $this->assertSame(2, Click::count());
        $this->assertNull(Click::first()->country);
        unlink($broken);
    }

    /** Enable advanced analytics with the given database, trusting X-Forwarded-For. */
    private function useGeoIpDatabase(string $path): void
    {
        $copy = tempnam(sys_get_temp_dir(), 'geoip');
        copy($path, $copy);
        $this->beforeApplicationDestroyed(fn () => @unlink($copy));

        config([
            'polr.setting_adv_analytics' => true,
            'geoip.services.maxmind_database.database_path' => $copy,
            'trustedproxy.proxies' => '*',
        ]);
    }
}
