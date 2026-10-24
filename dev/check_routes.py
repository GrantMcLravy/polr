"""Compare `php artisan route:list --json` with Polr's route inventory.

  php artisan route:list --json > routes.json
  python dev/check_routes.py routes.json

The inventory is the one in MODERNIZATION.md: every Polr 2.x route keeps its
path, methods, name and controller action. /signup is only present when
POLR_ALLOW_ACCT_CREATION is on (it is in the baseline .env).
"""
import json
import sys

CTRL = "App\\Http\\Controllers\\"
API_MW = "App\\Http\\Middleware\\ApiMiddleware"
GET, POST, BOTH = ("GET",), ("POST",), ("GET", "POST")

EXPECTED = {
    (GET, "/"): ("index", "IndexController@showIndexPage"),
    (GET, "/logout"): ("logout", "UserController@performLogoutUser"),
    (GET, "/login"): ("login", "UserController@displayLoginPage"),
    (POST, "/login"): ("plogin", "UserController@performLogin"),
    (GET, "/about-polr"): ("about", "StaticPageController@displayAbout"),
    (GET, "/signup"): ("signup", "UserController@displaySignupPage"),
    (POST, "/signup"): ("psignup", "UserController@performSignup"),
    (GET, "/lost_password"): ("lost_password", "UserController@displayLostPasswordPage"),
    (POST, "/lost_password"): ("plost_password", "UserController@performSendPasswordResetCode"),
    (GET, "/activate/{username}/{recovery_key}"): ("activate", "UserController@performActivation"),
    (GET, "/reset_password/{username}/{recovery_key}"): ("reset_password", "UserController@performPasswordReset"),
    (POST, "/reset_password/{username}/{recovery_key}"): ("preset_password", "UserController@performPasswordReset"),
    (GET, "/admin"): ("admin", "AdminController@displayAdminPage"),
    (POST, "/admin/action/change_password"): ("change_password", "AdminController@changePassword"),
    (GET, "/admin/stats/{short_url}"): (None, "StatsController@displayStats"),
    (GET, "/setup"): ("setup", "SetupController@displaySetupPage"),
    (POST, "/setup"): ("psetup", "SetupController@performSetup"),
    (GET, "/setup/finish"): ("setup_finish", "SetupController@finishSetup"),
    (POST, "/shorten"): ("pshorten", "LinkController@performShorten"),
    (GET, "/{short_url}"): (None, "LinkController@performRedirect"),
    (GET, "/{short_url}/{secret_key}"): (None, "LinkController@performRedirect"),
    (BOTH, "/api/v2/action/shorten"): ("api_shorten_url", "Api\\ApiLinkController@shortenLink"),
    (POST, "/api/v2/action/shorten_bulk"): ("api_shorten_url_bulk", "Api\\ApiLinkController@shortenLinksBulk"),
    (BOTH, "/api/v2/action/lookup"): ("api_lookup_url", "Api\\ApiLinkController@lookupLink"),
    (BOTH, "/api/v2/data/link"): ("api_link_analytics", "Api\\ApiAnalyticsController@lookupLinkStats"),
}
for path, name, method in [
    ("link_avail_check", "api_link_check", "checkLinkAvailability"),
    ("admin/toggle_api_active", "api_toggle_api_active", "toggleAPIActive"),
    ("admin/generate_new_api_key", "api_generate_new_api_key", "generateNewAPIKey"),
    ("admin/edit_api_quota", "api_edit_quota", "editAPIQuota"),
    ("admin/toggle_user_active", "api_toggle_user_active", "toggleUserActive"),
    ("admin/change_user_role", "api_change_user_role", "changeUserRole"),
    ("admin/add_new_user", "api_add_new_user", "addNewUser"),
    ("admin/delete_user", "api_delete_user", "deleteUser"),
    ("admin/toggle_link", "api_toggle_link", "toggleLink"),
    ("admin/delete_link", "api_delete_link", "deleteLink"),
    ("admin/edit_link_long_url", "api_edit_link_long_url", "editLinkLongUrl"),
]:
    EXPECTED[(POST, "/api/v2/" + path)] = (name, "AjaxController@" + method)
for path, name, method in [
    ("admin/get_admin_users", "api_get_admin_users", "paginateAdminUsers"),
    ("admin/get_admin_links", "api_get_admin_links", "paginateAdminLinks"),
    ("admin/get_user_links", "api_get_user_links", "paginateUserLinks"),
]:
    EXPECTED[(GET, "/api/v2/" + path)] = (name, "AdminPaginationController@" + method)

CATCH_ALLS = {"/{short_url}", "/{short_url}/{secret_key}"}


def main(path):
    with open(path, encoding="utf-8") as f:
        routes = json.load(f)
    got, order = {}, []
    for r in routes:
        methods = tuple(m for m in r["method"].split("|") if m != "HEAD")
        uri = "/" + r["uri"].lstrip("/")
        got[(methods, uri)] = (r["name"], r["action"].replace(CTRL, ""), r["middleware"])
        order.append(uri)

    problems = []
    for key, (name, action) in EXPECTED.items():
        if key not in got:
            problems.append(f"missing  {key}")
            continue
        gname, gaction, middleware = got[key]
        if (gname, gaction) != (name, action):
            problems.append(f"differs  {key}: {gname} {gaction} (expected {name} {action})")
        public_api = key[1].startswith(("/api/v2/action/", "/api/v2/data/"))
        want = ["web", API_MW] if public_api else ["web"]
        if middleware != want:
            problems.append(f"middleware {key}: {middleware} (expected {want})")
    for key in got:
        if key not in EXPECTED:
            problems.append(f"extra    {key}: {got[key]}")

    # route:list sorts by URI, so check registration order via the route file
    # instead: the catch-alls must be the last two routes registered.
    with open("routes/web.php", encoding="utf-8") as f:
        lines = [l.strip() for l in f if l.strip().startswith("Route::")]
    last_two = [l.split("'")[1] for l in lines[-2:]]
    if set(last_two) != CATCH_ALLS:
        problems.append(f"order    last registered routes are {last_two}, not the short-link catch-alls")

    for p in problems:
        print(p)
    print(f"{len(got)} routes, {len(EXPECTED)} expected: {'OK' if not problems else 'MISMATCH'}")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1]))
