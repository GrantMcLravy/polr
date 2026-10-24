<?php

namespace Tests\Feature;

use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createAdmin('admin');
        $this->alice = $this->createUser('alice');
    }

    public function test_admin_endpoints_require_admin(): void
    {
        $this->createLink('https://example.com/a', ['ending' => 'x']);
        $endpoints = [
            ['POST', '/api/v2/admin/toggle_api_active', ['user_id' => 2]],
            ['POST', '/api/v2/admin/edit_api_quota', ['user_id' => 2, 'new_quota' => 5]],
            ['POST', '/api/v2/admin/toggle_user_active', ['user_id' => 2]],
            ['POST', '/api/v2/admin/change_user_role', ['user_id' => 2, 'role' => 'admin']],
            ['POST', '/api/v2/admin/add_new_user', ['username' => 'x']],
            ['POST', '/api/v2/admin/delete_user', ['user_id' => 1]],
            ['POST', '/api/v2/admin/toggle_link', ['link_ending' => 'x']],
            ['POST', '/api/v2/admin/delete_link', ['link_ending' => 'x']],
            ['POST', '/api/v2/admin/edit_link_long_url', ['link_ending' => 'x', 'new_long_url' => 'https://example.com']],
            ['GET', '/api/v2/admin/get_admin_users', []],
            ['GET', '/api/v2/admin/get_admin_links', []],
        ];
        foreach ($endpoints as [$method, $uri, $data]) {
            $this->actingAsPolrUser($this->alice)->call($method, $uri, $data)
                ->assertStatus(401)
                ->assertSee('401 User not admin.');
        }

        $this->flushSession();
        $this->get('/api/v2/admin/get_user_links')->assertStatus(401)->assertSee('User must be authenticated.');
    }

    public function test_user_and_link_management(): void
    {
        $as = fn () => $this->actingAsPolrUser($this->admin);

        $as()->post('/api/v2/admin/add_new_user', ['username' => 'erin', 'user_password' => 'ErinPass1', 'user_email' => 'erin@example.com', 'user_role' => ''])
            ->assertOk()->assertContent('OK');
        $erin = User::where('username', 'erin')->first();
        $this->assertSame('1', (string) $erin->active);
        $this->assertSame('0', $erin->api_key, 'stored as in Polr 2.x');

        $as()->post('/api/v2/admin/toggle_user_active', ['user_id' => $erin->id])->assertContent('0');
        $as()->post('/api/v2/admin/change_user_role', ['user_id' => $erin->id, 'role' => 'admin'])->assertContent('OK');
        $as()->post('/api/v2/admin/toggle_api_active', ['user_id' => $this->alice->id])->assertContent('1');
        $key = $as()->post('/api/v2/admin/generate_new_api_key', ['user_id' => $this->alice->id])->getContent();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{30}$/', $key);
        $as()->post('/api/v2/admin/edit_api_quota', ['user_id' => $this->alice->id, 'new_quota' => 15])->assertContent('OK');
        $this->assertSame('15', (string) $this->alice->fresh()->api_quota);
        $as()->post('/api/v2/admin/delete_user', ['user_id' => $erin->id])->assertContent('OK');
        $this->assertNull(User::find($erin->id));

        $this->createLink('https://example.com/a', ['ending' => 'abc']);
        $as()->post('/api/v2/admin/toggle_link', ['link_ending' => 'abc'])->assertContent('Enable');
        $as()->post('/api/v2/admin/toggle_link', ['link_ending' => 'abc'])->assertContent('Disable');
        $as()->post('/api/v2/admin/edit_link_long_url', ['link_ending' => 'abc', 'new_long_url' => 'https://example.com/edited'])->assertContent('OK');
        $this->assertSame(sprintf('%u', crc32('https://example.com/edited')), Link::first()->long_url_hash);
        $as()->post('/api/v2/admin/delete_link', ['link_ending' => 'abc'])->assertContent('OK');
        $this->assertSame(0, Link::count());
        $as()->post('/api/v2/admin/toggle_link', ['link_ending' => 'abc'])->assertNotFound()->assertSee('This page could not be found.');
    }

    public function test_ajax_validation_errors_use_lumen_format(): void
    {
        $this->createLink('https://example.com/a', ['ending' => 'abc']);

        $this->actingAsPolrUser($this->admin)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('/api/v2/admin/edit_link_long_url', ['link_ending' => 'abc', 'new_long_url' => 'not a url'])
            ->assertStatus(422)
            ->assertExactJson(['new_long_url' => ['The new long url format is invalid.']]);
    }

    public function test_link_availability(): void
    {
        $this->createLink('https://example.com/a', ['ending' => 'taken']);

        $this->post('/api/v2/link_avail_check', ['link_ending' => 'free'])->assertContent('available');
        $this->post('/api/v2/link_avail_check', ['link_ending' => 'taken'])->assertContent('unavailable');
        $this->post('/api/v2/link_avail_check', ['link_ending' => 'not ok'])->assertContent('invalid');
    }

    public function test_admin_links_table(): void
    {
        $link = $this->createLink('https://example.com/<b>', ['ending' => 'abc', 'creator' => '<alice>']);
        DB::table('links')->where('id', $link->id)->update(['created_at' => '2020-02-01 12:00:00', 'clicks' => 3]);

        $response = $this->actingAsPolrUser($this->admin)
            ->get('/api/v2/admin/get_admin_links?'.http_build_query($this->tableQuery(['short_url', 'long_url', 'clicks', 'created_at', 'creator', 'disable', 'delete'])))
            ->assertOk();

        $this->assertSame(['draw', 'recordsTotal', 'recordsFiltered', 'data'], array_keys($response->json()));
        $row = $response->json('data.0');
        $this->assertSame('abc', $row['short_url']);
        $this->assertSame(3, $row['clicks']);
        $this->assertSame('2020-02-01 12:00:00', $row['created_at']);
        $this->assertSame('&lt;alice&gt;', $row['creator'], 'escaped column');
        $this->assertStringStartsWith('<a target="_blank" title="https://example.com/&lt;b&gt;"', $row['long_url']);
        $this->assertStringContainsString('ng-click="toggleLink($event, \'abc\')"', $row['disable']);
    }

    public function test_admin_users_and_own_links_tables(): void
    {
        $users = $this->actingAsPolrUser($this->admin)
            ->get('/api/v2/admin/get_admin_users?'.http_build_query($this->tableQuery(['username', 'email', 'created_at', 'toggle_active', 'api_action', 'change_role', 'delete'], ['order[0][column]' => 0])))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2);
        $this->assertSame('admin', $users->json('data.0.username'));
        $this->assertSame(0, $users->json('data.0.api_active'));
        $this->assertStringContainsString('btn-success', $users->json('data.1.toggle_active'));

        $this->createLink('https://example.com/mine', ['creator' => 'alice']);
        config(['polr.setting_adv_analytics' => true]);
        $own = $this->actingAsPolrUser($this->alice)
            ->get('/api/v2/admin/get_user_links?'.http_build_query($this->tableQuery(['short_url', 'long_url', 'clicks', 'created_at'])))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1);
        $this->assertStringStartsWith('0 <a target="_blank" class="stats-icon" href="/admin/stats/0">', $own->json('data.0.clicks'));
    }

    public function test_stats_page(): void
    {
        $link = $this->createLink('https://example.com/a', ['ending' => 'abc', 'creator' => 'alice']);
        DB::table('clicks')->insert(['link_id' => $link->id, 'ip' => '203.0.113.1', 'country' => 'GB',
            'referer_host' => null, 'created_at' => '2020-02-03 10:00:00', 'updated_at' => '2020-02-03 10:00:00']);
        $query = '?left_bound=2020-02-01&right_bound=2020-03-01';

        $this->get('/admin/stats/abc'.$query)->assertRedirect('/login');
        $this->actingAsPolrUser($this->alice)->get('/admin/stats/abc'.$query)
            ->assertRedirect('/login')->assertSessionHas('error', 'Please enable advanced analytics to view this page.');

        config(['polr.setting_adv_analytics' => true]);
        $this->actingAsPolrUser($this->alice)->get('/admin/stats/abc'.$query)
            ->assertOk()
            ->assertSee('var countryData = JSON.parse(\'[{"label":"GB","clicks":1}]\');', false)
            ->assertSee('GeoLite2 data created by MaxMind');
        $this->actingAsPolrUser($this->createUser('bob'))->get('/admin/stats/abc'.$query)
            ->assertRedirect('/admin')->assertSessionHas('error', 'You do not have permission to view stats for this link.');
        $this->actingAsPolrUser($this->admin)->get('/admin/stats/abc'.$query)->assertOk();
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('polr:create-admin', ['username' => 'cliadmin', 'email' => 'cli@example.com', '--password' => 'Cli Pass 1'])
            ->expectsOutput('Administrator cliadmin created.')
            ->assertSuccessful();
        $this->artisan('polr:create-admin', ['username' => 'cliadmin', 'email' => 'x@example.com', '--password' => 'x'])
            ->assertFailed();
        $this->artisan('polr:create-admin', ['username' => 'bad name', 'email' => 'b@example.com', '--password' => 'x'])
            ->assertFailed();

        $this->post('/login', ['username' => 'cliadmin', 'password' => 'Cli Pass 1'])
            ->assertRedirect('/')
            ->assertSessionHas('role', 'admin');
    }

    private function tableQuery(array $columns, array $extra = []): array
    {
        $query = ['draw' => 1, 'start' => 0, 'length' => 10, 'search' => ['value' => '', 'regex' => 'false'],
            'order' => [['column' => 0, 'dir' => 'asc']]];
        foreach ($columns as $i => $column) {
            $query['columns'][$i] = ['data' => $column, 'name' => $column, 'searchable' => 'true', 'orderable' => 'true',
                'search' => ['value' => '', 'regex' => 'false']];
        }
        return array_merge($query, $extra);
    }
}
