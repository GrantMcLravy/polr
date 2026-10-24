"""Compare scenario recordings of the port against the Polr 2.3.0 baseline.

  python dev/compare.py baseline/golden OUT_DIR [--group NAME ...] [-v]
      [--allow dev/accepted-differences.txt]

Prints one line per differing step (status, headers, body/JSON, mails, DB
state) and a summary. -v shows a unified diff of each differing body.
Header order and names' case are ignored.
"""
import argparse
import difflib
import json
import os
import re
import sys


def load(path):
    with open(path, encoding="utf-8") as f:
        return json.load(f)


# Accepted, documented differences (MODERNIZATION.md) that are normalized away
# so that real behavior changes stand out. --strict disables this.
#  - Set-Cookie: Symfony writes attributes in lower case and Laravel adds
#    SameSite=Lax.
#  - Cache-Control: newer Symfony adds "private" to "no-cache".
#  - Content-Type: charset case and spacing.
#  - Vary: Accept-Encoding was added by the baseline's Apache (mod_deflate).
#  - HTML bodies: whitespace-only line differences (Blade output) and the
#    quoted URL in Symfony's redirect page (<meta http-equiv="refresh">).
#  - The MaxMind attribution line (deliberate change) on the about and stats
#    pages.
STRICT = False


def norm_header(name, value):
    name = name.lower()
    if STRICT:
        return name, value
    if name == "set-cookie":
        parts = [p.strip() for p in value.split(";")]
        attrs = sorted(p.lower() for p in parts[1:] if p.lower() != "samesite=lax")
        return name, "; ".join([parts[0]] + attrs)
    if name == "cache-control":
        return name, ", ".join(sorted(p.strip() for p in value.split(",") if p.strip() != "private"))
    if name == "content-type":
        return name, value.lower().replace("; ", ";")
    return name, value


def norm_headers(headers):
    out = [norm_header(k, v) for k, v in headers]
    if not STRICT:
        out = [h for h in out if h != ("vary", "Accept-Encoding")]
    return sorted(out)


def body_text(resp):
    if resp.get("json") is not None:
        return json.dumps(resp["json"], indent=1, ensure_ascii=False, sort_keys=True)
    body = resp.get("body") or ""
    if not STRICT:
        # Newer Blade emits slightly different whitespace around directives
        # (@section, @if, ...); compare HTML line by line without it.
        body = "\n".join(l.strip() for l in body.splitlines() if l.strip())
        # Deliberate change: MaxMind attribution on the about and stats pages.
        body = "\n".join(l for l in body.splitlines() if "GeoLite2 data created by MaxMind" not in l)
        # Symfony's redirect page now quotes the URL in the meta refresh tag.
        body = re.sub(r"""(<meta http-equiv="refresh" content="0;url=)'([^']*)'" />""", r'\1\2" />', body)
    return body


def diff_text(a, b, limit=40):
    lines = list(difflib.unified_diff(a.splitlines(), b.splitlines(), "baseline", "port", lineterm="", n=1))
    if len(lines) > limit:
        lines = lines[:limit] + [f"... ({len(lines) - limit} more lines)"]
    return "\n".join("        " + l for l in lines)


def compare_step(a, b, verbose):
    problems = []
    if "response" in a:
        ra, rb = a["response"], b.get("response")
        if rb is None:
            return ["step has no response in port"]
        if ra["status"] != rb["status"]:
            problems.append(f"status {ra['status']} -> {rb['status']}")
        ha, hb = norm_headers(ra["headers"]), norm_headers(rb["headers"])
        if ha != hb:
            only_a = [h for h in ha if h not in hb]
            only_b = [h for h in hb if h not in ha]
            problems.append(f"headers -{only_a} +{only_b}")
        ta, tb = body_text(ra), body_text(rb)
        if ta != tb:
            problems.append("body differs" + ("\n" + diff_text(ta, tb) if verbose else ""))
    elif "mails" in a:
        if a["mails"] != b.get("mails"):
            ta = json.dumps(a["mails"], indent=1, ensure_ascii=False)
            tb = json.dumps(b.get("mails"), indent=1, ensure_ascii=False)
            problems.append("mails differ" + ("\n" + diff_text(ta, tb) if verbose else ""))
    elif "value" in a:
        if a["value"] != b.get("value"):
            ta = json.dumps(a["value"], indent=1, ensure_ascii=False, sort_keys=True)
            tb = json.dumps(b.get("value"), indent=1, ensure_ascii=False, sort_keys=True)
            problems.append("value differs" + ("\n" + diff_text(ta, tb) if verbose else ""))
    return problems


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("golden")
    ap.add_argument("port")
    ap.add_argument("--group", nargs="*")
    ap.add_argument("-v", "--verbose", action="store_true")
    ap.add_argument("--strict", action="store_true", help="do not normalize accepted header differences")
    ap.add_argument("--allow", help='file listing steps ("group: step name") that may differ')
    args = ap.parse_args()
    global STRICT
    STRICT = args.strict

    allowed = set()
    if args.allow:
        with open(args.allow, encoding="utf-8") as f:
            allowed = {l.strip() for l in f if l.strip() and not l.startswith("#")}

    total = differing = 0
    unexpected = []
    seen_allowed = set()

    def report(group, label, message):
        key = f"{group}: {label}"
        if key in allowed:
            seen_allowed.add(key)
            print(f"[{group}] {message}  (accepted)")
        else:
            unexpected.append(key)
            print(f"[{group}] {message}")

    for name in sorted(os.listdir(args.golden)):
        group = name[:-5]
        if not name.endswith(".json") or (args.group and group not in args.group):
            continue
        a = load(os.path.join(args.golden, name))
        port_path = os.path.join(args.port, name)
        if not os.path.exists(port_path):
            print(f"[{group}] missing in port")
            continue
        b = load(port_path)
        steps_b = b["steps"]
        for i, step in enumerate(a["steps"]):
            total += 1
            other = steps_b[i] if i < len(steps_b) else {}
            if other.get("name") != step["name"]:
                differing += 1
                report(group, step["name"], f"#{i} {step['name']}: step mismatch (port: {other.get('name')})")
                continue
            problems = compare_step(step, other, args.verbose)
            if problems:
                differing += 1
                report(group, step["name"], f"#{i} {step['name']}: " + "; ".join(problems))
        for extra in steps_b[len(a["steps"]):]:
            report(group, "extra step", f"extra step in port: {extra.get('name')}: {extra.get('value', '')}")
    print(f"\n{total - differing}/{total} steps identical")
    if args.allow:
        for key in sorted(allowed - seen_allowed):
            if not args.group or key.split(":")[0] in args.group:
                print(f"note: accepted difference no longer differs: {key}")
        print(f"{len(unexpected)} unexpected difference(s)")
        return 1 if unexpected else 0
    return 0 if differing == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
