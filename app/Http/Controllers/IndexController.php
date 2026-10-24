<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Helpers\CryptoHelper;

class IndexController extends Controller {
    /**
     * Show the index page.
     *
     * @return Response
     */
    public function showIndexPage(Request $request) {
        if (config('polr.setup_ran') != true) {
            return redirect(route('setup'));
        }

        if (!config('polr.setting_public_interface') && !self::isLoggedIn()) {
            if (config('polr.setting_index_redirect')) {
                return redirect()->to(config('polr.setting_index_redirect'));
            }
            else {
                return redirect()->to(route('login'));
            }
        }

        return view('index', ['large' => true]);
    }
}
