<?php

namespace Andmarruda\Lpb\Tests\Unit\Widgets;

use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\Widgets\Schema;

/**
 * Unit tests for the fluent widget Schema builder
 */
class SchemaTest extends TestCase
{
    /**
     * Test fields, modifiers and nested lists produce JSON Schema 2020-12
     */
    public function test_builds_json_schema(): void
    {
        $schema = Schema::make()
            ->string('title')->required()->maxLength(120)->label('Title')->help('Shown on top')
            ->integer('count')->min(1)->max(5)->default(3)
            ->enum('align', ['left', 'center'])
            ->array('buttons', fn (Schema $b) => $b->string('label')->required())->max(3)
            ->boolean('visible')
            ->toArray();

        $this->assertSame(Schema::DIALECT, $schema['$schema']);
        $this->assertSame('object', $schema['type']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame(['title'], $schema['required']);
        $this->assertSame(['type' => 'string', 'maxLength' => 120, 'title' => 'Title', 'description' => 'Shown on top'], $schema['properties']['title']);
        $this->assertSame(['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'default' => 3], $schema['properties']['count']);
        $this->assertSame(['enum' => ['left', 'center']], $schema['properties']['align']);
        $this->assertSame(3, $schema['properties']['buttons']['maxItems']);
        $this->assertSame(['label'], $schema['properties']['buttons']['items']['required']);
        $this->assertFalse($schema['properties']['buttons']['items']['additionalProperties']);
    }

    /**
     * Test min and max map to string lengths for strings
     */
    public function test_string_bounds_are_lengths(): void
    {
        $schema = Schema::make()->string('code')->min(2)->max(4)->toArray();

        $this->assertSame(['type' => 'string', 'minLength' => 2, 'maxLength' => 4], $schema['properties']['code']);
    }

    /**
     * Test media, link and rich text fields carry their editor annotation
     */
    public function test_special_fields(): void
    {
        $properties = Schema::make()->media('image')->link('cta')->richText('body')->toArray()['properties'];

        $this->assertSame('media', $properties['image']['x-lpb-type']);
        $this->assertSame(['$media'], $properties['image']['required']);
        $this->assertSame('link', $properties['cta']['x-lpb-type']);
        $this->assertSame(['url'], $properties['cta']['required']);
        $this->assertSame(['type' => 'string', 'x-lpb-type' => 'richText'], $properties['body']);
    }

    /**
     * Test an empty schema is an object without properties and a raw schema is kept
     */
    public function test_empty_and_raw_schemas(): void
    {
        $empty = Schema::make()->toArray();
        $this->assertInstanceOf(\stdClass::class, $empty['properties']);

        $raw = Schema::raw(['type' => 'object', 'properties' => ['a' => ['type' => 'string']]])->toArray();
        $this->assertSame(['a' => ['type' => 'string']], $raw['properties']);
        $this->assertSame(Schema::DIALECT, $raw['$schema']);
    }

    /**
     * Test a modifier without a field and reserved field names are rejected
     */
    public function test_rejects_misuse(): void
    {
        try {
            Schema::make()->required();
            $this->fail('A modifier without a field was accepted');
        } catch (InvalidValueException) {
        }

        $this->expectException(InvalidValueException::class);
        Schema::make()->string('$media');
    }
}
