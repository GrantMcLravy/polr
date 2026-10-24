"""Drive the original Polr through its setup wizard and create sample data.

Usage:
  python seed.py setup-quirks BASE_URL   # wizard with awkward values (.env fixture only)
  python seed.py setup        BASE_URL   # real setup (creates DB + admin)
  python seed.py populate     BASE_URL   # users, links, API keys, clicks

All data is created through HTTP, exactly as a user would. Values that end up
random (API keys, secret keys, timestamps) are overwritten afterwards by
fixtures/normalize.sql so the dump is deterministic.
"""
import json
import sys

from polrclient import Client

ADMIN = ("polradmin", "admin@polr.test", "AdminPass123")

SETUP_FORM = {
    "db:host": "mysql",
    "db:port": "3306",
    "db:username": "polr",
    "db:password": "polrpw",
    "db:name": "polr",
    "app:name": "Polr Baseline",
    "app:protocol": "http://",
    "app:external_url": "polr.test",
    "setting:adv_analytics": "false",
    "maxmind:license_key": "",
    "setting:shorten_permission": "false",
    "setting:public_interface": "true",
    "setting:redirect_404": "false",
    "setting:index_redirect": "https://example.com/home",
    "setting:pseudor_ending": "false",
    "setting:base": "32",
    "acct:username": ADMIN[0],
    "acct:email": ADMIN[1],
    "acct:password": ADMIN[2],
    "app:smtp_server": "mailpit",
    "app:smtp_port": "1025",
    "app:smtp_username": "",
    "app:smtp_password": "",
    "app:smtp_from": "noreply@polr.test",
    "app:smtp_from_name": "Polr Baseline",
    "setting:anon_api": "true",
    "setting:anon_api_quota": "30",
    "setting:auto_api_key": "false",
    "setting:registration_permission": "no-verification",
    "setting:restrict_email_domain": "false",
    "setting:allowed_email_domains": "",
    "setting:password_recovery": "true",
    "setting:acct_registration_recaptcha": "false",
    "setting:recaptcha_site_key": "",
    "setting:recaptcha_secret_key": "",
    "app:stylesheet": "",
}

# Values chosen to exercise .env quoting/escaping: quotes, ampersands, hash,
# dollar signs, backslashes, spaces, angle brackets and non-ASCII.
QUIRK_FORM = dict(SETUP_FORM, **{
    "db:password": 'p&ss"w#rd $HOME ${APP_KEY} \\back',
    "db:username": "user with space",
    "app:name": 'Polr "Quoted" & <Tagged> #1 $dollar café',
    "app:external_url": "short.example.com:8443",
    "app:smtp_password": "smtp&pw\"#$",
    "app:smtp_from_name": "Polr's Mailer",
    "setting:index_redirect": "https://example.com/?a=1&b=2#frag",
    "setting:allowed_email_domains": "example.com, example.org",
    "setting:registration_permission": "email",
    "setting:restrict_email_domain": "true",
    "setting:acct_registration_recaptcha": "true",
    "setting:recaptcha_site_key": "site-key-123",
    "setting:recaptcha_secret_key": "secret&key\"456",
    "maxmind:license_key": "LICENSE_KEY_123",
    "setting:adv_analytics": "true",
    "setting:pseudor_ending": "true",
    "setting:base": "62",
    "app:stylesheet": "//maxcdn.bootstrapcdn.com/bootswatch/3.3.7/cyborg/bootstrap.min.css",
})


def expect(resp, status, what):
    if resp.status != status:
        raise SystemExit(f"{what}: expected {status}, got {resp.status}\n{resp.body[:2000]}")
    return resp


def run_setup(base, form, finish=True):
    c = Client(base)
    token = c.csrf("/setup")
    r = expect(c.post("/setup", dict(form, _token=token)), 302, "POST /setup")
    print("setup ->", r.header("Location"))
    if finish:
        r = c.get("/setup/finish")
        if r.status != 200 or "Polr setup is complete" not in r.body:
            raise SystemExit(f"/setup/finish failed: {r.status}\n{r.body[:3000]}")
        print("setup finished")


def login(base, username, password, forwarded_for=None):
    c = Client(base, forwarded_for=forwarded_for)
    token = c.csrf("/login")
    r = expect(c.post("/login", {"username": username, "password": password, "_token": token}),
               302, f"login {username}")
    if r.header("Location").rstrip("/").endswith("/login"):
        raise SystemExit(f"login failed for {username}")
    c.token = c.csrf("/admin")
    return c


def ajax(c, path, data):
    r = c.post("/api/v2/" + path, data, headers={"X-CSRF-TOKEN": c.token})
    expect(r, 200, path)
    return r.body


def shorten_web(c, url, custom="", secret=False):
    token = c.csrf("/")
    data = {"link-url": url, "custom-ending": custom, "_token": token}
    if secret:
        data["options"] = "s"
    r = expect(c.post("/shorten", data), 200, f"shorten {url}")
    return r


def api(c, path, params, method="GET"):
    if method == "GET":
        r = c.get("/api/v2/" + path, params)
    else:
        r = c.post("/api/v2/" + path, params)
    expect(r, 200, f"api {path} {params}")
    return r.body


def user_id(admin, username):
    r = admin.get("/api/v2/admin/get_admin_users", {
        "draw": 1, "start": 0, "length": 100,
        "search[value]": username, "search[regex]": "false",
        "columns[0][data]": "username", "columns[0][name]": "username",
        "columns[0][searchable]": "true", "columns[0][orderable]": "true",
        "columns[0][search][value]": "", "columns[0][search][regex]": "false",
    })
    for row in json.loads(r.body)["data"]:
        if row["username"] == username:
            return row["id"]
    raise SystemExit(f"user {username} not found")


