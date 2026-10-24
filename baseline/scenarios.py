"""Record Polr's HTTP behavior against the 2.3.0 fixture database.

Runs a fixed list of scenario groups against a running Polr instance and writes
one normalized JSON file per group to OUT_DIR, plus the resulting database
state. The same script is pointed at the original app (to produce the golden
files in baseline/golden/) and at the ported app (to compare against them).

  python scenarios.py --base-url http://127.0.0.1:18080 --out golden \
      --app-exec "docker compose -f baseline/docker-compose.yml exec -T app" \
      --db-exec  "docker compose -f baseline/docker-compose.yml exec -T mysql" \
      --mail-url http://127.0.0.1:18025

Before every group the database is restored from fixtures/polr-2.3.0.sql and
.env is reset to fixtures/env/baseline.env plus the group's overrides.
"""
import argparse
import json
import os
import re
import shlex
import subprocess
import sys
import time
import urllib.parse
import urllib.request

from polrclient import Client

HERE = os.path.dirname(os.path.abspath(__file__))
DUMP = os.path.join(HERE, "fixtures", "polr-2.3.0.sql")
BASE_ENV = os.path.join(HERE, "fixtures", "env", "baseline.env")

ADMIN = ("polradmin", "AdminPass123")
ALICE = ("alice", "AlicePass123")
ADMIN_KEY = "0000000000000000000000000ad001"
ALICE_KEY = "00000000000000000000000a11ce01"
QUOTA_KEY = "00000000000000000000000000ff01"
SECRET_LINK = ("5", "5ec7")          # anonymous secret link
ALICE_SECRET_LINK = ("a", "a5ec")    # alice's secret API link
STATS_BOUNDS = {"left_bound": "2020-02-01 00:00:00", "right_bound": "2020-03-01 00:00:00"}

# Web-server level headers ("host" is echoed by PHP's built-in server)
DROP_HEADERS = {"date", "server", "x-powered-by", "connection", "keep-alive",
                "content-length", "transfer-encoding", "host"}
DATETIME_RE = re.compile(r"\b(?!2020-)\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?")
CSRF_RES = [
    re.compile(r"(name=['\"]_token['\"] value=['\"])[^'\"]+"),
    re.compile(r'(name="csrf-token" content=")[^"]+'),
]


# --------------------------------------------------------------------------
# Environment control
# --------------------------------------------------------------------------

class Env:
    def __init__(self, args):
        self.base_url = args.base_url
        self.app_exec = shlex.split(args.app_exec)
        self.db_exec = shlex.split(args.db_exec)
        self.app_dir = args.app_dir
        self.mail_url = args.mail_url.rstrip("/")
        self.db_user, self.db_pass, self.db_name = "root", "rootpw", "polr"
        with open(BASE_ENV, encoding="utf-8") as f:
            self.base_env = f.read()
        # Settings the instance under test needs on top of the fixture, e.g.
        # TRUSTED_PROXIES so the port honours X-Forwarded-For like the
        # baseline's Apache (mod_remoteip) did.
        self.extra_env = dict(kv.split("=", 1) for kv in (args.extra_env or []))

    def _run(self, cmd, stdin=None):
        p = subprocess.run(cmd, input=stdin, capture_output=True)
        if p.returncode != 0:
            raise RuntimeError(f"{cmd}: {p.stderr.decode(errors='replace')}")
        return p.stdout.decode("utf-8", errors="replace")

    def mysql(self, sql=None, stdin=None, args=()):
        cmd = self.db_exec + ["mysql", f"-u{self.db_user}", f"-p{self.db_pass}",
                              "--default-character-set=utf8", *args, self.db_name]
        if sql is not None:
            cmd += ["-e", sql]
        return self._run(cmd, stdin)

    def restore_db(self):
        with open(DUMP, "rb") as f:
            self._run(self.db_exec + ["mysql", f"-u{self.db_user}", f"-p{self.db_pass}"], f.read())

    def write_env(self, overrides):
        lines = self.base_env.splitlines()
        for key, value in {**self.extra_env, **overrides}.items():
            new = f"{key}={value}"
            for i, line in enumerate(lines):
                if line.startswith(key + "="):
                    lines[i] = new
                    break
            else:
                lines.append(new)
        content = "\n".join(lines) + "\n"
        self._run(self.app_exec + ["sh", "-c", f"cat > {self.app_dir}/.env"], content.encode())
        self.app_post_env_hook()

    def app_post_env_hook(self):
        # The original app reads .env on every request. A port that caches
        # config must clear it here; this is a no-op for the original.
        pass

    def clear_mail(self):
        req = urllib.request.Request(self.mail_url + "/api/v1/messages", method="DELETE")
        urllib.request.urlopen(req).read()

    def mails(self):
        """Return received mails, oldest first, as dicts with to/subject/text/html."""
        data = json.loads(urllib.request.urlopen(self.mail_url + "/api/v1/messages").read())
        out = []
        for m in reversed(data.get("messages", [])):
            full = json.loads(urllib.request.urlopen(
                f"{self.mail_url}/api/v1/message/{m['ID']}").read())
            out.append({
                "from": [full["From"]["Address"], full["From"]["Name"]],
                "to": [[t["Address"], t["Name"]] for t in full["To"]],
                "subject": full["Subject"],
                "html": full.get("HTML", ""),
                "text": full.get("Text", ""),
            })
        return out

    def db_state(self):
        """Table contents (minus columns that are random by design)."""
        state = {}
        queries = {
            "users": "SELECT id, username, email, ip, role, active, api_key, api_active, api_quota, "
                     "created_at, updated_at, deleted_at, LEFT(password, 7) AS password_prefix "
                     "FROM users ORDER BY id",
            "links": "SELECT id, short_url, long_url, long_url_hash, ip, creator, clicks, "
                     "CHAR_LENGTH(secret_key) AS secret_key_len, is_disabled, is_custom, is_api, "
                     "created_at, updated_at FROM links ORDER BY id",
            "clicks": "SELECT id, link_id, ip, country, referer, referer_host, user_agent, "
                      "created_at, updated_at FROM clicks ORDER BY id",
            "migrations": "SELECT migration, batch FROM migrations ORDER BY migration",
        }
        for table, sql in queries.items():
            out = self.mysql(sql, args=["--batch", "--raw"])
            rows = [line.split("\t") for line in out.splitlines()]
            header, body = rows[0], rows[1:]
            state[table] = [dict(zip(header, r)) for r in body]
        return state


