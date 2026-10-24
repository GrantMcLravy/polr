<?php

namespace App\Console\Commands;

use App\Factories\UserFactory;
use App\Helpers\UserHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Create an active administrator account from the command line, e.g. for
 * installs that are configured through .env/Docker rather than the web setup
 * wizard. Creates the account the same way the wizard creates the first admin.
 */
class CreateAdmin extends Command
{
    protected $signature = 'polr:create-admin
                            {username : Login name (letters, numbers, dashes and underscores)}
                            {email : Email address}
                            {--password= : Password (prompted for when omitted; note that options can be visible in the process list and shell history)}';

    protected $description = 'Create an active Polr administrator account';

    public function handle()
    {
        $username = $this->argument('username');
        $email = $this->argument('email');
        $password = $this->option('password');

        if ($password === null) {
            if (! $this->input->isInteractive()) {
                $this->error('No password given: use --password or run interactively.');
                return self::FAILURE;
            }
            $password = $this->secret('Password');
            if ($password !== $this->secret('Repeat password')) {
                $this->error('The passwords do not match.');
                return self::FAILURE;
            }
        }

        // Same rules as the signup form.
        $validator = Validator::make(
            ['username' => $username, 'password' => $password, 'email' => $email],
            ['username' => 'required|alpha_dash', 'password' => 'required', 'email' => 'required|email']
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }
            return self::FAILURE;
        }

        if (UserHelper::userExists($username) || UserHelper::emailExists($email)) {
            $this->error('A user with this username or email already exists.');
            return self::FAILURE;
        }

        UserFactory::createUser($username, $email, $password, 1, '127.0.0.1', false, 0, UserHelper::$USER_ROLES['admin']);

        $this->info("Administrator {$username} created.");
        return self::SUCCESS;
    }
}
