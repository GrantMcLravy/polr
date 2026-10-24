#!/usr/bin/env bash
# Replay the Polr 2.3.0 baseline scenarios against the dev environment and
# compare with baseline/golden. Exits non-zero on any difference not listed in
# dev/accepted-differences.txt.
#
# Usage: dev/replay.sh [OUT_DIR] [scenario args...]
#        (e.g. dev/replay.sh /tmp/run --only api auth)
set -euo pipefail
export MSYS_NO_PATHCONV=1  # Git Bash on Windows: do not rewrite container paths
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
OUT="${1:-dev/.skel/replay}"; shift || true
DC="docker compose -f dev/docker-compose.yml"
PY="${PYTHON:-python}"

$DC up -d --wait >/dev/null 2>&1
rm -rf "$OUT"
# TRUSTED_PROXIES: honour the scenario client's X-Forwarded-For like the
# baseline's Apache (mod_remoteip) did.
"$PY" baseline/scenarios.py --base-url "http://127.0.0.1:${DEV_HTTP_PORT:-18081}" --out "$OUT" \
    --app-exec "$DC exec -T app" --db-exec "$DC exec -T mysql" --app-dir /app \
    --mail-url "http://127.0.0.1:${DEV_MAIL_PORT:-18026}" \
    --extra-env "TRUSTED_PROXIES=172.16.0.0/12,192.168.0.0/16,10.0.0.0/8" "$@"
status=0
"$PY" dev/compare.py baseline/golden "$OUT" --allow dev/accepted-differences.txt > "$OUT.txt" || status=$?
grep -v "(accepted)$" "$OUT.txt" | grep "^\[" || true
tail -2 "$OUT.txt"
exit $status