# --------------------------------------------------------------------------
# Recording
# --------------------------------------------------------------------------

def normalize_text(text, masks=()):
    for rx in CSRF_RES:
        text = rx.sub(r"\1<CSRF>", text)
    for pattern, repl in masks:
        text = re.sub(pattern, repl, text)
    return DATETIME_RE.sub("<NOW>", text)


def normalize_headers(headers, masks=()):
    out = []
    for name, value in headers:
        lname = name.lower()
        if lname in DROP_HEADERS:
            continue
        if lname == "set-cookie":
            cookie_name, _, rest = value.partition("=")
            attrs = [a.strip() for a in rest.split(";")[1:]]
            attrs = [re.sub(r"(?i)^(expires|max-age)=.*", r"\1=<MASKED>", a) for a in attrs]
            value = "; ".join([f"{cookie_name}=<MASKED>"] + attrs)
        else:
            value = normalize_text(value, masks)
        out.append([name, value])
    return out


FIXED_API_KEYS = {"0000000000000000000000000ad001", "00000000000000000000000a11ce01",
                  "00000000000000000000000000ff01"}


def normalize_value(value, key=None):
    """Normalize recorded DB state: fresh timestamps and newly generated API keys."""
    if isinstance(value, dict):
        return {k: normalize_value(v, k) for k, v in value.items()}
    if isinstance(value, list):
        return [normalize_value(v) for v in value]
    if isinstance(value, str):
        if key == "api_key" and re.fullmatch(r"[0-9a-f]{30}", value) and value not in FIXED_API_KEYS:
            return "<API_KEY>"
        return DATETIME_RE.sub("<NOW>", value)
    return value


def try_json(body):
    try:
        return json.loads(body)
    except ValueError:
        return None


class Recorder:
    def __init__(self, env, group):
        self.env = env
        self.group = group
        self.steps = []

    def client(self, forwarded_for="198.51.100.20"):
        return Client(self.env.base_url, forwarded_for=forwarded_for)

    def do(self, name, client, method, path, params=None, data=None, headers=None,
           masks=(), csrf=None):
        """Perform a request and record it. csrf: 'form' adds _token, 'header' adds X-CSRF-TOKEN."""
        data = dict(data) if data is not None else None
        headers = dict(headers or {})
        if csrf == "form":
            data = dict(data or {}, _token=client.token)
        elif csrf == "header":
            headers["X-CSRF-TOKEN"] = client.token
        resp = client.request(method, path, params=params, data=data, headers=headers)
        # The app's own host/port differs between runs (baseline vs port)
        masks = [(re.escape(self.env.base_url), "<BASE>")] + list(masks)
        body = normalize_text(resp.body, masks)
        parsed = try_json(body)
        req_data = {k: ("<CSRF>" if k == "_token" else v) for k, v in (data or {}).items()}
        self.steps.append({
            "name": name,
            "request": {"method": method, "path": normalize_text(path, masks), "params": params,
                        "data": req_data or None,
                        "headers": {k: ("<CSRF>" if k == "X-CSRF-TOKEN" else v) for k, v in headers.items()} or None},
            "response": {
                "status": resp.status,
                "headers": normalize_headers(resp.headers, masks),
                "json": parsed if parsed is not None else None,
                "body": body if parsed is None else None,
            },
        })
        return resp

    def note(self, name, value):
        self.steps.append({"name": name, "value": normalize_value(value)})

    def mails(self, name, masks=()):
        mails = self.env.mails()
        for m in mails:
            m["html"] = normalize_text(m["html"], masks)
            m["text"] = normalize_text(m["text"], masks)
        self.steps.append({"name": name, "mails": mails})
        return mails


def prime(client, path="/"):
    """Load a page so the client has a session and CSRF token."""
    client.token = client.csrf(path)
    return client


def login(rec, who, label=None, forwarded_for="198.51.100.20"):
    c = rec.client(forwarded_for)
    prime(c, "/login")
    rec.do(label or f"login {who[0]}", c, "POST", "/login",
           data={"username": who[0], "password": who[1]}, csrf="form")
    prime(c, "/")
    return c


def datatable_params(columns, search="", order_col=0, order_dir="asc", start=0, length=10, draw=1):
    p = {"draw": draw, "start": start, "length": length,
         "search[value]": search, "search[regex]": "false",
         "order[0][column]": order_col, "order[0][dir]": order_dir}
    for i, (data, orderable, searchable) in enumerate(columns):
        p[f"columns[{i}][data]"] = data
        p[f"columns[{i}][name]"] = data
        p[f"columns[{i}][searchable]"] = "true" if searchable else "false"
        p[f"columns[{i}][orderable]"] = "true" if orderable else "false"
        p[f"columns[{i}][search][value]"] = ""
        p[f"columns[{i}][search][regex]"] = "false"
    return p


