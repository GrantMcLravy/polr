<?php
namespace App\Http\Controllers;
use Mail;
use Hash;
use App\Models\User;
use Illuminate\Http\Request;

use App\Helpers\CryptoHelper;
use App\Helpers\UserHelper;

use App\Factories\UserFactory;

class UserController extends Controller {
    /**
     * Show pages related to the user control panel.
     *
     * @return Response
     */
    public function displayLoginPage(Request $request) {
        return view('login');
    }

    public function displaySignupPage(Request $request) {
        return view('signup');
    }

    public function displayLostPasswordPage(Request $request) {
        return view('lost_password');
    }

    public function performLogoutUser(Request $request) {
        $request->session()->forget('username');
        $request->session()->forget('role');
        return redirect()->route('index');
    }

    public function performLogin(Request $request) {
        $username = $request->input('username');
        $password = $request->input('password');

        $credentials_valid = UserHelper::checkCredentials($username, $password);

        if ($credentials_valid != false) {
            // log user in
            $role = $credentials_valid['role'];
            $request->session()->put('username', $username);
            $request->session()->put('role', $role);

            return redirect()->route('index');
        }
        else {
            return redirect('login')->with('error', 'Invalid password or inactivated account. Try again.');
        }
    }

    public function performSignup(Request $request) {
        if (config('polr.polr_allow_acct_creation') == false) {
            return redirect(route('index'))->with('error', 'Sorry, but registration is disabled.');
        }

        if (config('polr.polr_acct_creation_recaptcha')) {
            // Verify reCAPTCHA if setting is enabled
            $gRecaptchaResponse = $request->input('g-recaptcha-response');

            $recaptcha = new \ReCaptcha\ReCaptcha(config('polr.polr_recaptcha_secret_key'));
            $recaptcha_resp = $recaptcha->verify($gRecaptchaResponse, $request->ip());

            if (!$recaptcha_resp->isSuccess()) {
                return redirect(route('signup'))->with('error', 'You must complete the reCAPTCHA to register.');
            }
        }

        // Validate signup form data
        $this->validate($request, [
            'username' => 'required|alpha_dash',
            'password' => 'required',
            'email' => 'required|email'
        ]);

        $username = $request->input('username');
        $password = $request->input('password');
        $email = $request->input('email');

        if (config('polr.setting_restrict_email_domain')) {
            $email_domain = explode('@', $email)[1];
            $permitted_email_domains = explode(',', (string) config('polr.setting_allowed_email_domains'));

            if (!in_array($email_domain, $permitted_email_domains)) {
                return redirect(route('signup'))->with('error', 'Sorry, your email\'s domain is not permitted to create new accounts.');
            }
        }

        $ip = $request->ip();

        $user_exists = UserHelper::userExists($username);
        $email_exists = UserHelper::emailExists($email);

        if ($user_exists || $email_exists) {
            // if user or email email
            return redirect(route('signup'))->with('error', 'Sorry, your email or username already exists. Try again.');
        }

        $acct_activation_needed = config('polr.polr_acct_activation');

        if ($acct_activation_needed == false) {
            // if no activation is necessary
            $active = 1;
            $response = redirect(route('login'))->with('success', 'Thanks for signing up! You may now log in.');
        }
        else {
            // email activation is necessary
            $response = redirect(route('login'))->with('success', 'Thanks for signing up! Please confirm your email to continue.');
            $active = 0;
        }

        $api_active = false;
        $api_key = null;

        if (config('polr.setting_auto_api')) {
            // if automatic API key assignment is on
            $api_active = 1;
            $api_key = CryptoHelper::generateRandomHex(config('polr.api_key_length'));
        }

        $user = UserFactory::createUser($username, $email, $password, $active, $ip, $api_key, $api_active);

        if ($acct_activation_needed) {
            Mail::send('emails.activation', [
                'username' => $username, 'recovery_key' => $user->recovery_key, 'ip' => $ip
            ], function ($m) use ($user) {
                $m->from(config('mail.from.address'), config('mail.from.name'));

                $m->to($user->email, $user->username)->subject(config('polr.app_name') . ' account activation');
            });
        }

        return $response;
    }

    public function performSendPasswordResetCode(Request $request) {
        if (!config('polr.setting_password_recov')) {
            return redirect(route('index'))->with('error', 'Password recovery is disabled.');
        }

        $email = $request->input('email');
        $ip = $request->ip();
        $user = UserHelper::getUserByEmail($email);

        if (!$user) {
            return redirect(route('lost_password'))->with('error', 'Email is not associated with a user.');
        }

        $recovery_key = UserHelper::resetRecoveryKey($user->username);

        Mail::send('emails.lost_password', [
            'username' => $user->username, 'recovery_key' => $recovery_key, 'ip' => $ip
        ], function ($m) use ($user) {
            $m->from(config('mail.from.address'), config('mail.from.name'));

            $m->to($user->email, $user->username)->subject(config('polr.app_name') . ' Password Reset');
        });

        return redirect(route('index'))->with('success', 'Password reset email sent. Check your inbox for details.');
    }

    public function performActivation(Request $request, $username, $recovery_key) {
        $user = UserHelper::getUserByUsername($username, true);

        if (UserHelper::userResetKeyCorrect($username, $recovery_key, true)) {
            // Key is correct
            // Activate account and reset recovery key
            $user->active = 1;
            $user->save();

            UserHelper::resetRecoveryKey($username);
            return redirect(route('login'))->with('success', 'Account activated. You may now login.');
        }
        else {
            return redirect(route('index'))->with('error', 'Username or activation key incorrect.');
        }
    }

    public function performPasswordReset(Request $request, $username, $recovery_key) {
        $new_password = $request->input('new_password');
        $user = UserHelper::getUserByUsername($username);

        if (UserHelper::userResetKeyCorrect($username, $recovery_key)) {
            if (!$new_password) {
                return view('reset_password');
            }

            // Key is correct
            // Reset password
            $user->password = Hash::make($new_password);
            $user->save();

            UserHelper::resetRecoveryKey($username);
            return redirect(route('login'))->with('success', 'Password reset. You may now login.');
        }
        else {
            return redirect(route('index'))->with('error', 'Username or reset key incorrect.');
        }

    }

}
