<?php

namespace Tests;

use App\Factories\LinkFactory;
use App\Factories\UserFactory;
use App\Helpers\UserHelper;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

abstract class TestCase extends BaseTestCase
{
    protected function createUser(string $username, array $options = []): User
    {
        $options += [
            'email' => "{$username}@example.com",
            'password' => 'secret-password',
            'active' => 1,
            'api_key' => false,
            'api_active' => 0,
            'role' => false,
        ];

        return UserFactory::createUser(
            $username, $options['email'], $options['password'], $options['active'], '127.0.0.1',
            $options['api_key'], $options['api_active'], $options['role']
        );
    }

    protected function createAdmin(string $username = 'admin', array $options = []): User
    {
        return $this->createUser($username, $options + ['role' => UserHelper::$USER_ROLES['admin']]);
    }

    protected function createLink(string $longUrl, array $options = []): Link
    {
        $options += ['secret' => false, 'ending' => null, 'creator' => false, 'api' => false];

        return LinkFactory::createLink(
            $longUrl, $options['secret'], $options['ending'], '127.0.0.1', $options['creator'], true, $options['api']
        );
    }

    /** Act as a logged-in user (Polr keeps the login in the session). */
    protected function actingAsPolrUser(User $user): static
    {
        return $this->withSession(['username' => $user->username, 'role' => $user->role]);
    }

    /**
     * Re-register routes/web.php, e.g. after changing a setting that decides
     * which routes exist (POLR_ALLOW_ACCT_CREATION).
     */
    protected function reloadRoutes(): void
    {
        $router = $this->app['router'];
        $router->setRoutes(new RouteCollection);
        Route::middleware('web')->group(base_path('routes/web.php'));
        $router->getRoutes()->refreshNameLookups();
        $router->getRoutes()->refreshActionLookups();
    }

    /** Make the next requests go through CSRF checks (Laravel skips them in unit tests). */
    protected function enableCsrfProtection(): void
    {
        $this->app['env'] = 'local';
    }

    /** Mails sent through the array mailer, as Symfony Email objects. */
    protected function sentMails(): array
    {
        return $this->app['mail.manager']->mailer('array')->getSymfonyTransport()->messages()
            ->map(fn ($sent) => $sent->getOriginalMessage())
            ->values()
            ->all();
    }
}