# Column definitions mirror public/js/AdminCtrl.js
ADMIN_USER_COLUMNS = [("username", 1, 1), ("email", 1, 1), ("created_at", 1, 1),
                      ("toggle_active", 0, 0), ("api_action", 0, 0),
                      ("change_role", 0, 0), ("delete", 0, 0)]
ADMIN_LINK_COLUMNS = [("short_url", 1, 1), ("long_url", 1, 1), ("clicks", 1, 1),
                      ("created_at", 1, 1), ("creator", 1, 1), ("disable", 0, 0), ("delete", 0, 0)]
USER_LINK_COLUMNS = [("short_url", 1, 1), ("long_url", 1, 1), ("clicks", 1, 1), ("created_at", 1, 1)]

SECRET_URL_MASK = (r"(http://polr\.test/[A-Za-z0-9_-]+/)[0-9a-f]{4}\b", r"\1<SECRET>")
API_KEY_MASK = (r"^[0-9a-f]{30}$", "<API_KEY>")
RECOVERY_MASK = (r"(/(?:activate|reset_password)/[^/\s'\"<]+/)[0-9a-f]{100}", r"\1<RECOVERY_KEY>")


# --------------------------------------------------------------------------
# Scenario groups
# --------------------------------------------------------------------------

def g_public_pages(rec):
    c = rec.client()
    for path in ["/", "/login", "/about-polr", "/lost_password", "/signup", "/admin",
                 "/logout", "/setup", "/setup/finish", "/admin/stats/custom-one"]:
        rec.do(f"GET {path}", c, "GET", path)
    rec.do("POST /setup after setup ran", prime(rec.client(), "/"), "POST", "/setup",
           data={"app:name": "x"}, csrf="form")


def g_redirects(rec):
    c = rec.client(forwarded_for="81.2.69.142")
    rec.do("counter link", c, "GET", "/0")
    rec.do("custom link with referer", c, "GET", "/custom-one",
           headers={"Referer": "https://ref.example.com/x?y=1"})
    rec.do("query string is not forwarded", c, "GET", "/custom-one", params={"utm": "1"})
    rec.do("secret link without key", c, "GET", f"/{SECRET_LINK[0]}")
    rec.do("secret link wrong key", c, "GET", f"/{SECRET_LINK[0]}/0000")
    rec.do("secret link correct key", c, "GET", f"/{SECRET_LINK[0]}/{SECRET_LINK[1]}")
    rec.do("non-secret link with extra segment", c, "GET", "/custom-one/abcd")
    rec.do("disabled link", c, "GET", "/disabled-link")
    rec.do("unknown link", c, "GET", "/does-not-exist")
    rec.do("unknown link two segments", c, "GET", "/does-not-exist/key")
    rec.do("encoded ending", c, "GET", "/caf%C3%A9")
    rec.do("ending with dot", c, "GET", "/favicon.ico")
    rec.do("three segments", c, "GET", "/a/b/c")
    rec.do("private IP visitor", rec.client(forwarded_for="10.9.8.7"), "GET", "/alice-custom")
    rec.do("IPv6 visitor", rec.client(forwarded_for="2001:db8::1"), "GET", "/alice-custom")
    rec.note("db", rec.env.db_state()["clicks"][-6:])


def g_redirect_404(rec):
    c = rec.client()
    rec.do("unknown link redirects", c, "GET", "/does-not-exist")
    rec.do("disabled link redirects", c, "GET", "/disabled-link")
    rec.do("secret link wrong key", c, "GET", f"/{SECRET_LINK[0]}/0000")
    rec.do("unknown nested path", c, "GET", "/a/b/c")


def g_private_interface(rec):
    c = rec.client()
    rec.do("index redirects when public interface off", c, "GET", "/")
    c = login(rec, ALICE)
    rec.do("index shown when logged in", c, "GET", "/")


def g_private_interface_no_redirect(rec):
    rec.do("index -> login when no redirect URL", rec.client(), "GET", "/")


def g_web_shorten(rec):
    c = prime(rec.client())
    shorten = lambda name, **data: rec.do(name, c, "POST", "/shorten", data=data, csrf="form",
                                         masks=[SECRET_URL_MASK])
    shorten("new link", **{"link-url": "https://example.com/new-1"})
    shorten("same long URL returns existing", **{"link-url": "https://example.com/new-1"})
    shorten("existing anonymous link", **{"link-url": "https://example.com/page/2"})
    shorten("custom ending", **{"link-url": "https://example.com/new-2", "custom-ending": "my-end"})
    shorten("custom ending in use", **{"link-url": "https://example.com/new-3", "custom-ending": "custom-one"})
    shorten("custom ending invalid chars", **{"link-url": "https://example.com/new-4", "custom-ending": "bad ending!"})
    shorten("secret link", **{"link-url": "https://example.com/new-5", "options": "s"})
    shorten("already shortened", **{"link-url": "https://bit.ly/abc"})
    shorten("own domain", **{"link-url": "http://polr.test/1"})
    shorten("invalid URL", **{"link-url": "not a url"})
    shorten("missing URL", **{})
    shorten("URL with spaces", **{"link-url": "https://example.com/a b"})
    shorten("URL with unicode host", **{"link-url": "https://bücher.example/"})
    shorten("URL with underscore host", **{"link-url": "https://my_host.example.com/"})
    shorten("ftp URL", **{"link-url": "ftp://example.com/file"})
    shorten("mailto URL", **{"link-url": "mailto:someone@example.com"})
    shorten("javascript URL", **{"link-url": "javascript:alert(1)"})
    rec.do("index after validation error", c, "GET", "/")
    rec.do("POST /shorten without CSRF", rec.client(), "POST", "/shorten",
           data={"link-url": "https://example.com/no-csrf"})
    rec.do("POST /shorten bad CSRF", rec.client(), "POST", "/shorten",
           data={"link-url": "https://example.com/no-csrf", "_token": "x" * 40})
    a = login(rec, ALICE)
    rec.do("alice shortens", a, "POST", "/shorten", data={"link-url": "https://example.com/new-1"}, csrf="form")
    rec.note("db", rec.env.db_state()["links"][-8:])


