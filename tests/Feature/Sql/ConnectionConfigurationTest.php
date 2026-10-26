<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Tests\TestCase;
use Andmarruda\Lpb\Models\Sql\Page;

/**
 * Connection configuration and MongoDB tooling on a SQL-only application
 */
class ConnectionConfigurationTest extends TestCase
{
    /**
     * Test models use the configured SQL connection
     */
    public function test_models_use_configured_connection(): void
    {
        config(['laravel-page-builder.connections.sql' => 'tenant']);

        $this->assertSame('tenant', (new Page())->getConnectionName());
    }

    /**
     * Test models fall back to their own connection when none is configured
     */
    public function test_models_fall_back_without_configuration(): void
    {
        config(['laravel-page-builder.connections.sql' => null]);

        $this->assertNull((new Page())->getConnectionName());
    }

    /**
     * Test the MongoDB installer fails cleanly without a MongoDB connection
     */
    public function test_install_mongo_fails_without_mongodb_connection(): void
    {
        $this->artisan('lpb:install-mongo')->assertFailed();
    }
}
