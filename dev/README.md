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
