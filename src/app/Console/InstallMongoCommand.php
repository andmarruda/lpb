<?php
namespace Andmarruda\Lpb\Console;

use Andmarruda\Lpb\Database\MongoSchema;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use MongoDB\Driver\Exception\Exception as MongoException;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Creates the MongoDB collections with $jsonSchema validators and indexes.
 *
 * Safe to re-run: existing collections get their validator replaced and
 * existing indexes with the same definition are left as they are.
 */
class InstallMongoCommand extends Command
{
    protected $signature = 'lpb:install-mongo
        {--connection= : MongoDB connection name (defaults to laravel-page-builder.connections.mongodb)}';

    protected $description = 'Create the Laravel Page Builder MongoDB collections, validators and indexes';

    public function handle(DatabaseManager $db): int
    {
        $name = $this->option('connection') ?: config('laravel-page-builder.connections.mongodb', 'mongodb');

        try {
            $connection = $db->connection($name);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (!$connection instanceof MongoConnection) {
            $this->error("Connection [{$name}] is not a MongoDB connection. Install mongodb/laravel-mongodb and configure it in config/database.php.");
            return self::FAILURE;
        }

        $database = $connection->getDatabase();
        $existing = iterator_to_array($database->listCollectionNames(), false);
        $embeddedId = $connection->getRenameEmbeddedIdField() ? '_id' : 'id';
        $failed = false;

        foreach (MongoSchema::collections($embeddedId) as $collection => $definition) {
            $options = [
                'validator' => ['$jsonSchema' => $definition['validator']],
                // Existing documents that do not match can still be updated
                'validationLevel' => 'moderate',
                'validationAction' => 'error',
            ];

            if (in_array($collection, $existing, true)) {
                $database->command(['collMod' => $collection] + $options);
                $this->line("Updated validator: {$collection}");
            } else {
                $database->createCollection($collection, $options);
                $this->line("Created collection: {$collection}");
            }

            foreach ($definition['indexes'] as $index) {
                try {
                    $database->selectCollection($collection)->createIndex($index['key'], array_diff_key($index, ['key' => true]));
                } catch (MongoException $e) {
                    $this->error("Index {$index['name']} on {$collection} failed: {$e->getMessage()}");
                    $failed = true;
                }
            }
        }

        if ($failed) {
            $this->error('Some indexes could not be created; fix the data above and run the command again.');
            return self::FAILURE;
        }

        $this->info('MongoDB collections, validators and indexes are installed.');
        return self::SUCCESS;
    }
}
