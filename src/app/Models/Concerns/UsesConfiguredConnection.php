<?php
namespace Andmarruda\Lpb\Models\Concerns;

use MongoDB\Laravel\Eloquent\Model as MongoModel;

/**
 * Resolves the model's connection from laravel-page-builder.connections
 * (sql or mongodb), falling back to the model's own connection.
 */
trait UsesConfiguredConnection
{
    public function getConnectionName()
    {
        $key = $this instanceof MongoModel ? 'mongodb' : 'sql';
        $configured = app()->bound('config') ? config("laravel-page-builder.connections.{$key}") : null;

        return $configured ?: parent::getConnectionName();
    }
}
