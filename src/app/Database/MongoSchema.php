<?php
namespace Andmarruda\Lpb\Database;

use Andmarruda\Lpb\Models\Mongo\GlobalSetting;
use Andmarruda\Lpb\Models\Mongo\Media;
use Andmarruda\Lpb\Models\Mongo\Menu;
use Andmarruda\Lpb\Models\Mongo\Page;
use Andmarruda\Lpb\Models\Mongo\Revision;
use Andmarruda\Lpb\Models\Mongo\Template;
use Andmarruda\Lpb\ValueObjects\PublishState;
use Andmarruda\Lpb\ValueObjects\TemplateStatus;

/**
 * $jsonSchema validators and indexes for the MongoDB collections.
 *
 * See docs/MONGODB_SCHEMA.md for the embedding decisions behind them.
 */
final class MongoSchema
{
    private const SLUG_PATTERN = '^[a-z0-9]+(?:-[a-z0-9]+)*$';

    /**
     * Collection definitions keyed by collection name.
     *
     * @param string $embeddedId Key of embedded ids: '_id' when the connection
     *                           renames embedded "id" fields (the default), else 'id'
     * @return array<string, array{validator: array, indexes: array<int, array>}>
     */
    public static function collections(string $embeddedId = '_id'): array
    {
        $string = ['bsonType' => 'string'];
        $nullableString = ['bsonType' => ['string', 'null']];
        $slug = ['bsonType' => 'string', 'pattern' => self::SLUG_PATTERN];
        $order = ['bsonType' => ['int', 'long'], 'minimum' => 0];
        $nullableObject = ['bsonType' => ['object', 'null']];

        return [
            (new Page())->getTable() => [
                'validator' => self::schema(['title', 'slug', 'status'], [
                    'title' => $string,
                    'slug' => $slug,
                    'status' => ['enum' => array_column(PublishState::cases(), 'value')],
                    'template_id' => $nullableString,
                    'template_slug' => ['bsonType' => ['string', 'null'], 'pattern' => self::SLUG_PATTERN],
                    'seo' => $nullableObject,
                    'metatags' => self::listOf(['name', 'content'], [
                        'name' => $string,
                        'content' => $string,
                    ]),
                    'blocks' => self::listOf([$embeddedId, 'type', 'order', 'widget_instances'], [
                        $embeddedId => $string,
                        'parent_id' => $nullableString,
                        'type' => $string,
                        'order' => $order,
                        'widget_instances' => self::listOf([$embeddedId, 'widget', 'order'], [
                            $embeddedId => $string,
                            'widget' => $string,
                            'order' => $order,
                        ]),
                    ]),
                ]),
                'indexes' => [
                    ['key' => ['slug' => 1], 'name' => 'uq_pages_slug', 'unique' => true],
                    ['key' => ['status' => 1], 'name' => 'idx_pages_status'],
                    ['key' => ['template_id' => 1], 'name' => 'idx_pages_template'],
                    ['key' => ["blocks.{$embeddedId}" => 1], 'name' => 'idx_pages_blocks_id'],
                ],
            ],
            (new Template())->getTable() => [
                'validator' => self::schema(['name', 'slug', 'view_path', 'is_default', 'status'], [
                    'name' => $string,
                    'slug' => $slug,
                    'description' => $nullableString,
                    'view_path' => $string,
                    'is_default' => ['bsonType' => 'bool'],
                    'status' => ['enum' => array_column(TemplateStatus::cases(), 'value')],
                    'seo' => $nullableObject,
                ]),
                'indexes' => [
                    ['key' => ['slug' => 1], 'name' => 'uq_templates_slug', 'unique' => true],
                    ['key' => ['status' => 1], 'name' => 'idx_templates_status'],
                    ['key' => ['is_default' => 1], 'name' => 'idx_templates_default'],
                ],
            ],
            (new Menu())->getTable() => [
                'validator' => self::schema(['name', 'slug', 'location'], [
                    'name' => $string,
                    'slug' => $slug,
                    'location' => $string,
                    'items' => self::listOf([$embeddedId, 'label', 'url', 'target', 'order'], [
                        $embeddedId => $string,
                        'parent_id' => $nullableString,
                        'label' => $string,
                        'url' => $string,
                        'target' => ['enum' => ['_self', '_blank', '_parent', '_top']],
                        'order' => $order,
                    ]),
                ]),
                'indexes' => [
                    ['key' => ['slug' => 1], 'name' => 'uq_menus_slug', 'unique' => true],
                    ['key' => ['location' => 1, '_id' => 1], 'name' => 'idx_menus_location'],
                    ['key' => ["items.{$embeddedId}" => 1], 'name' => 'idx_menus_items_id'],
                ],
            ],
            (new Media())->getTable() => [
                'validator' => self::schema(['disk', 'path', 'filename', 'mime_type', 'size'], [
                    'disk' => $string,
                    'path' => $string,
                    'filename' => $string,
                    'mime_type' => $string,
                    'size' => ['bsonType' => ['int', 'long'], 'minimum' => 0],
                    // An empty PHP array is stored as a BSON array
                    'meta' => ['bsonType' => ['object', 'array', 'null']],
                    'collection' => $nullableString,
                ]),
                'indexes' => [
                    ['key' => ['disk' => 1, 'path' => 1], 'name' => 'uq_media_disk_path', 'unique' => true],
                    ['key' => ['mime_type' => 1], 'name' => 'idx_media_mime_type'],
                    ['key' => ['collection' => 1, 'filename' => 1], 'name' => 'idx_media_collection'],
                    ['key' => ['filename' => 1], 'name' => 'idx_media_filename'],
                ],
            ],
            (new Revision())->getTable() => [
                'validator' => self::schema(['revisionable_type', 'revisionable_id', 'payload', 'status'], [
                    'revisionable_type' => $string,
                    'revisionable_id' => $string,
                    'payload' => ['bsonType' => ['object', 'array']],
                    'status' => ['enum' => array_column(PublishState::cases(), 'value')],
                    'created_by' => $nullableString,
                ]),
                'indexes' => [
                    ['key' => ['revisionable_type' => 1, 'revisionable_id' => 1, 'created_at' => -1], 'name' => 'idx_revisions_revisionable'],
                ],
            ],
            (new GlobalSetting())->getTable() => [
                'validator' => self::schema(['key'], [
                    'key' => $string,
                ]),
                'indexes' => [
                    ['key' => ['key' => 1], 'name' => 'uq_global_settings_key', 'unique' => true],
                ],
            ],
        ];
    }

    /**
     * @param string[] $required
     * @param array $properties
     * @return array
     */
    private static function schema(array $required, array $properties): array
    {
        return ['bsonType' => 'object', 'required' => $required, 'properties' => $properties];
    }

    /**
     * An optional array of embedded documents.
     *
     * @param string[] $required
     * @param array $properties
     * @return array
     */
    private static function listOf(array $required, array $properties): array
    {
        return ['bsonType' => 'array', 'items' => self::schema($required, $properties)];
    }
}