def populate(base):
    admin = login(base, ADMIN[0], ADMIN[2])

    # Users created by an admin
    ajax(admin, "admin/add_new_user", {"username": "alice", "user_password": "AlicePass123",
                                       "user_email": "alice@example.com", "user_role": ""})
    ajax(admin, "admin/add_new_user", {"username": "bob", "user_password": "BobPass123",
                                       "user_email": "bob@example.com", "user_role": "admin"})
    ajax(admin, "admin/add_new_user", {"username": "quota", "user_password": "QuotaPass123",
                                       "user_email": "quota@example.com", "user_role": ""})
    ajax(admin, "admin/add_new_user", {"username": "dave", "user_password": "DavePass123",
                                       "user_email": "dave@example.com", "user_role": ""})

    # Self-service signup
    anon = Client(base)
    token = anon.csrf("/signup")
    expect(anon.post("/signup", {"username": "carol", "password": "CarolPass123",
                                 "email": "carol@example.org", "_token": token}), 302, "signup carol")

    # API access for alice (and the quota user); keys are fixed by normalize.sql
    for name in ("alice", "quota", ADMIN[0]):
        uid = user_id(admin, name)
        ajax(admin, "admin/toggle_api_active", {"user_id": uid})
        ajax(admin, "admin/generate_new_api_key", {"user_id": uid})
    ajax(admin, "admin/edit_api_quota", {"user_id": user_id(admin, "quota"), "new_quota": "1"})

    # Deactivate dave
    ajax(admin, "admin/toggle_user_active", {"user_id": user_id(admin, "dave")})

    # Links: anonymous web form (counter endings)
    for i in range(1, 6):
        shorten_web(anon, f"https://example.com/page/{i}")
    shorten_web(anon, "https://example.com/custom", custom="custom-one")
    shorten_web(anon, "https://example.com/secret", secret=True)
    shorten_web(anon, "https://example.com/to-disable", custom="disabled-link")
    shorten_web(anon, "https://example.com/to-delete", custom="delete-me")

    # Links: admin via web form
    shorten_web(admin, "https://example.net/admin-link")
    shorten_web(admin, "https://example.net/admin-custom", custom="admin-custom")

    # Links: alice via web form and API (keys normalized later; read live key)
    alice = login(base, "alice", "AlicePass123")
    shorten_web(alice, "https://example.org/alice-web")
    # alice cannot list users; read her current key from the admin listing
    rows = json.loads(admin.get("/api/v2/admin/get_admin_users", {
        "draw": 1, "start": 0, "length": 100}).body)["data"]
    keys = {r["username"]: r["api_key"] for r in rows}
    alice_key = keys["alice"]
    api(Client(base), "action/shorten", {"key": alice_key, "url": "https://example.org/alice-api-1"})
    api(Client(base), "action/shorten", {"key": alice_key, "url": "https://example.org/alice-api-2",
                                         "response_type": "json"}, method="POST")
    api(Client(base), "action/shorten", {"key": alice_key, "url": "https://example.org/alice-api-custom",
                                         "custom_ending": "alice-custom", "response_type": "json"})
    api(Client(base), "action/shorten", {"key": alice_key, "url": "https://example.org/alice-api-secret",
                                         "is_secret": "true", "response_type": "json"})
    api(Client(base), "action/shorten_bulk", {"key": alice_key, "response_type": "json", "data": json.dumps(
        {"links": [{"url": "https://example.org/bulk-1"},
                   {"url": "https://example.org/bulk-2", "custom_ending": "bulk-custom"}]})}, method="POST")

    # Links: anonymous API
    api(Client(base, forwarded_for="203.0.113.7"), "action/shorten",
        {"url": "https://example.com/anon-api"})

    # Disable a link, edit a long URL, delete a link
    ajax(admin, "admin/toggle_link", {"link_ending": "disabled-link"})
    ajax(admin, "admin/edit_link_long_url", {"link_ending": "admin-custom",
                                             "new_long_url": "https://example.net/admin-custom-edited"})
    ajax(admin, "admin/delete_link", {"link_ending": "delete-me"})

    # Clicks (advanced analytics is on; X-Forwarded-For sets the client IP).
    # Each visitor clicks a different number of times so per-country and
    # per-referer counts are distinct and their sort order is deterministic.
    visitors = [
        ("81.2.69.142", "https://news.example.com/article", 4),   # GB in GeoIP2-City-Test
        ("216.160.83.56", "https://www.google.com/search?q=x", 3),  # US
        ("89.160.20.112", None, 2),                                # SE
        ("10.1.2.3", "https://intranet.local/", 1),               # private -> no country
    ]
    for ending in ("custom-one", "1", "alice-custom"):
        for ip, ref, times in visitors:
            for _ in range(times):
                v = Client(base, forwarded_for=ip)
                r = v.get("/" + ending, headers={"Referer": ref} if ref else None)
                expect(r, 301, f"click {ending}")
    print("populated")


def main():
    cmd, base = sys.argv[1], sys.argv[2]
    if cmd == "setup-quirks":
        run_setup(base, QUIRK_FORM, finish=False)
    elif cmd == "setup":
        run_setup(base, SETUP_FORM)
    elif cmd == "populate":
        populate(base)
    else:
        raise SystemExit(__doc__)


if __name__ == "__main__":
    main()
