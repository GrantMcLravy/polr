# Obtaining a MaxMind GeoIP License Key
-----------------

If you would like to use "advanced analytics" in order to get more insights on your users, you must input a MaxMind GeoIP license key during the setup process or add it later to your configuration file (`.env`).

To obtain a license key, create an account on [MaxMind's website](https://www.maxmind.com/en/geolite2/signup). After you create your account and confirm your email, go to your [MaxMind account page](https://www.maxmind.com/en/accounts/current/license-key) to generate a license key for use with Polr. If you are asked whether your license key will be used for "GeoIP Update", answer no.

Copy your newly generated license key and input it into Polr.

The GeoLite2 database must be kept up to date: see
[Advanced analytics](installation.md#advanced-analytics-geoip) (or
[GeoIP database updates](docker.md#geoip-database-updates) for Docker) for the
update script and the cron job that runs it.

## Troubleshooting

### `util/update-geoip.sh` or `php artisan geoip:update` fails

Make sure your `.env` contains your license key:

```
MAXMIND_LICENSE_KEY="LICENSE_KEY_GOES_HERE"
```

If you cache the configuration (`php artisan config:cache`), run that command again
after changing `.env`. Then run the update again. "401 Unauthorized" in the output
means the license key is missing or wrong.