def g_shorten_permission(rec):
    c = prime(rec.client())
    rec.do("anonymous shorten refused", c, "POST", "/shorten",
           data={"link-url": "https://example.com/x"}, csrf="form")
    rec.do("index shows error", c, "GET", "/")
    a = login(rec, ALICE)
    rec.do("logged-in shorten allowed", a, "POST", "/shorten",
           data={"link-url": "https://example.com/x"}, csrf="form")


def g_pseudorandom(rec):
    c = prime(rec.client())
    rec.do("pseudorandom ending", c, "POST", "/shorten", data={"link-url": "https://example.com/pr"},
           csrf="form", masks=[(r"(http://polr\.test/)[A-Za-z0-9]{5}\b", r"\1<RANDOM>")])
    links = rec.env.db_state()["links"]
    rec.note("new ending length", len(links[-1]["short_url"]))


def g_base62(rec):
    c = prime(rec.client())
    for i in range(3):
        rec.do(f"base62 counter {i}", c, "POST", "/shorten",
               data={"link-url": f"https://example.com/b62-{i}"}, csrf="form")


def g_auth(rec):
    c = prime(rec.client(), "/login")
    rec.do("wrong password", c, "POST", "/login", data={"username": "alice", "password": "nope"}, csrf="form")
    rec.do("login page shows error", c, "GET", "/login")
    rec.do("unknown user", c, "POST", "/login", data={"username": "nobody", "password": "x"}, csrf="form")
    rec.do("inactive user", c, "POST", "/login", data={"username": "dave", "password": "DavePass123"}, csrf="form")
    rec.do("login without CSRF", rec.client(), "POST", "/login", data={"username": "alice", "password": "AlicePass123"})
    a = login(rec, ALICE, "alice logs in")
    rec.do("index logged in", a, "GET", "/")
    rec.do("dashboard", a, "GET", "/admin")
    rec.do("change password wrong current", a, "POST", "/admin/action/change_password",
           data={"current_password": "nope", "new_password": "NewAlice1"}, csrf="form")
    rec.do("change password", a, "POST", "/admin/action/change_password",
           data={"current_password": "AlicePass123", "new_password": "NewAlice1"}, csrf="form")
    rec.do("dashboard after change", a, "GET", "/admin")
    rec.do("logout", a, "GET", "/logout")
    rec.do("dashboard after logout", a, "GET", "/admin")
    rec.do("change password logged out", prime(rec.client()), "POST", "/admin/action/change_password",
           data={"current_password": "x", "new_password": "y"}, csrf="form")
    login(rec, ("alice", "AlicePass123"), "old password rejected")
    login(rec, ("alice", "NewAlice1"), "new password accepted")
    adm = login(rec, ADMIN, "admin logs in")
    rec.do("admin dashboard", adm, "GET", "/admin")
    login(rec, ("bob", "BobPass123"), "bob (admin created via panel) logs in")
    login(rec, ("carol", "CarolPass123"), "carol (self signup) logs in")


def g_admin_ajax(rec):
    adm = login(rec, ADMIN)
    dt = lambda name, path, cols, **kw: rec.do(name, adm, "GET", "/api/v2/admin/" + path,
                                               params=datatable_params(cols, **kw))
    dt("admin users", "get_admin_users", ADMIN_USER_COLUMNS)
    dt("admin users search", "get_admin_users", ADMIN_USER_COLUMNS, search="example.org")
    dt("admin users order by email desc", "get_admin_users", ADMIN_USER_COLUMNS, order_col=1, order_dir="desc")
    dt("admin links", "get_admin_links", ADMIN_LINK_COLUMNS)
    dt("admin links page 2", "get_admin_links", ADMIN_LINK_COLUMNS, start=10, length=10, draw=2)
    dt("admin links search", "get_admin_links", ADMIN_LINK_COLUMNS, search="alice")
    dt("admin links order by clicks", "get_admin_links", ADMIN_LINK_COLUMNS, order_col=2, order_dir="desc")
    dt("admin links length -1", "get_admin_links", ADMIN_LINK_COLUMNS, length=-1)
    dt("own links", "get_user_links", USER_LINK_COLUMNS)

    post = lambda name, path, data, **kw: rec.do(name, adm, "POST", "/api/v2/" + path, data=data,
                                                 csrf="header", **kw)
    post("link available", "link_avail_check", {"link_ending": "free-one"})
    post("link unavailable", "link_avail_check", {"link_ending": "custom-one"})
    post("link invalid", "link_avail_check", {"link_ending": "bad ending"})
    post("toggle api active (carol)", "admin/toggle_api_active", {"user_id": 6})
    post("generate api key (carol)", "admin/generate_new_api_key", {"user_id": 6}, masks=[API_KEY_MASK])
    post("edit quota", "admin/edit_api_quota", {"user_id": 6, "new_quota": "15"})
    post("toggle user active (carol)", "admin/toggle_user_active", {"user_id": 6})
    post("toggle user active again", "admin/toggle_user_active", {"user_id": 6})
    post("change role", "admin/change_user_role", {"user_id": 6, "role": "admin"})
    post("change role back", "admin/change_user_role", {"user_id": 6, "role": ""})
    post("add user", "admin/add_new_user", {"username": "erin", "user_password": "ErinPass1",
                                             "user_email": "erin@example.com", "user_role": ""})
    post("delete user", "admin/delete_user", {"user_id": 5})
    post("toggle link", "admin/toggle_link", {"link_ending": "custom-one"})
    post("toggle link back", "admin/toggle_link", {"link_ending": "custom-one"})
    post("edit long url", "admin/edit_link_long_url", {"link_ending": "0", "new_long_url": "https://example.com/edited"})
    post("edit long url invalid", "admin/edit_link_long_url", {"link_ending": "0", "new_long_url": "not a url"})
    post("delete link", "admin/delete_link", {"link_ending": "4"})
    post("unknown user", "admin/toggle_user_active", {"user_id": 999})
    post("unknown link", "admin/toggle_link", {"link_ending": "nope"})
    rec.do("ajax without CSRF", adm, "POST", "/api/v2/admin/toggle_link", data={"link_ending": "0"})
    rec.do("redirect to edited link", rec.client(), "GET", "/0")
    rec.do("deleted link", rec.client(), "GET", "/4")
    login(rec, ("erin", "ErinPass1"), "new user logs in")
    rec.note("db", {k: v for k, v in rec.env.db_state().items() if k in ("users", "links")})


