<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_logout(): void
    {
        $this->createUser('alice', ['password' => 'AlicePass123']);

        $this->post('/login', ['username' => 'alice', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Invalid password or inactivated account. Try again.');

        $this->post('/login', ['username' => 'alice', 'password' => 'AlicePass123'])
            ->assertRedirect('/')
            ->assertSessionHas('username', 'alice')
            ->assertSessionHas('role', '');

        $this->get('/admin')->assertOk()->assertSee('Welcome to your Polr Test dashboard!');

        $this->get('/logout')->assertRedirect('/')->assertSessionMissing('username');
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_password_whitespace_is_significant(): void
    {
        // Polr 2.x did not trim input; neither may the port (no TrimStrings).
        $this->createUser('spacey', ['password' => ' padded password ']);

        $this->post('/login', ['username' => 'spacey', 'password' => 'padded password'])->assertRedirect('/login');
        $this->post('/login', ['username' => 'spacey', 'password' => ' padded password '])->assertRedirect('/');
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $this->createUser('dave', ['password' => 'DavePass123', 'active' => 0]);

        $this->post('/login', ['username' => 'dave', 'password' => 'DavePass123'])->assertRedirect('/login');
    }

    public function test_bcrypt_hash_from_polr_2_is_accepted(): void
    {
        // Created by Polr 2.3.0 (cost 10) for the password "AdminPass123"; see
        // baseline/fixtures/polr-2.3.0.sql.
        $hash = $this->polr2Hash('polradmin');
        $this->assertStringStartsWith('$2y$10$', $hash);
        $user = $this->createAdmin('polradmin');
        $user->password = $hash;
        $user->save();

        $this->post('/login', ['username' => 'polradmin', 'password' => 'AdminPass123'])
            ->assertRedirect('/')
            ->assertSessionHas('role', 'admin');
        $this->assertSame($hash, $user->fresh()->password, 'hash is not rewritten on login');
    }

    public function test_non_bcrypt_hash_fails_login_without_error(): void
    {
        $user = $this->createUser('legacy');
        $user->password = md5('secret');
        $user->save();

        $this->post('/login', ['username' => 'legacy', 'password' => 'secret'])->assertRedirect('/login');
    }

    public function test_change_password(): void
    {
        $user = $this->createUser('alice', ['password' => 'old-password']);

        $this->actingAsPolrUser($user)
            ->post('/admin/action/change_password', ['current_password' => 'wrong', 'new_password' => 'new-password'])
            ->assertRedirect('/admin')
            ->assertSessionHas('error', 'Current password invalid. Try again.');

        $this->actingAsPolrUser($user)
            ->post('/admin/action/change_password', ['current_password' => 'old-password', 'new_password' => 'new-password'])
            ->assertRedirect('/admin')
            ->assertSessionHas('success', 'Password changed successfully.');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_signup_without_activation(): void
    {
        $this->post('/signup', ['username' => 'frank', 'password' => 'FrankPass1', 'email' => 'frank@example.com'])
            ->assertRedirect('/login')
            ->assertSessionHas('success', 'Thanks for signing up! You may now log in.');

        $user = User::where('username', 'frank')->first();
        $this->assertSame('1', (string) $user->active);
        $this->assertNull($user->api_key);
        $this->assertSame([], $this->sentMails());

        $this->post('/signup', ['username' => 'frank', 'password' => 'x', 'email' => 'other@example.com'])
            ->assertSessionHas('error', 'Sorry, your email or username already exists. Try again.');
        $this->from('/signup')->post('/signup', ['username' => 'gina smith', 'password' => 'x', 'email' => 'gina@example.com'])
            ->assertSessionHasErrors('username');
    }

    public function test_signup_with_email_activation(): void
    {
        config(['polr.polr_acct_activation' => true]);

        $this->post('/signup', ['username' => 'kate', 'password' => 'KatePass1', 'email' => 'kate@example.com'])
            ->assertSessionHas('success', 'Thanks for signing up! Please confirm your email to continue.');
        $this->post('/login', ['username' => 'kate', 'password' => 'KatePass1'])->assertRedirect('/login');

        $mails = $this->sentMails();
        $this->assertCount(1, $mails);
        $this->assertSame('Polr Test account activation', $mails[0]->getSubject());
        $this->assertSame('noreply@polr.test', $mails[0]->getFrom()[0]->getAddress());
        $this->assertSame('kate@example.com', $mails[0]->getTo()[0]->getAddress());
        $this->assertMatchesRegularExpression('#http://polr.test/activate/kate/([0-9a-f]{100})#', $mails[0]->getHtmlBody());
        preg_match('#/activate/kate/([0-9a-f]{100})#', $mails[0]->getHtmlBody(), $m);

        $this->get("/activate/kate/{$m[1]}")->assertRedirect('/login')->assertSessionHas('success', 'Account activated. You may now login.');
        $this->post('/login', ['username' => 'kate', 'password' => 'KatePass1'])->assertRedirect('/');
    }

    public function test_signup_email_domain_restriction(): void
    {
        config(['polr.setting_restrict_email_domain' => true, 'polr.setting_allowed_email_domains' => 'example.org,example.com']);

        $this->post('/signup', ['username' => 'jack', 'password' => 'x', 'email' => 'jack@other.net'])
            ->assertSessionHas('error', "Sorry, your email's domain is not permitted to create new accounts.");
        $this->post('/signup', ['username' => 'ivy', 'password' => 'x', 'email' => 'ivy@example.com'])
            ->assertSessionHas('success');
    }

    public function test_signup_auto_api_key(): void
    {
        config(['polr.setting_auto_api' => true]);

        $this->post('/signup', ['username' => 'hank', 'password' => 'x', 'email' => 'hank@example.com']);

        $user = User::where('username', 'hank')->first();
        $this->assertSame(1, $user->api_active);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{30}$/', $user->api_key);
    }

    public function test_signup_requires_recaptcha_when_enabled(): void
    {
        config(['polr.polr_acct_creation_recaptcha' => true, 'polr.polr_recaptcha_site_key' => 'site-key', 'polr.polr_recaptcha_secret_key' => 'secret-key']);

        $this->get('/signup')->assertSee('data-sitekey="site-key"', false);
        // An empty response is rejected locally by google/recaptcha, without a network call.
        $this->post('/signup', ['username' => 'leo', 'password' => 'x', 'email' => 'leo@example.com'])
            ->assertRedirect('/signup')
            ->assertSessionHas('error', 'You must complete the reCAPTCHA to register.');
        $this->assertNull(User::where('username', 'leo')->first());
    }

    public function test_signup_routes_absent_when_disabled(): void
    {
        config(['polr.polr_allow_acct_creation' => false]);
        $this->reloadRoutes();

        // As in Polr 2.x, /signup then falls through to the short-link route.
        $this->get('/signup')->assertNotFound();
        $this->post('/signup', ['username' => 'x', 'password' => 'x', 'email' => 'x@example.com'])->assertStatus(405);
        $this->get('/login')->assertDontSee('Register');
    }

    public function test_password_reset(): void
    {
        $this->createUser('alice', ['password' => 'old-password']);

        $this->post('/lost_password', ['email' => 'nobody@example.com'])
            ->assertRedirect('/lost_password')
            ->assertSessionHas('error', 'Email is not associated with a user.');

        $this->post('/lost_password', ['email' => 'alice@example.com'])
            ->assertRedirect('/')
            ->assertSessionHas('success', 'Password reset email sent. Check your inbox for details.');

        $mails = $this->sentMails();
        $this->assertSame('Polr Test Password Reset', $mails[0]->getSubject());
        preg_match('#http://polr.test(/reset_password/alice/[0-9a-f]{100})#', $mails[0]->getHtmlBody(), $m);
        $link = $m[1];

        $this->get($link)->assertOk()->assertSee('new_password');
        $this->post($link, ['new_password' => 'new-password'])->assertRedirect('/login');
        $this->post('/login', ['username' => 'alice', 'password' => 'new-password'])->assertRedirect('/');
        $this->get($link)->assertRedirect('/')->assertSessionHas('error', 'Username or reset key incorrect.');
    }

    public function test_password_reset_disabled(): void
    {
        config(['polr.setting_password_recov' => false]);

        $this->post('/lost_password', ['email' => 'alice@example.com'])
            ->assertSessionHas('error', 'Password recovery is disabled.');
    }

    private function polr2Hash(string $username): string
    {
        $dump = file_get_contents(__DIR__.'/../../baseline/fixtures/polr-2.3.0.sql');
        preg_match("/'{$username}','(\\$2y\\$[^']+)'/", $dump, $m);
        return $m[1];
    }
}
