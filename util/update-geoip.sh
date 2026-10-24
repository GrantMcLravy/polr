#!/bin/sh
# Update the MaxMind GeoLite2 City database used by Polr's advanced analytics
# (storage/app/geoip.mmdb). Requires MAXMIND_LICENSE_KEY in .env.
#
# MaxMind publishes GeoLite2 updates twice a week, and its license requires
# replacing old copies within 30 days of a new release, so run this regularly
# from the system's cron, as the user that owns Polr's storage/ directory. For
# example, every Wednesday and Saturday at 04:00:
#
#   0 4 * * 3,6  /path/to/polr/util/update-geoip.sh >> /path/to/polr/storage/logs/geoip-update.log 2>&1
#
# In Docker, run it from the host's cron with "docker compose exec -T polr
# util/update-geoip.sh" (see docs/user-guide/docker.md).
#
# Exits non-zero if the update fails. The license key is redacted from the
# output (error messages include the download URL).
cd "$(dirname "$0")/.." || exit 1

# (A wide COLUMNS keeps Symfony from wrapping the URL across lines.)
if output=$(COLUMNS=10000 php artisan geoip:update --no-ansi 2>&1); then
    status=0
else
    status=$?
fi

printf '%s\n' "$output" | sed 's/license_key=[^&[:space:]]*/license_key=REDACTED/g'
exit $status
