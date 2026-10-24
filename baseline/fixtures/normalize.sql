-- Applied after seed.py populate so the 2.3.0 dump is deterministic:
-- fixed timestamps, API keys, secret keys, recovery keys and client IPs.

UPDATE users SET
    created_at = DATE_ADD('2020-02-01 10:00:00', INTERVAL id MINUTE),
    updated_at = DATE_ADD('2020-02-01 10:00:00', INTERVAL id MINUTE),
    recovery_key = CONCAT('recovery', LPAD(id, 92, '0')),
    ip = '198.51.100.1';

UPDATE users SET api_key = '0000000000000000000000000ad001' WHERE username = 'polradmin';
UPDATE users SET api_key = '00000000000000000000000a11ce01' WHERE username = 'alice';
UPDATE users SET api_key = '00000000000000000000000000ff01' WHERE username = 'quota';

UPDATE links SET
    created_at = DATE_ADD('2020-02-01 12:00:00', INTERVAL id MINUTE),
    updated_at = DATE_ADD('2020-02-01 12:00:00', INTERVAL id MINUTE);
UPDATE links SET ip = '198.51.100.1' WHERE ip NOT LIKE '203.%';
UPDATE links SET secret_key = '5ec7' WHERE short_url = '5';
UPDATE links SET secret_key = 'a5ec' WHERE short_url = 'a';

UPDATE clicks SET
    created_at = DATE_ADD('2020-02-02 08:00:00', INTERVAL id * 7 HOUR),
    updated_at = DATE_ADD('2020-02-02 08:00:00', INTERVAL id * 7 HOUR);
