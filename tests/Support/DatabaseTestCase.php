<?php
/**
 * Filename: DatabaseTestCase.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Base for tests that touch the plugin's tables, on PostgreSQL
 *              with the real migration, in a database of their own.
 *
 * @package Postlinkly
 *
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Postlinkly\Teme\Tests\Support;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

/**
 * Runs the plugin's migration exactly as Teme runs it, on PostgreSQL (never
 * SQLite, so a type or index the real database refuses fails here too).
 *
 * The connection comes from POSTLINKLY_TEST_DB_* (host, port, name, user,
 * password). The defaults are the dev site's `postgres_test` container on
 * port 5453. That container keeps its data in memory, so a restart wipes the
 * database; it is created again here when missing.
 *
 * @since 1.0.0
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * The plugin's own tables, as `owns.tables` lists them.
     *
     * @since 1.0.0
     */
    public const TABLES = ['postlinkly_items', 'postlinkly_stems', 'postlinkly_suggestions'];

    private static bool $booted = false;

    /**
     * Connects once per run, migrates afresh, then empties the tables before
     * every test.
     *
     * @since 1.0.0
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$booted) {
            self::boot();
            self::$booted = true;
        }

        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
        }
    }

    /**
     * Makes the connection, creating the test database first if it is gone,
     * and runs the migration from a clean slate.
     *
     * @since 1.0.0
     */
    private static function boot(): void
    {
        $app = new Container;
        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        $database = getenv('POSTLINKLY_TEST_DB_NAME') ?: 'postlinkly_test';
        $capsule = new Capsule($app);
        $capsule->addConnection(self::connection('teme_test'), 'admin');
        $capsule->addConnection(self::connection($database));
        $capsule->setAsGlobal();

        $exists = $capsule->getConnection('admin')->selectOne('select 1 as found from pg_database where datname = ?', [$database]);
        if ($exists === null) {
            // The name comes from this file or the tester's environment, never
            // from data, and PostgreSQL can't bind an identifier, so it is quoted.
            $capsule->getConnection('admin')->statement('create database "'.str_replace('"', '', $database).'"');
        }

        $app->instance('db', $capsule->getDatabaseManager());
        $app->bind('db.schema', static fn (Container $app) => $app['db']->connection()->getSchemaBuilder());

        // Teme never runs down(), so the tables are cleared here before the real migration runs.
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        $migration = require __DIR__.'/../../database/migrations/2026_11_20_000001_create_postlinkly_tables.php';
        $migration->up();
    }

    /**
     * Connection settings for one database on the test server.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    private static function connection(string $database): array
    {
        return [
            'driver' => 'pgsql',
            'host' => getenv('POSTLINKLY_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('POSTLINKLY_TEST_DB_PORT') ?: '5453',
            'database' => $database,
            'username' => getenv('POSTLINKLY_TEST_DB_USER') ?: 'teme',
            'password' => getenv('POSTLINKLY_TEST_DB_PASSWORD') ?: 'teme',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ];
    }
}
