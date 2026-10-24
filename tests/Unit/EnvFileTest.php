<?php

namespace Tests\Unit;

use App\Bootstrap\LoadEnvironmentVariables;
use Dotenv\Repository\Adapter\ArrayAdapter;
use Dotenv\Repository\RepositoryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * .env files must read back exactly as under Polr 2.3.0 (phpdotenv 1.1).
 *
 * The fixtures are real files: written by the 2.3.0 setup wizard (also with
 * awkward values), the repository's .env.setup and tests/test_env. The
 * expected values were recorded from the original application
 * (baseline/run.sh).
 */
class EnvFileTest extends TestCase
{
    public static function fixtures(): array
    {
        $cases = [];
        foreach (glob(__DIR__.'/../../baseline/fixtures/env/*.env') as $file) {
            $cases[basename($file, '.env')] = [$file, substr($file, 0, -4).'.parsed.json'];
        }
        return $cases;
    }

    #[DataProvider('fixtures')]
    public function test_env_file_reads_back_as_in_polr_2(string $envFile, string $expectedFile): void
    {
        $expected = json_decode(file_get_contents($expectedFile), true);

        $repository = RepositoryBuilder::createWithNoAdapters()
            ->addAdapter(ArrayAdapter::class)
            ->immutable()
            ->make();
        LoadEnvironmentVariables::load($envFile, $repository);

        $actual = [];
        foreach (array_keys($expected) as $key) {
            $actual[$key] = $this->envValue($repository->get($key));
        }

        $this->assertSame($expected, $actual);
    }

    public function test_existing_environment_variables_win(): void
    {
        $repository = RepositoryBuilder::createWithNoAdapters()
            ->addAdapter(ArrayAdapter::class)
            ->immutable()
            ->make();
        $repository->set('APP_NAME', 'From the environment');

        LoadEnvironmentVariables::load(__DIR__.'/../../baseline/fixtures/env/setup-default.env', $repository);

        $this->assertSame('From the environment', $repository->get('APP_NAME'));
        $this->assertSame('polr.test', $repository->get('APP_ADDRESS'));
    }

    public function test_missing_file_is_ignored(): void
    {
        $repository = RepositoryBuilder::createWithNoAdapters()->addAdapter(ArrayAdapter::class)->make();

        LoadEnvironmentVariables::load('/nonexistent/.env', $repository);

        $this->assertNull($repository->get('APP_NAME'));
    }

    /** The conversions env() applies (same in Lumen 5.1 and Laravel 13). */
    private function envValue(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        if (preg_match('/\A([\'"])(.*)\1\z/', $value, $matches)) {
            return $matches[2];
        }
        return $value;
    }
}
