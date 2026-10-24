<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Run against a database created by Polr 2.3.0 (baseline/fixtures/polr-2.3.0.sql:
 * made by the 2.3.0 setup wizard and populated through its web interface and
 * API). Loaded into its own database, not the one other tests refresh.
 */
class LegacyDatabaseTest extends TestCase
{
    private const DATABASE = 'polr_legacy_test';
    private const ALICE_KEY = '00000000000000000000000a11ce01';

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('DROP DATABASE IF EXISTS '.self::DATABASE);
        DB::statement('CREATE DATABASE '.self::DATABASE.' CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        config(['database.connections.legacy' => ['database' => self::DATABASE] + config('database.connections.mysql')]);

        $dump = file_get_contents(__DIR__.'/../../baseline/fixtures/polr-2.3.0.sql');
        $dump = preg_replace('/^(CREATE DATABASE|USE) .*$/m', '', $dump);
        DB::connection('legacy')->unprepared($dump);

        DB::setDefaultConnection('legacy');
        config(['database.default' => 'legacy']);
    }

    protected function tearDown(): void
    {
        DB::connection('mysql')->statement('DROP DATABASE IF EXISTS '.self::DATABASE);
        parent::tearDown();
    }

    public function test_no_migrations_to_run(): void
    {
        $this->artisan('migrate', ['--pretend' => true, '--force' => true])
            ->expectsOutputToContain('Nothing to migrate')
            ->assertSuccessful();
        $this->artisan('migrate:status')->assertSuccessful();
    }

    public function test_existing_admin_logs_in_with_polr_2_password_hash(): void
    {
        $this->post('/login', ['username' => 'polradmin', 'password' => 'AdminPass123'])
            ->assertRedirect('/')
            ->assertSessionHas('role', 'admin');
        $this->get('/admin')->assertOk()->assertSee('Welcome to your Polr Test dashboard!');
    }

    public function test_existing_links_redirect(): void
    {
        $this->get('/custom-one')->assertStatus(301)->assertRedirect('https://example.com/custom');
        $this->get('/1')->assertRedirect('https://example.com/page/2');
        $this->get('/5')->assertForbidden();
        $this->get('/5/5ec7')->assertRedirect('https://example.com/secret');
        $this->get('/disabled-link')->assertOk()->assertSee('this link has been disabled');

        $this->assertSame(11, DB::table('links')->where('short_url', 'custom-one')->value('clicks'));
    }

    public function test_new_links_continue_the_counter(): void
    {
        $this->post('/shorten', ['link-url' => 'https://example.com/new'])->assertSee('http://polr.test/d');
    }

    public function test_existing_api_key_works(): void
    {
        $this->get('/api/v2/action/lookup?'.http_build_query(['key' => self::ALICE_KEY, 'url_ending' => 'custom-one', 'response_type' => 'json']))
            ->assertOk()
            ->assertExactJson(['action' => 'lookup', 'result' => [
                'long_url' => 'https://example.com/custom',
                'created_at' => ['date' => '2020-02-01 12:06:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
                'clicks' => 10,
                'updated_at' => ['date' => '2020-02-01 12:06:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
            ]]);
    }

    public function test_stats_and_admin_tables_load(): void
    {
        config(['polr.setting_adv_analytics' => true]);
        $this->post('/login', ['username' => 'alice', 'password' => 'AlicePass123'])->assertRedirect('/');

        $this->get('/admin/stats/alice-custom?left_bound=2020-02-01&right_bound=2020-03-01')
            ->assertOk()
            ->assertSee('var countryData = JSON.parse(\'[{"label":"GB","clicks":4},{"label":"US","clicks":3},{"label":"SE","clicks":2}]\');', false);

        $this->get('/logout');
        $this->post('/login', ['username' => 'polradmin', 'password' => 'AdminPass123']);
        $this->get('/api/v2/admin/get_admin_links?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 18);
        $this->get('/api/v2/admin/get_admin_users?draw=1&start=0&length=10')
            ->assertOk()
            ->assertJsonPath('recordsTotal', 6);
    }
}