def g_user_ajax(rec):
    a = login(rec, ALICE)
    rec.do("own links", a, "GET", "/api/v2/admin/get_user_links", params=datatable_params(USER_LINK_COLUMNS))
    rec.do("admin users forbidden", a, "GET", "/api/v2/admin/get_admin_users",
           params=datatable_params(ADMIN_USER_COLUMNS))
    rec.do("admin links forbidden", a, "GET", "/api/v2/admin/get_admin_links",
           params=datatable_params(ADMIN_LINK_COLUMNS))
    post = lambda name, path, data, **kw: rec.do(name, a, "POST", "/api/v2/" + path, data=data,
                                                 csrf="header", **kw)
    post("regenerate own key", "admin/generate_new_api_key", {"user_id": 2}, masks=[API_KEY_MASK])
    post("regenerate other's key forbidden", "admin/generate_new_api_key", {"user_id": 1})
    post("toggle link forbidden", "admin/toggle_link", {"link_ending": "0"})
    post("delete user forbidden", "admin/delete_user", {"user_id": 3})
    post("link availability", "link_avail_check", {"link_ending": "alice-custom"})
    c = rec.client()
    rec.do("user links anonymous", c, "GET", "/api/v2/admin/get_user_links",
           params=datatable_params(USER_LINK_COLUMNS))
    carol = login(rec, ("carol", "CarolPass123"))
    rec.do("regenerate key without API access", carol, "POST", "/api/v2/admin/generate_new_api_key",
           data={"user_id": 6}, csrf="header")


def g_stats(rec):
    rec.do("stats anonymous", rec.client(), "GET", "/admin/stats/alice-custom", params=STATS_BOUNDS)
    a = login(rec, ALICE)
    rec.do("own link stats (default range)", a, "GET", "/admin/stats/alice-custom")
    rec.do("own link stats with bounds", a, "GET", "/admin/stats/alice-custom", params=STATS_BOUNDS)
    rec.do("other's link stats", a, "GET", "/admin/stats/custom-one", params=STATS_BOUNDS)
    rec.do("unknown link stats", a, "GET", "/admin/stats/nope")
    rec.do("bounds reversed", a, "GET", "/admin/stats/alice-custom",
           params={"left_bound": "2020-03-01", "right_bound": "2020-02-01"},
           headers={"Referer": "http://polr.test/admin"})
    rec.do("bounds too broad", a, "GET", "/admin/stats/alice-custom",
           params={"left_bound": "2018-01-01", "right_bound": "2020-02-01"},
           headers={"Referer": "http://polr.test/admin"})
    rec.do("invalid date", a, "GET", "/admin/stats/alice-custom", params={"left_bound": "garbage"},
           headers={"Referer": "http://polr.test/admin"})
    rec.do("future right bound", a, "GET", "/admin/stats/alice-custom",
           params={"left_bound": "2020-02-01", "right_bound": "2099-01-01"},
           headers={"Referer": "http://polr.test/admin"})
    adm = login(rec, ADMIN)
    rec.do("admin views any link stats", adm, "GET", "/admin/stats/custom-one", params=STATS_BOUNDS)
    rec.do("admin links table shows stats icons", adm, "GET", "/api/v2/admin/get_admin_links",
           params=datatable_params(ADMIN_LINK_COLUMNS))


def g_stats_disabled(rec):
    a = login(rec, ALICE)
    rec.do("stats with analytics off", a, "GET", "/admin/stats/alice-custom", params=STATS_BOUNDS)
    rec.do("redirect does not record click", rec.client(), "GET", "/alice-custom")
    rec.do("user links without stats icons", a, "GET", "/api/v2/admin/get_user_links",
           params=datatable_params(USER_LINK_COLUMNS))
    rec.note("click count", len(rec.env.db_state()["clicks"]))


