<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Request handling details where Laravel's defaults differ from Polr 2.x
 * (Lumen 5.1); see MODERNIZATION.md.
 */
class HttpBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_is_enforced_for_web_forms_and_admin_ajax(): void
    {
        $this->enableCsrfProtection();

        // Lumen did not treat a CSRF failure as an HTTP exception: it is a 500.
        $this->post('/shorten', ['link-url' => 'https://example.com'])
            ->assertStatus(500)
            ->assertSee('Oh dear. Something seems to have gone awry.');
        $this->post('/api/v2/admin/toggle_link', ['link_ending' => 'x'])->assertStatus(500);
        // The token is required even for same-origin requests.
        $this->withHeaders(['Sec-Fetch-Site' => 'same-origin'])
            ->post('/shorten', ['link-url' => 'https://example.com'])
            ->assertStatus(500);
        $this->assertSame(0, Link::count());

        $this->withSession(['_token' => 'token-value'])
            ->post('/shorten', ['link-url' => 'https://example.com', '_token' => 'token-value'])
            ->assertOk();
    }

    public function test_public_api_is_exempt_from_csrf_and_gets_no_xsrf_cookie(): void
    {
        $this->enableCsrfProtection();

        $response = $this->post('/api/v2/action/shorten', ['url' => 'https://example.com/api'])->assertOk();

        $cookies = array_map(fn ($c) => $c->getName(), $response->headers->getCookies());
        $this->assertContains('laravel_session', $cookies, 'the public API keeps its session cookie');
        $this->assertNotContains('XSRF-TOKEN', $cookies);
    }

    public function test_error_responses_carry_no_cookies(): void
    {
        $this->assertSame([], $this->get('/does-not-exist')->headers->getCookies());
        $this->assertSame([], $this->get('/api/v2/action/lookup?url_ending=x&key=wrong')->headers->getCookies());

        $this->createLink('https://example.com', ['ending' => 'abc']);
        $this->assertNotEmpty($this->get('/abc')->headers->getCookies(), 'normal responses do');
        // Validation failures were responses, not errors, in Lumen.
        $this->assertNotEmpty($this->post('/shorten', ['link-url' => 'nope'])->headers->getCookies());
    }

    public function test_options_and_wrong_methods_are_405_without_details(): void
    {
        $this->call('OPTIONS', '/api/v2/action/shorten')
            ->assertStatus(405)
            ->assertHeaderMissing('Allow')
            ->assertSee('405');
        $this->call('OPTIONS', '/a/b/c')->assertNotFound();

        $response = $this->get('/shorten')->assertStatus(405);
        $this->assertStringNotContainsString('Supported methods', $response->getContent());
        $this->post('/')->assertStatus(405);
        $this->get('/api/v2/link_avail_check')->assertStatus(405);
    }

    public function test_framework_paths_do_not_shadow_short_links(): void
    {
        foreach (['up', 'storage'] as $ending) {
            $this->createLink("https://example.com/{$ending}", ['ending' => $ending]);
            $this->get("/{$ending}")->assertRedirect("https://example.com/{$ending}");
        }
        $secret = $this->createLink('https://example.com/secret', ['ending' => 'storage2', 'secret' => true]);
        $this->get("/storage2/{$secret->secret_key}")->assertRedirect('https://example.com/secret');

        $this->createLink('https://example.com/shortened', ['ending' => 'shortened']);
        $this->get('/shortened')->assertRedirect('https://example.com/shortened');
    }

    public function test_route_names(): void
    {
        foreach (['index', 'login', 'plogin', 'logout', 'about', 'signup', 'psignup', 'lost_password', 'plost_password',
                  'admin', 'change_password', 'setup', 'psetup', 'setup_finish', 'pshorten', 'api_shorten_url',
                  'api_shorten_url_bulk', 'api_lookup_url', 'api_link_analytics', 'api_link_check'] as $name) {
            $this->assertTrue(Route::has($name), $name);
        }
        $this->assertSame('http://polr.test/api/v2/action/shorten', route('api_shorten_url'));
    }

    public function test_trusted_proxies(): void
    {
        $this->createLink('https://example.com', ['ending' => 'abc']);
        $ip = fn () => Link::latest('id')->value('ip');
        $shorten = fn ($n) => $this->withHeaders(['X-Forwarded-For' => '203.0.113.9'])
            ->post('/shorten', ['link-url' => "https://example.com/{$n}"]);

        $shorten(1);
        $this->assertSame('127.0.0.1', $ip(), 'no trusted proxies by default');

        config(['trustedproxy.proxies' => '10.0.0.0/8']);
        $shorten(2);
        $this->assertSame('127.0.0.1', $ip(), 'proxy not in the trusted range');

        config(['trustedproxy.proxies' => '127.0.0.0/8, 10.0.0.0/8']);
        $shorten(3);
        $this->assertSame('203.0.113.9', $ip());

        config(['trustedproxy.proxies' => '*']);
        $shorten(4);
        $this->assertSame('203.0.113.9', $ip());
    }

    public function test_setup_wizard_writes_the_same_env_file_as_polr_2(): void
    {
        $dir = sys_get_temp_dir().'/polr-setup-'.uniqid();
        mkdir($dir);
        $this->app->useEnvironmentPath($dir);
        config(['polr.setup_ran' => false, 'polr.version' => '2.3.0', 'polr.version_relmonth' => 'Jan',
                'polr.version_relday' => '28', 'polr.version_relyear' => '2020']);

        // The same awkward values that baseline/seed.py gave the 2.3.0 wizard.
        $this->post('/setup', [
            'db:host' => 'mysql', 'db:port' => '3306', 'db:username' => 'user with space',
            'db:password' => 'p&ss"w#rd $HOME ${APP_KEY} \\back', 'db:name' => 'polr',
            'app:name' => 'Polr "Quoted" & <Tagged> #1 $dollar café', 'app:protocol' => 'http://',
            'app:external_url' => 'short.example.com:8443', 'setting:adv_analytics' => 'true',
            'maxmind:license_key' => 'LICENSE_KEY_123', 'setting:shorten_permission' => 'false',
            'setting:public_interface' => 'true', 'setting:redirect_404' => 'false',
            'setting:index_redirect' => 'https://example.com/?a=1&b=2#frag', 'setting:pseudor_ending' => 'true',
            'setting:base' => '62', 'acct:username' => 'polradmin', 'acct:email' => 'admin@polr.test',
            'acct:password' => 'AdminPass123', 'app:smtp_server' => 'mailpit', 'app:smtp_port' => '1025',
            'app:smtp_username' => '', 'app:smtp_password' => 'smtp&pw"#$', 'app:smtp_from' => 'noreply@polr.test',
            'app:smtp_from_name' => "Polr's Mailer", 'setting:anon_api' => 'true', 'setting:anon_api_quota' => '30',
            'setting:auto_api_key' => 'false', 'setting:registration_permission' => 'email',
            'setting:restrict_email_domain' => 'true', 'setting:allowed_email_domains' => 'example.com, example.org',
            'setting:password_recovery' => 'true', 'setting:acct_registration_recaptcha' => 'true',
            'setting:recaptcha_site_key' => 'site-key-123', 'setting:recaptcha_secret_key' => 'secret&key"456',
            'app:stylesheet' => '//maxcdn.bootstrapcdn.com/bootswatch/3.3.7/cyborg/bootstrap.min.css',
        ])->assertRedirect('/setup/finish');

        $random = '/^(APP_KEY|TMP_SETUP_AUTH_KEY|POLR_GENERATED_AT)=.*$/m';
        $expected = preg_replace($random, '$1=…', file_get_contents(__DIR__.'/../../baseline/fixtures/env/setup-quirks.env'));
        $written = file_get_contents("{$dir}/.env");
        $this->assertSame($expected, preg_replace($random, '$1=…', $written));
        $this->assertMatchesRegularExpression('/^APP_KEY="[0-9a-f]{32}"$/m', $written);

        unlink("{$dir}/.env");
        rmdir($dir);
    }

    public function test_setup_is_closed_after_it_ran(): void
    {
        $this->get('/setup')->assertOk()->assertSee('Sorry, but you have already completed the setup process.');
        $this->get('/setup/finish')->assertNotFound();
    }
}
