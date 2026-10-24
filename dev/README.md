# Development environment

PHP 8.4 (the repository is mounted at `/app`), MySQL 5.7 and a mail catcher, for
working on the Laravel 13 port. Not the production image (that is phase 7).

```sh
cp baseline/fixtures/env/baseline.env .env          # DB_HOST=mysql, MAIL_HOST=mailpit
docker compose -f dev/docker-compose.yml up -d --wait
docker compose -f dev/docker-compose.yml exec -T mysql mysql -uroot -prootpw < baseline/fixtures/polr-2.3.0.sql
docker compose -f dev/docker-compose.yml run --rm app composer install
docker compose -f dev/docker-compose.yml run --rm app php artisan migrate --pretend --force
```

The app is served on http://127.0.0.1:18081 and the mail catcher on
http://127.0.0.1:18026. In Git Bash on Windows, set `MSYS_NO_PATHCONV=1` first.

## Tests

```sh
docker compose -f dev/docker-compose.yml exec -T mysql mysql -uroot -prootpw -e "create database if not exists polr_test"
docker compose -f dev/docker-compose.yml exec app vendor/bin/phpunit
```

`phpunit.xml` pins every Polr setting and points at the dev MySQL (`DB_HOST=mysql`,
database `polr_test`); variables already set in the environment win (CI sets
`DB_HOST=127.0.0.1`). Other PHP versions: build `docker build --build-arg
PHP_VERSION=8.5 -t polr-dev:8.5 dev` and run the same command in that image on the
`polr-dev_default` network with the `polr-dev_vendor` volume mounted at
`/app/vendor`.

## Checking behavior against Polr 2.3.0

```sh
dev/replay.sh                       # all scenario groups
dev/replay.sh /tmp/run --only api   # selected groups
python dev/compare.py baseline/golden dev/.skel/replay --group api -v   # diffs
python dev/check_routes.py routes.json   # after: php artisan route:list --json > routes.json
```

`dev/replay.sh` restores the 2.3.0 database before each scenario group, writes the
group's `.env`, replays the recorded requests against http://127.0.0.1:18081 and
compares with `baseline/golden`. It fails on any difference not listed in
`dev/accepted-differences.txt`. Before the first run: copy
`tests/fixtures/geoip/GeoIP2-City-Test.mmdb` to `storage/app/geoip.mmdb`. `dev/compare.py` normalizes the accepted
formatting-only differences listed in `MODERNIZATION.md` (`--strict` disables that).