def g_api(rec):
    c = rec.client()
    api = lambda name, path, params, method="GET", **kw: rec.do(
        name, c, method, "/api/v2/" + path,
        params=params if method == "GET" else None,
        data=params if method == "POST" else None, **kw)
    k = {"key": ALICE_KEY}
    api("shorten plain GET", "action/shorten", dict(k, url="https://example.com/api-1"))
    api("shorten json GET", "action/shorten", dict(k, url="https://example.com/api-2", response_type="json"))
    api("shorten json POST", "action/shorten", dict(k, url="https://example.com/api-3", response_type="json"), "POST")
    api("shorten existing", "action/shorten", dict(k, url="https://example.com/api-1", response_type="json"))
    api("shorten custom", "action/shorten", dict(k, url="https://example.com/api-4", custom_ending="api-custom", response_type="json"))
    api("shorten custom taken", "action/shorten", dict(k, url="https://example.com/api-5", custom_ending="custom-one", response_type="json"))
    api("shorten custom taken plain", "action/shorten", dict(k, url="https://example.com/api-5", custom_ending="custom-one"))
    api("shorten custom invalid", "action/shorten", dict(k, url="https://example.com/api-6", custom_ending="bad end", response_type="json"))
    api("shorten secret", "action/shorten", dict(k, url="https://example.com/api-7", is_secret="true", response_type="json"),
        masks=[(r"(http:\\/\\/polr\.test\\/[A-Za-z0-9_-]+\\/)[0-9a-f]{4}", r"\1<SECRET>")])
    api("shorten invalid url", "action/shorten", dict(k, url="not a url", response_type="json"))
    api("shorten invalid url plain", "action/shorten", dict(k, url="not a url"))
    api("shorten missing url", "action/shorten", dict(k, response_type="json"))
    api("shorten url with spaces", "action/shorten", dict(k, url="https://example.com/a b c", response_type="json"))
    api("shorten already short", "action/shorten", dict(k, url="https://goo.gl/x", response_type="json"))
    api("shorten bad key", "action/shorten", dict(key="wrong", url="https://example.com/x", response_type="json"))
    api("shorten bad key plain", "action/shorten", dict(key="wrong", url="https://example.com/x"))
    api("shorten inactive api user", "action/shorten", dict(key="0", url="https://example.com/x", response_type="json"))
    api("shorten anonymous", "action/shorten", dict(url="https://example.com/anon-1", response_type="json"))
    api("shorten anonymous plain", "action/shorten", dict(url="https://example.com/anon-2"))
    api("shorten unknown response_type", "action/shorten", dict(k, url="https://example.com/api-8", response_type="xml"))
    bulk = json.dumps({"links": [{"url": "https://example.com/bulk-a"},
                                 {"url": "https://example.com/bulk-b", "custom_ending": "bulk-b"},
                                 {"url": "https://example.com/bulk-c", "is_secret": "true"}]})
    api("bulk", "action/shorten_bulk", dict(k, data=bulk), "POST",
        masks=[(r"(http:\\/\\/polr\.test\\/[A-Za-z0-9_-]+\\/)[0-9a-f]{4}", r"\1<SECRET>")])
    api("bulk plain refused", "action/shorten_bulk", dict(k, data=bulk, response_type="plain_text"), "POST")
    api("bulk invalid json", "action/shorten_bulk", dict(k, data="{nope"), "POST")
    api("bulk invalid url", "action/shorten_bulk", dict(k, data=json.dumps({"links": [{"url": "nope"}]})), "POST")
    api("bulk GET not allowed", "action/shorten_bulk", dict(k, data=bulk))
    api("lookup json", "action/lookup", dict(k, url_ending="custom-one", response_type="json"))
    api("lookup plain", "action/lookup", dict(k, url_ending="custom-one"))
    api("lookup POST", "action/lookup", dict(k, url_ending="1", response_type="json"), "POST")
    api("lookup secret without key", "action/lookup", dict(k, url_ending=ALICE_SECRET_LINK[0], response_type="json"))
    api("lookup secret with key", "action/lookup", dict(k, url_ending=ALICE_SECRET_LINK[0], url_key=ALICE_SECRET_LINK[1], response_type="json"))
    api("lookup disabled link", "action/lookup", dict(k, url_ending="disabled-link", response_type="json"))
    api("lookup missing ending", "action/lookup", dict(k, response_type="json"))
    api("lookup anonymous", "action/lookup", dict(url_ending="custom-one", response_type="json"))
    for t in ("day", "country", "referer"):
        api(f"stats {t}", "data/link", dict(k, url_ending="alice-custom", stats_type=t, **STATS_BOUNDS))
    api("stats POST", "data/link", dict(k, url_ending="alice-custom", stats_type="day", **STATS_BOUNDS), "POST")
    api("stats default bounds", "data/link", dict(k, url_ending="alice-custom", stats_type="day"))
    api("stats plain refused", "data/link", dict(k, url_ending="alice-custom", stats_type="day", response_type="plain_text"))
    api("stats bad type", "data/link", dict(k, url_ending="alice-custom", stats_type="bogus", **STATS_BOUNDS))
    api("stats missing type", "data/link", dict(k, url_ending="alice-custom", **STATS_BOUNDS))
    api("stats other's link", "data/link", dict(k, url_ending="custom-one", stats_type="day", **STATS_BOUNDS))
    api("stats admin any link", "data/link", dict(key=ADMIN_KEY, url_ending="custom-one", stats_type="country", **STATS_BOUNDS))
    api("stats unknown link", "data/link", dict(k, url_ending="nope", stats_type="day"))
    api("stats bounds reversed", "data/link", dict(k, url_ending="alice-custom", stats_type="day",
                                                   left_bound="2020-03-01", right_bound="2020-02-01"))
    api("stats bounds too broad", "data/link", dict(k, url_ending="alice-custom", stats_type="day",
                                                    left_bound="2015-01-01", right_bound="2020-02-01"))
    api("stats invalid date", "data/link", dict(k, url_ending="alice-custom", stats_type="day", left_bound="garbage"))
    api("stats anonymous", "data/link", dict(url_ending="alice-custom", stats_type="day"))
    q = {"key": QUOTA_KEY, "response_type": "json"}
    api("quota first call", "action/shorten", dict(q, url="https://example.com/q-1"))
    api("quota exceeded", "action/shorten", dict(q, url="https://example.com/q-2"))
    api("quota exceeded plain", "action/shorten", dict(key=QUOTA_KEY, url="https://example.com/q-3"))
    api("lookup also counts toward quota", "action/lookup", dict(q, url_ending="custom-one"))
    rec.do("OPTIONS preflight", c, "OPTIONS", "/api/v2/action/shorten")
    rec.do("POST with CSRF-less session cookie", prime(rec.client()), "POST", "/api/v2/action/shorten",
           data={"key": ALICE_KEY, "url": "https://example.com/with-cookie", "response_type": "json"})
    rec.note("db", rec.env.db_state()["links"][-20:])


