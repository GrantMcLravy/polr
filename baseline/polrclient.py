"""Minimal HTTP client for driving Polr in the baseline scripts (stdlib only).

Redirects are not followed so that status codes and Location headers can be
recorded. Each Client keeps its own cookie jar, i.e. acts as one browser.
"""
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Response:
    def __init__(self, status, headers, body):
        self.status = status
        self.headers = headers  # list of (name, value), in order
        self.body = body  # str

    def header(self, name):
        for k, v in self.headers:
            if k.lower() == name.lower():
                return v
        return None

    def __repr__(self):
        return f"<Response {self.status} {self.header('Location') or ''}>"


class Client:
    def __init__(self, base_url, forwarded_for=None):
        self.base_url = base_url.rstrip("/")
        self.jar = http.cookiejar.CookieJar()
        self.forwarded_for = forwarded_for
        self.opener = urllib.request.build_opener(
            _NoRedirect(), urllib.request.HTTPCookieProcessor(self.jar))

    def url(self, path):
        return path if path.startswith("http") else self.base_url + path

    def request(self, method, path, params=None, data=None, headers=None):
        url = self.url(path)
        if params:
            url += ("&" if "?" in url else "?") + urllib.parse.urlencode(params, doseq=True)
        body = None
        hdrs = {"User-Agent": "polr-baseline/1.0"}
        if self.forwarded_for:
            hdrs["X-Forwarded-For"] = self.forwarded_for
        if data is not None:
            body = urllib.parse.urlencode(data, doseq=True).encode()
            hdrs["Content-Type"] = "application/x-www-form-urlencoded"
        hdrs.update(headers or {})
        req = urllib.request.Request(url, data=body, headers=hdrs, method=method)
        try:
            resp = self.opener.open(req, timeout=60)
        except urllib.error.HTTPError as e:
            resp = e
        raw = resp.read()
        return Response(resp.status if hasattr(resp, "status") else resp.code,
                        list(resp.headers.items()),
                        raw.decode("utf-8", errors="replace"))

    def get(self, path, params=None, headers=None):
        return self.request("GET", path, params=params, headers=headers)

    def post(self, path, data=None, headers=None):
        return self.request("POST", path, data=data or {}, headers=headers)

    def csrf(self, path="/"):
        """Fetch a page and return the CSRF token embedded in it."""
        r = self.get(path)
        m = (re.search(r"name=['\"]_token['\"] value=['\"]([^'\"]+)", r.body)
             or re.search(r'name="csrf-token" content="([^"]+)"', r.body))
        if not m:
            raise RuntimeError(f"no CSRF token on {path}: {r.status}")
        return m.group(1)

    def cookie(self, name):
        for c in self.jar:
            if c.name == name:
                return c.value
        return None
