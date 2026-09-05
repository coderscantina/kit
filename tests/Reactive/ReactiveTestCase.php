<?php

declare(strict_types=1);

namespace Tests\Reactive;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Registry;
use Tests\Fixtures\Reactive\Note;
use Tests\TestCase;

/**
 * The reactive suite runs against MySQL and Redis: deadlock retry and the
 * registry cannot be faked on sqlite. Set REACTIVE_TEST_DB to run it; CI
 * provides both services. Without it the suite skips with a message.
 */
abstract class ReactiveTestCase extends TestCase
{
    private bool $booted = false;

    use RefreshDatabase {
        migrateDatabases as baseMigrateDatabases;
    }

    protected function setUp(): void
    {
        $database = getenv('REACTIVE_TEST_DB');

        if ($database === false || $database === '') {
            $this->markTestSkipped('REACTIVE_TEST_DB is unset; the reactive suite needs MySQL and Redis.');
        }

        parent::setUp();

        $this->booted = true;
        app(Registry::class)->flush();
        app(Metrics::class)->flush();
    }

    /**
     * Runs once per process, before the per-test transaction opens. On MySQL
     * a DDL statement would commit that transaction, so the fixture table
     * cannot be created inside a test.
     */
    protected function migrateDatabases(): void
    {
        $this->baseMigrateDatabases();

        Note::migrate();
    }

    protected function tearDown(): void
    {
        if ($this->booted) {
            app(Registry::class)->flush();
            app(Metrics::class)->flush();
        }

        parent::tearDown();
    }

    /**
     * Point the app at the MySQL database and Redis from the env before boot.
     */
    protected function refreshApplication(): void
    {
        $database = getenv('REACTIVE_TEST_DB');

        if ($database !== false && $database !== '') {
            $overrides = [
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => $database,
                'DB_HOST' => getenv('REACTIVE_TEST_DB_HOST') ?: '127.0.0.1',
                'DB_PORT' => getenv('REACTIVE_TEST_DB_PORT') ?: '3306',
                'DB_USERNAME' => getenv('REACTIVE_TEST_DB_USERNAME') ?: 'root',
                'DB_PASSWORD' => getenv('REACTIVE_TEST_DB_PASSWORD') ?: '',
                'REDIS_HOST' => getenv('REACTIVE_TEST_REDIS_HOST') ?: '127.0.0.1',
                'REDIS_PORT' => getenv('REACTIVE_TEST_REDIS_PORT') ?: '6379',
                'REACTIVE_REGISTRY' => 'redis',
                'CACHE_STORE' => 'array',
            ];

            // phpunit.xml wrote the sqlite values into $_ENV and $_SERVER;
            // Laravel reads those before putenv, so all three must agree.
            foreach ($overrides as $key => $value) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        parent::refreshApplication();
    }
}