def g_api_anon_quota(rec):
    for i in range(4):
        rec.do(f"anonymous call {i}", rec.client(forwarded_for="203.0.113.50"), "GET", "/api/v2/action/shorten",
               params={"url": f"https://example.com/anon-q-{i}", "response_type": "json"})
    rec.do("other IP not affected", rec.client(forwarded_for="203.0.113.51"), "GET", "/api/v2/action/shorten",
           params={"url": "https://example.com/anon-q-x", "response_type": "json"})


def g_api_anon_off(rec):
    c = rec.client()
    rec.do("anonymous refused json", c, "GET", "/api/v2/action/shorten",
           params={"url": "https://example.com/x", "response_type": "json"})
    rec.do("anonymous refused plain", c, "GET", "/api/v2/action/shorten", params={"url": "https://example.com/x"})
    rec.do("anonymous lookup refused", c, "GET", "/api/v2/action/lookup", params={"url_ending": "custom-one"})


def g_signup(rec):
    c = prime(rec.client(), "/signup")
    s = lambda name, **data: rec.do(name, c, "POST", "/signup", data=data, csrf="form")
    s("signup", username="frank", password="FrankPass1", email="frank@example.com")
    s("duplicate username", username="frank", password="x", email="other@example.com")
    s("duplicate email", username="frank2", password="x", email="frank@example.com")
    s("invalid email", username="gina", password="x", email="not-an-email")
    s("invalid username", username="gina smith", password="x", email="gina@example.com")
    s("missing password", username="gina", email="gina@example.com")
    rec.do("signup page shows errors", c, "GET", "/signup")
    login(rec, ("frank", "FrankPass1"), "frank logs in")
    rec.note("db", rec.env.db_state()["users"][-1:])


def g_signup_auto_api(rec):
    c = prime(rec.client(), "/signup")
    rec.do("signup gets API key", c, "POST", "/signup", data={"username": "hank", "password": "HankPass1",
                                                               "email": "hank@example.com"}, csrf="form")
    u = rec.env.db_state()["users"][-1]
    rec.note("api key", {"api_active": u["api_active"], "api_key_len": len(u["api_key"] or "")})


def g_signup_domain(rec):
    c = prime(rec.client(), "/signup")
    rec.do("allowed domain", c, "POST", "/signup",
           data={"username": "ivy", "password": "IvyPass1", "email": "ivy@example.org"}, csrf="form")
    rec.do("second allowed domain (with space)", c, "POST", "/signup",
           data={"username": "ivy2", "password": "IvyPass1", "email": "ivy2@example.com"}, csrf="form")
    rec.do("disallowed domain", c, "POST", "/signup",
           data={"username": "jack", "password": "JackPass1", "email": "jack@other.net"}, csrf="form")


def g_signup_activation(rec):
    rec.env.clear_mail()
    c = prime(rec.client(), "/signup")
    rec.do("signup requires activation", c, "POST", "/signup",
           data={"username": "kate", "password": "KatePass1", "email": "kate@example.com"}, csrf="form")
    login(rec, ("kate", "KatePass1"), "inactive login refused")
    mails = rec.mails("activation mail", masks=[RECOVERY_MASK])
    link = re.search(r"(/activate/[^'\"\s<]+)", rec.env.mails()[0]["html"]).group(1)
    rec.do("activate", rec.client(), "GET", link, masks=[RECOVERY_MASK])
    rec.do("activate again (key rotated)", rec.client(), "GET", link, masks=[RECOVERY_MASK])
    rec.do("activate wrong key", rec.client(), "GET", "/activate/kate/" + "0" * 100)
    login(rec, ("kate", "KatePass1"), "active login accepted")


def g_signup_recaptcha(rec):
    c = prime(rec.client(), "/signup")
    rec.do("signup page has recaptcha", c, "GET", "/signup")
    rec.do("signup without recaptcha response", c, "POST", "/signup",
           data={"username": "leo", "password": "LeoPass1", "email": "leo@example.com"}, csrf="form")


def g_signup_disabled(rec):
    c = rec.client()
    rec.do("GET /signup falls through to short links", c, "GET", "/signup")
    rec.do("POST /signup", prime(rec.client()), "POST", "/signup",
           data={"username": "x", "password": "x", "email": "x@example.com"}, csrf="form")
    rec.do("login page without signup link", c, "GET", "/login")


def g_password_recovery(rec):
    rec.env.clear_mail()
    c = prime(rec.client(), "/lost_password")
    rec.do("unknown email", c, "POST", "/lost_password", data={"email": "nobody@example.com"}, csrf="form")
    rec.do("known email", c, "POST", "/lost_password", data={"email": "alice@example.com"}, csrf="form")
    rec.mails("reset mail", masks=[RECOVERY_MASK])
    link = re.search(r"(/reset_password/[^'\"\s<]+)", rec.env.mails()[0]["html"]).group(1)
    r = rec.client()
    rec.do("reset page", r, "GET", link, masks=[RECOVERY_MASK])
    r.token = r.csrf(link)
    rec.do("reset without new password", r, "POST", link, data={}, csrf="form", masks=[RECOVERY_MASK])
    rec.do("reset", r, "POST", link, data={"new_password": "Reset1234"}, csrf="form", masks=[RECOVERY_MASK])
    rec.do("reset link reused", rec.client(), "GET", link, masks=[RECOVERY_MASK])
    login(rec, ("alice", "Reset1234"), "login with reset password")
    rec.do("inactive user email", c, "POST", "/lost_password", data={"email": "dave@example.com"}, csrf="form")


