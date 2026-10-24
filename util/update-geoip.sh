#!/bin/sh
# Update the MaxMind GeoLite2 City database used by Polr's advanced analytics
# (storage/app/geoip.mmdb). Requires MAXMIND_LICENSE_KEY in .env.
#
# MaxMind publishes GeoLite2 updates twice a week, and its license requires
# replacing old copies within 30 days of a new release, so run this from cron,
# as the user that owns Polr's storage/ directory. For example, every Wednesday
# and Saturday at 04:00:
#
#   0 4 * * 3,6  /path/to/polr/util/update-geoip.sh >> /path/to/polr/storage/logs/geoip-update.log 2>&1
#
# Exits non-zero if the update fails.
set -e
cd "$(dirname "$0")/.."
exec php artisan geoip:update
