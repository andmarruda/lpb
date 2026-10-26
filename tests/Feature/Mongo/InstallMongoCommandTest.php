<?php

namespace Andmarruda\Lpb\Tests\Feature\Mongo;

use Illuminate\Support\Facades\DB;
use MongoDB\Database;
use MongoDB\Driver\Exception\Exception as MongoException;
use Tests\TestCase;
use Andmarruda\Lpb\Database\MongoSchema;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Repositories\Mongo\PageRepository;

/**
 * lpb:install-mongo command integration tests
 *
 * Every test starts without the package collections and ends with them
 * reinstalled, so the rest of the suite keeps running against validators.
 */
class InstallMongoCommandTest extends TestCase
{
    protected Database $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = DB::connection('mongodb')->getDatabase();
        $this->dropCollections();
    }

    protected function tearDown(): void
    {
        $this->dropCollections();
        $this->artisan('lpb:install-mongo')->run();

        parent::tearDown();
    }

    protected function dropCollections(): void
    {
        foreach (array_keys(MongoSchema::collections()) as $collection) {
            $this->database->dropCollection($collection);
        }
    }

    /**
     * @return array<string, array> Collection options keyed by name
     */
    protected function collectionOptions(): array
    {
        $options = [];
        foreach ($this->database->listCollections() as $info) {
            $options[$info->getName()] = $info->getOptions();
        }
        return $options;
    }

    /**
     * Test the command creates every collection with a validator and its indexes
     */
    public function test_creates_collections_validators_and_indexes(): void
    {
        $this->artisan('lpb:install-mongo')
            ->expectsOutput('MongoDB collections, validators and indexes are installed.')
            ->assertSuccessful();

        $options = $this->collectionOptions();
        foreach (MongoSchema::collections() as $collection => $definition) {
            $this->assertArrayHasKey($collection, $options);
            $this->assertSame('moderate', $options[$collection]['validationLevel']);
            $this->assertArrayHasKey('$jsonSchema', (array) $options[$collection]['validator']);

            $indexes = array_map(fn ($index) => $index->getName(), iterator_to_array($this->database->selectCollection($collection)->listIndexes()));
            foreach ($definition['indexes'] as $index) {
                $this->assertContains($index['name'], $indexes);
            }
        }
    }

    /**
     * Test the command can run again over existing collections
     */
    public function test_is_idempotent(): void
    {
        $this->artisan('lpb:install-mongo')->assertSuccessful();

        $this->artisan('lpb:install-mongo')
            ->expectsOutput('Updated validator: lpb_pages')
            ->assertSuccessful();
    }

    /**
     * Test the unique slug index is enforced
     */
    public function test_enforces_unique_slugs(): void
    {
        $this->artisan('lpb:install-mongo')->assertSuccessful();
        $pages = new PageRepository(new Page());
        $pages->create(['title' => 'Home', 'slug' => 'home']);

        $this->expectException(MongoException::class);
        $pages->create(['title' => 'Copy', 'slug' => 'home']);
    }

    /**
     * Test the validator rejects documents written around the repositories
     */
    public function test_validator_rejects_invalid_documents(): void
    {
        $this->artisan('lpb:install-mongo')->assertSuccessful();

        $this->expectException(MongoException::class);
        $this->database->selectCollection('lpb_pages')->insertOne(['title' => 'Home', 'slug' => 'Not A Slug', 'status' => 'draft']);
    }

    /**
     * Test duplicate data makes the unique index fail and the command report it
     */
    public function test_reports_index_failures(): void
    {
        $this->database->selectCollection('lpb_pages')->insertMany([
            ['title' => 'A', 'slug' => 'home', 'status' => 'draft'],
            ['title' => 'B', 'slug' => 'home', 'status' => 'draft'],
        ]);

        $this->artisan('lpb:install-mongo')
            ->expectsOutputToContain('Index uq_pages_slug on lpb_pages failed')
            ->assertFailed();
    }

    /**
     * Test a missing or non-MongoDB connection fails with a message
     */
    public function test_rejects_unknown_connection(): void
    {
        $this->artisan('lpb:install-mongo', ['--connection' => 'missing'])->assertFailed();
    }

    /**
     * Test models use the configured MongoDB connection
     */
    public function test_models_use_configured_connection(): void
    {
        config(['laravel-page-builder.connections.mongodb' => 'tenant']);

        $this->assertSame('tenant', (new Page())->getConnectionName());
    }
}
