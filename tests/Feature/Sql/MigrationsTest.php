<?php

namespace Andmarruda\Lpb\Tests\Feature\Sql;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * SQL migrations roll back and re-apply cleanly
 *
 * Runs outside RefreshDatabase and leaves the schema migrated, so the
 * other test classes are unaffected.
 */
class MigrationsTest extends TestCase
{
    private const TABLES = [
        'lpb_global_settings',
        'lpb_templates',
        'lpb_pages',
        'lpb_metatags',
        'lpb_blocks',
        'lpb_widget_instances',
        'lpb_menus',
        'lpb_menu_items',
        'lpb_media',
        'lpb_revisions',
    ];

    /**
     * Test every package table is dropped on reset and recreated on migrate
     */
    public function test_migrations_reset_and_reapply(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} was not created");
        }

        $this->artisan('migrate:reset', ['--force' => true])->assertSuccessful();
        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} was not dropped");
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "{$table} was not recreated");
        }
    }
}