def g_password_recovery_off(rec):
    c = prime(rec.client(), "/lost_password")
    rec.do("recovery disabled", c, "POST", "/lost_password", data={"email": "alice@example.com"}, csrf="form")
    rec.do("login page without reset link", c, "GET", "/login")


def g_methods(rec):
    c = rec.client()
    rec.do("POST /", prime(rec.client()), "POST", "/", data={}, csrf="form")
    rec.do("GET /shorten", c, "GET", "/shorten")
    rec.do("GET /admin/action/change_password", c, "GET", "/admin/action/change_password")
    rec.do("PUT /login", c, "PUT", "/login")
    rec.do("HEAD /", c, "HEAD", "/")
    rec.do("HEAD short link", c, "HEAD", "/custom-one")
    rec.do("GET internal ajax endpoint", c, "GET", "/api/v2/link_avail_check")
    rec.do("GET unknown api path", c, "GET", "/api/v2/action/nope")
    rec.do("GET /api/v2", c, "GET", "/api/v2")
    rec.do("trailing slash on short link", c, "GET", "/custom-one/")
    rec.do("index.php front controller", c, "GET", "/index.php")
    rec.do("index.php with path", c, "GET", "/index.php/custom-one")
    rec.do("static asset", c, "GET", "/js/constants.js")


# name -> (env overrides, function)
GROUPS = {
    "public_pages": ({}, g_public_pages),
    "redirects": ({}, g_redirects),
    "redirect_404": ({"SETTING_REDIRECT_404": "true"}, g_redirect_404),
    "private_interface": ({"SETTING_PUBLIC_INTERFACE": "false"}, g_private_interface),
    "private_interface_no_redirect": ({"SETTING_PUBLIC_INTERFACE": "false", "SETTING_INDEX_REDIRECT": ""},
                                      g_private_interface_no_redirect),
    "web_shorten": ({}, g_web_shorten),
    "shorten_permission": ({"SETTING_SHORTEN_PERMISSION": "true"}, g_shorten_permission),
    "pseudorandom": ({"SETTING_PSEUDORANDOM_ENDING": "true"}, g_pseudorandom),
    "base62": ({"POLR_BASE": "62"}, g_base62),
    "auth": ({}, g_auth),
    "admin_ajax": ({}, g_admin_ajax),
    "user_ajax": ({}, g_user_ajax),
    "stats": ({}, g_stats),
    "stats_disabled": ({"SETTING_ADV_ANALYTICS": "false"}, g_stats_disabled),
    "api": ({}, g_api),
    "api_anon_quota": ({"SETTING_ANON_API_QUOTA": "2"}, g_api_anon_quota),
    "api_anon_off": ({"SETTING_ANON_API": "false"}, g_api_anon_off),
    "signup": ({}, g_signup),
    "signup_auto_api": ({"SETTING_AUTO_API": "true"}, g_signup_auto_api),
    "signup_domain": ({"SETTING_RESTRICT_EMAIL_DOMAIN": "true",
                       "SETTING_ALLOWED_EMAIL_DOMAINS": '"example.org,example.com"'}, g_signup_domain),
    "signup_activation": ({"POLR_ACCT_ACTIVATION": "true"}, g_signup_activation),
    "signup_recaptcha": ({"POLR_ACCT_CREATION_RECAPTCHA": "true",
                          "POLR_RECAPTCHA_SITE_KEY": '"site-key"',
                          "POLR_RECAPTCHA_SECRET_KEY": '"secret-key"'}, g_signup_recaptcha),
    "signup_disabled": ({"POLR_ALLOW_ACCT_CREATION": "false"}, g_signup_disabled),
    "password_recovery": ({}, g_password_recovery),
    "password_recovery_off": ({"SETTING_PASSWORD_RECOV": "false"}, g_password_recovery_off),
    "methods": ({}, g_methods),
}


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--base-url", required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--app-exec", required=True, help="command prefix to run a command in the app container")
    ap.add_argument("--db-exec", required=True, help="command prefix to run a command in the MySQL container")
    ap.add_argument("--app-dir", default="/var/www/html")
    ap.add_argument("--mail-url", required=True)
    ap.add_argument("--only", nargs="*", help="run only these groups")
    ap.add_argument("--extra-env", action="append", metavar="KEY=VALUE",
                    help="extra .env setting for the instance under test (repeatable)")
    args = ap.parse_args()

    env = Env(args)
    os.makedirs(args.out, exist_ok=True)
    failed = []
    for name, (overrides, fn) in GROUPS.items():
        if args.only and name not in args.only:
            continue
        env.restore_db()
        env.write_env(overrides)
        env.clear_mail()
        rec = Recorder(env, name)
        started = time.time()
        try:
            fn(rec)
        except Exception as e:  # keep going; record the failure
            failed.append(name)
            rec.note("ERROR", f"{type(e).__name__}: {e}")
        with open(os.path.join(args.out, f"{name}.json"), "w", encoding="utf-8", newline="\n") as f:
            json.dump({"group": name, "env": overrides, "steps": rec.steps}, f,
                      indent=2, ensure_ascii=False, sort_keys=False)
            f.write("\n")
        print(f"{name:32s} {len(rec.steps):3d} steps  {time.time() - started:5.1f}s"
              + ("  ERROR" if name in failed else ""))
    env.restore_db()
    env.write_env({})
    if failed:
        print("groups with errors:", ", ".join(failed), file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
