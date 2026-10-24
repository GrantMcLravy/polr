#!/usr/bin/env bash
# Rebuild the Polr 2.3.0 baseline from scratch:
#   1. run the original code (git commit $POLR_COMMIT) on PHP 7.1 + MySQL 5.7
#   2. drive the real setup wizard (fixtures/env/*.env)
#   3. create sample data over HTTP, normalize it, dump it (fixtures/polr-2.3.0.sql)
#   4. record how phpdotenv 1.1 parses each .env fixture (fixtures/env/*.parsed.json)
#   5. record every scenario against the dump (golden/*.json)
#
# Usage: baseline/run.sh [--keep]    (--keep leaves the containers running)
set -euo pipefail
export MSYS_NO_PATHCONV=1  # Git Bash on Windows: do not rewrite container paths

POLR_COMMIT="${POLR_COMMIT:-677080f}"
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "$HERE" && git rev-parse --show-toplevel)"
HTTP_PORT="${BASELINE_HTTP_PORT:-18080}"
MAIL_PORT="${BASELINE_MAIL_PORT:-18025}"
BASE="http://127.0.0.1:$HTTP_PORT"
PY="${PYTHON:-python}"
cd "$HERE"
dc() { docker compose "$@"; }

echo "== build original app ($POLR_COMMIT)"
rm -rf .build && mkdir -p .build/src .build/geoip
cp Dockerfile .build/
(cd "$ROOT" && git -c core.autocrlf=false archive "$POLR_COMMIT") | tar -x -C .build/src
curl -sSfL -o .build/geoip/GeoIP2-City-Test.mmdb \
    https://github.com/maxmind/MaxMind-DB/raw/main/test-data/GeoIP2-City-Test.mmdb
dc down -v --remove-orphans >/dev/null 2>&1 || true
dc build app
dc up -d --wait

wait_http() {
    for _ in $(seq 1 60); do
        curl -s "$BASE/" >/dev/null && return 0
        sleep 1
    done
    echo "app did not come up" >&2; exit 1
}
reset_env() { dc exec -T app sh -c 'cp .env.setup .env && chown www-data .env'; }
wait_http

echo "== setup wizard with awkward values (.env fixture only)"
mkdir -p fixtures/env
"$PY" seed.py setup-quirks "$BASE"
dc cp app:/var/www/html/.env fixtures/env/setup-quirks.env
reset_env

echo "== setup wizard"
"$PY" seed.py setup "$BASE"
dc cp app:/var/www/html/.env fixtures/env/setup-default.env

echo "== enable advanced analytics with MaxMind's test City database"
dc cp .build/geoip/GeoIP2-City-Test.mmdb app:/var/www/html/storage/app/geoip.mmdb
# MAIL_ENCRYPTION=null: Lumen defaults to STARTTLS, which the mail catcher does not offer.
dc exec -T app sh -c 'chown www-data storage/app/geoip.mmdb \
    && sed -i "s/^SETTING_ADV_ANALYTICS=.*/SETTING_ADV_ANALYTICS=true/" .env \
    && echo MAIL_ENCRYPTION=null >> .env'
dc cp app:/var/www/html/.env fixtures/env/baseline.env

echo "== sample data"
"$PY" seed.py populate "$BASE"
dc exec -T mysql mysql -uroot -prootpw polr < fixtures/normalize.sql
dc exec -T mysql mysqldump -uroot -prootpw --skip-dump-date --single-transaction \
    --databases polr > fixtures/polr-2.3.0.sql

echo "== phpdotenv 1.1 parse results"
cp .build/src/.env.setup fixtures/env/dot-env-setup.env
cp .build/src/tests/test_env fixtures/env/travis-test.env
dc cp envprobe.php app:/tmp/envprobe.php
for f in fixtures/env/*.env; do
    name="$(basename "$f" .env)"
    dc exec -T app sh -c 'rm -rf /tmp/probe && mkdir /tmp/probe && cat > /tmp/probe/.env' < "$f"
    dc exec -T app php /tmp/envprobe.php /var/www/html /tmp/probe > "fixtures/env/$name.parsed.json"
done

echo "== scenarios"
rm -rf golden
"$PY" scenarios.py --base-url "$BASE" --out golden \
    --app-exec "docker compose exec -T app" \
    --db-exec "docker compose exec -T mysql" \
    --mail-url "http://127.0.0.1:$MAIL_PORT"

if [[ "${1:-}" != "--keep" ]]; then
    dc down -v >/dev/null
fi
echo "== done"
