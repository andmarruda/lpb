<?php

namespace Andmarruda\Lpb\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Validation\ConfigValidator;
use Andmarruda\Lpb\Widgets\Schema;

/**
 * Unit tests for widget config validation with opis/json-schema
 */
class ConfigValidatorTest extends TestCase
{
    private function schema(): array
    {
        return Schema::make()
            ->string('title')->required()->maxLength(5)
            ->media('image')
            ->object('meta', fn (Schema $m) => $m->string('note'))
            ->array('buttons', fn (Schema $b) => $b->string('label')->required())
            ->toArray();
    }

    /**
     * Test a valid config has no errors, including an empty nested object
     */
    public function test_valid_config(): void
    {
        $config = ['title' => 'Hi', 'image' => ['$media' => 42], 'meta' => [], 'buttons' => [['label' => 'Go']]];

        $this->assertSame([], (new ConfigValidator())->validate($config, $this->schema()));
    }

    /**
     * Test errors carry JSON pointers prefixed with the config path
     */
    public function test_errors_have_paths(): void
    {
        $errors = (new ConfigValidator())->validate(
            ['title' => 'Too long', 'buttons' => [['label' => 1]], 'image' => ['id' => 4]],
            $this->schema(),
            '/blocks/0/widgets/1/config'
        );

        $paths = array_column($errors, 'path');
        $this->assertContains('/blocks/0/widgets/1/config/title', $paths);
        $this->assertContains('/blocks/0/widgets/1/config/buttons/0/label', $paths);
        $this->assertContains('/blocks/0/widgets/1/config/image', $paths);
    }

    /**
     * Test unknown properties are reported once, without the declared ones
     */
    public function test_unknown_properties(): void
    {
        $errors = (new ConfigValidator())->validate(['title' => 'Too long', 'extra' => 1], $this->schema());

        $unknown = array_values(array_filter($errors, fn (array $e) => str_starts_with($e['message'], 'Unknown')));
        $this->assertCount(1, $unknown);
        $this->assertSame('', $unknown[0]['path']);
        $this->assertSame('Unknown properties: extra.', $unknown[0]['message']);
    }

    /**
     * Test a missing required property is reported at the object
     */
    public function test_required(): void
    {
        $errors = (new ConfigValidator())->validate([], $this->schema(), '/config');

        $this->assertSame('/config', $errors[0]['path']);
        $this->assertStringContainsString('title', $errors[0]['message']);
    }

    /**
     * Test $dynamic references are rejected anywhere in the config
     */
    public function test_rejects_dynamic_references(): void
    {
        $schema = Schema::raw(['type' => 'object'])->toArray();
        $errors = (new ConfigValidator())->validate(['a' => ['b' => ['$dynamic' => 'post.title']]], $schema, '/config');

        $this->assertSame([['path' => '/config/a/b/$dynamic', 'message' => '$dynamic references are not supported yet.']], $errors);
    }
}
