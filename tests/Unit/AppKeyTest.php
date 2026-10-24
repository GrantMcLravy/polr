<?php

namespace Tests\Unit;

use Illuminate\Encryption\Encrypter;
use Tests\TestCase;

/**
 * Polr 2.x's setup wizard writes a 32-character hex APP_KEY without the
 * "base64:" prefix. It must keep working as a raw AES-256-CBC key.
 */
class AppKeyTest extends TestCase
{
    public function test_hex_app_key_from_setup_is_a_valid_aes_256_key(): void
    {
        $key = 'a5bd6a61b1327670bedd712a358d9c2b';

        $this->assertTrue(Encrypter::supported($key, 'AES-256-CBC'));

        $encrypter = new Encrypter($key, 'AES-256-CBC');
        $this->assertSame('polr', $encrypter->decrypt($encrypter->encrypt('polr')));
    }

    public function test_application_uses_the_configured_hex_key(): void
    {
        $this->assertSame('a5bd6a61b1327670bedd712a358d9c2b', config('app.key'));
        $this->assertSame('AES-256-CBC', config('app.cipher'));
        $this->assertSame('value', decrypt(encrypt('value')));
    }
}
