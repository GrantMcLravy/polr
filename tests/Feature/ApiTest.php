<?php

namespace Tests\Feature;

use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The public API (/api/v2/action/*, /api/v2/data/*): parameters, JSON and
 * plain-text responses, status codes, error messages, CORS header, key auth,
 * anonymous access and quotas, as documented in docs/developer-guide/api.md.
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = '00000000000000000000000a11ce01';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser('alice', ['api_key' => self::KEY, 'api_active' => 1]);
    }

    public function test_shorten_plain_text(): void
    {
        $this->get('/api/v2/action/shorten?'.http_build_query(['key' => self::KEY, 'url' => 'https://example.com/a']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertContent('http://polr.test/0');

        $link = Link::first();
        $this->assertSame('alice', $link->creator);
        $this->assertSame(1, $link->is_api);
    }

    public function test_shorten_json_get_and_post(): void
    {
        $this->getJson('/api/v2/action/shorten?'.http_build_query(['key' => self::KEY, 'url' => 'https://example.com/a', 'response_type' => 'json']))
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertExactJson(['action' => 'shorten', 'result' => 'http://polr.test/0']);

        $this->post('/api/v2/action/shorten', ['key' => self::KEY, 'url' => 'https://example.com/b', 'response_type' => 'json'])
            ->assertOk()
            ->assertExactJson(['action' => 'shorten', 'result' => 'http://polr.test/1']);
    }

    public function test_shorten_custom_and_secret(): void
    {
        $this->post('/api/v2/action/shorten', ['key' => self::KEY, 'url' => 'https://example.com/a', 'custom_ending' => 'mine', 'response_type' => 'json'])
            ->assertJson(['result' => 'http://polr.test/mine']);

        $this->post('/api/v2/action/shorten', ['key' => self::KEY, 'url' => 'https://example.com/b', 'custom_ending' => 'mine', 'response_type' => 'json'])
            ->assertStatus(400)
            ->assertExactJson(['status_code' => 400, 'error_code' => 'CREATION_ERROR', 'error' => 'This URL ending is already in use.']);

        $response = $this->post('/api/v2/action/shorten', ['key' => self::KEY, 'url' => 'https://example.com/c', 'is_secret' => 'true', 'response_type' => 'json']);
        $link = Link::where('long_url', 'https://example.com/c')->first();
        $response->assertExactJson(['action' => 'shorten', 'result' => "http://polr.test/{$link->short_url}/{$link->secret_key}"]);
    }

    public function test_shorten_errors(): void
    {
        $this->get('/api/v2/action/shorten?'.http_build_query(['key' => self::KEY, 'url' => 'not a url']))
            ->assertStatus(400)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertContent('400 Invalid or missing parameters.');

        $this->get('/api/v2/action/shorten?'.http_build_query(['key' => 'wrong', 'url' => 'https://example.com', 'response_type' => 'json']))
            ->assertStatus(401)
            ->assertExactJson(['status_code' => 401, 'error_code' => 'AUTH_ERROR', 'error' => 'Authentication token invalid.']);
    }

    public function test_url_with_spaces_is_accepted(): void
    {
        $this->get('/api/v2/action/shorten?'.http_build_query(['key' => self::KEY, 'url' => 'https://example.com/a b', 'response_type' => 'json']))
            ->assertOk();
    }

    public function test_bulk_shorten(): void
    {
        $data = json_encode(['links' => [
            ['url' => 'https://example.com/a'],
            ['url' => 'https://example.com/b', 'custom_ending' => 'bee'],
        ]]);

        $this->post('/api/v2/action/shorten_bulk', ['key' => self::KEY, 'data' => $data])
            ->assertOk()
            ->assertExactJson(['action' => 'shorten_bulk', 'result' => ['shortened_links' => [
                ['long_url' => 'https://example.com/a', 'short_url' => 'http://polr.test/0'],
                ['long_url' => 'https://example.com/b', 'short_url' => 'http://polr.test/bee'],
            ]]]);

        $this->post('/api/v2/action/shorten_bulk', ['key' => self::KEY, 'data' => '{nope'])
            ->assertStatus(400)
            ->assertJson(['error_code' => 'INVALID_PARAMETERS']);
    }

    public function test_lookup(): void
    {
        $link = $this->createLink('https://example.com/a', ['ending' => 'abc']);
        DB::table('links')->where('id', $link->id)->update(['created_at' => '2020-02-01 12:06:00', 'updated_at' => '2020-02-01 12:07:00', 'clicks' => 10]);

        // Dates keep the DateTime object form of Polr 2.x (see docs/developer-guide/api.md).
        $this->get('/api/v2/action/lookup?'.http_build_query(['key' => self::KEY, 'url_ending' => 'abc', 'response_type' => 'json']))
            ->assertOk()
            ->assertExactJson(['action' => 'lookup', 'result' => [
                'long_url' => 'https://example.com/a',
                'created_at' => ['date' => '2020-02-01 12:06:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
                'clicks' => 10,
                'updated_at' => ['date' => '2020-02-01 12:07:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
            ]]);

        $this->get('/api/v2/action/lookup?'.http_build_query(['key' => self::KEY, 'url_ending' => 'abc']))
            ->assertContent('https://example.com/a');
    }

    public function test_lookup_unknown_and_secret_links(): void
    {
        $this->get('/api/v2/action/lookup?'.http_build_query(['key' => self::KEY, 'url_ending' => 'nope', 'response_type' => 'json']))
            ->assertStatus(404)
            ->assertExactJson(['status_code' => 404, 'error_code' => 'NOT_FOUND', 'error' => 'Link not found.']);

        $link = $this->createLink('https://example.com/s', ['secret' => true]);
        $query = ['key' => self::KEY, 'url_ending' => $link->short_url, 'response_type' => 'json'];
        $this->get('/api/v2/action/lookup?'.http_build_query($query))
            ->assertStatus(401)
            ->assertJson(['error_code' => 'ACCESS_DENIED']);
        $this->get('/api/v2/action/lookup?'.http_build_query($query + ['url_key' => $link->secret_key]))
            ->assertOk();
    }

    public function test_link_stats(): void
    {
        $link = $this->createLink('https://example.com/a', ['ending' => 'abc', 'creator' => 'alice']);
        foreach ([['GB', 'a.example', '2020-02-03 10:00:00'], ['GB', null, '2020-02-03 11:00:00'], ['US', 'a.example', '2020-02-04 10:00:00']] as [$country, $host, $at]) {
            DB::table('clicks')->insert(['link_id' => $link->id, 'ip' => '203.0.113.1', 'country' => $country,
                'referer_host' => $host, 'created_at' => $at, 'updated_at' => $at]);
        }
        $query = ['key' => self::KEY, 'url_ending' => 'abc', 'left_bound' => '2020-02-01', 'right_bound' => '2020-03-01'];

        $this->get('/api/v2/data/link?'.http_build_query($query + ['stats_type' => 'day']))
            ->assertExactJson(['action' => 'data_link_day', 'result' => ['url_ending' => 'abc', 'data' => [
                ['x' => '2020-02-03', 'y' => 2], ['x' => '2020-02-04', 'y' => 1]]]]);
        $this->get('/api/v2/data/link?'.http_build_query($query + ['stats_type' => 'country']))
            ->assertExactJson(['action' => 'data_link_country', 'result' => ['url_ending' => 'abc', 'data' => [
                ['label' => 'GB', 'clicks' => 2], ['label' => 'US', 'clicks' => 1]]]]);
        $this->get('/api/v2/data/link?'.http_build_query($query + ['stats_type' => 'referer']))
            ->assertJsonPath('result.data', [['label' => 'a.example', 'clicks' => 2], ['label' => 'Direct', 'clicks' => 1]]);

        $this->get('/api/v2/data/link?'.http_build_query($query + ['stats_type' => 'bogus']))
            ->assertStatus(400)->assertJson(['error_code' => 'INVALID_ANALYTICS_TYPE']);
        $this->get('/api/v2/data/link?'.http_build_query(['stats_type' => 'day', 'left_bound' => '2015-01-01', 'right_bound' => '2020-02-01'] + $query))
            ->assertStatus(400)->assertJson(['error' => 'Bounds too broad.']);
        $this->get('/api/v2/data/link?'.http_build_query(['url_ending' => 'abc', 'stats_type' => 'day']))
            ->assertStatus(401)->assertJson(['error' => 'Anonymous access of this API is not permitted.']);
    }

    public function test_anonymous_api(): void
    {
        $this->get('/api/v2/action/shorten?'.http_build_query(['url' => 'https://example.com/anon']))
            ->assertOk()->assertContent('http://polr.test/0');
        $this->assertSame('ANONIP-127.0.0.1', Link::first()->creator);

        config(['polr.setting_anon_api' => false]);
        $this->get('/api/v2/action/shorten?'.http_build_query(['url' => 'https://example.com/anon2']))
            ->assertStatus(401)->assertContent('401 Authentication token required.');
    }

    public function test_quota(): void
    {
        $this->createUser('quota', ['api_key' => 'quotakey', 'api_active' => 1]);
        DB::table('users')->where('username', 'quota')->update(['api_quota' => 1]);

        $this->post('/api/v2/action/shorten', ['key' => 'quotakey', 'url' => 'https://example.com/1'])->assertOk();
        $this->post('/api/v2/action/shorten', ['key' => 'quotakey', 'url' => 'https://example.com/2', 'response_type' => 'json'])
            ->assertStatus(429)
            ->assertExactJson(['status_code' => 429, 'error_code' => 'QUOTA_EXCEEDED', 'error' => 'Quota exceeded.']);

        config(['polr.setting_anon_api_quota' => 1]);
        $this->post('/api/v2/action/shorten', ['url' => 'https://example.com/3'])->assertOk();
        $this->post('/api/v2/action/shorten', ['url' => 'https://example.com/4'])->assertStatus(429);
    }

    public function test_quota_is_per_client_ip_behind_trusted_proxy(): void
    {
        config(['polr.setting_anon_api_quota' => 1, 'trustedproxy.proxies' => '127.0.0.1']);

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.1'])
            ->post('/api/v2/action/shorten', ['url' => 'https://example.com/1'])->assertOk();
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.2'])
            ->post('/api/v2/action/shorten', ['url' => 'https://example.com/2'])->assertOk();
        $this->withHeaders(['X-Forwarded-For' => '203.0.113.2'])
            ->post('/api/v2/action/shorten', ['url' => 'https://example.com/3'])->assertStatus(429);

        $this->assertSame(['ANONIP-203.0.113.1', 'ANONIP-203.0.113.2'], Link::orderBy('id')->pluck('creator')->all());
    }
}
