<?php
namespace App\Helpers;
use App\Models\Click;
use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClickHelper {
    static private function getCountry($ip) {
        // A GeoIP problem (e.g. a corrupt or unreadable database file, which
        // torann/geoip does not always catch) must never break a redirect:
        // record the click without a country and log a warning, at most once
        // per request.
        try {
            $country_iso = geoip()->getLocation($ip)->iso_code;
        }
        catch (\Throwable $e) {
            $request = request();
            if (! $request->attributes->get('polr_geoip_failure_logged')) {
                $request->attributes->set('polr_geoip_failure_logged', true);
                Log::warning('GeoIP lookup failed; recording click without a country.', ['exception' => $e]);
            }
            return null;
        }
        return $country_iso;
    }

    static private function getHost($url) {
        // Return host given URL; NULL if host is
        // not found.
        return parse_url((string) $url, PHP_URL_HOST);
    }

    static public function recordClick(Link $link, Request $request) {
        /**
         * Given a Link model instance and Request object, process post click operations.
         * @param Link model instance $link
         * @return boolean
         */

        $ip = $request->ip();
        $referer = $request->server('HTTP_REFERER');

        $click = new Click;
        $click->link_id = $link->id;
        $click->ip = $ip;
        $click->country = self::getCountry($ip);
        $click->referer = $referer;
        $click->referer_host = ClickHelper::getHost($referer);
        $click->user_agent = $request->server('HTTP_USER_AGENT');
        $click->save();

        return true;
    }
}
