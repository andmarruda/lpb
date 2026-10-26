<?php

namespace Andmarruda\Lpb\Tests\Unit\Casts;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Eloquent\Model;
use Andmarruda\Lpb\Casts\SeoCast;
use Andmarruda\Lpb\Exceptions\InvalidValueException;
use Andmarruda\Lpb\ValueObjects\SEO;

/**
 * Unit tests for the SeoCast attribute cast
 */
class SeoCastTest extends TestCase
{
    protected Model $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = new class extends Model {};
    }

    /**
     * Test JSON storage roundtrip
     */
    public function test_json_storage(): void
    {
        $cast = new SeoCast();
        $seo = new SEO(title: 'Home');

        $stored = $cast->set($this->model, 'seo', $seo, []);

        $this->assertSame(['seo' => '{"title":"Home"}'], $stored);
        $this->assertTrue($cast->get($this->model, 'seo', $stored['seo'], [])->equals($seo));
    }

    /**
     * Test document storage roundtrip
     */
    public function test_document_storage(): void
    {
        $cast = new SeoCast('document');

        $stored = $cast->set($this->model, 'seo', ['title' => 'Home', 'robots' => 'noindex'], []);

        $this->assertSame(['seo' => ['title' => 'Home', 'robots' => 'noindex']], $stored);
        $this->assertSame('noindex', $cast->get($this->model, 'seo', $stored['seo'], [])->robots);
    }

    /**
     * Test null and empty SEO are stored as null
     */
    public function test_null_and_empty_are_null(): void
    {
        $cast = new SeoCast();

        $this->assertSame(['seo' => null], $cast->set($this->model, 'seo', null, []));
        $this->assertSame(['seo' => null], $cast->set($this->model, 'seo', new SEO(), []));
        $this->assertNull($cast->get($this->model, 'seo', null, []));
    }

    /**
     * Test serialisation for toArray()
     */
    public function test_serialize(): void
    {
        $cast = new SeoCast();

        $this->assertSame(['title' => 'Home'], $cast->serialize($this->model, 'seo', new SEO(title: 'Home'), []));
        $this->assertNull($cast->serialize($this->model, 'seo', null, []));
    }

    /**
     * Test unsupported values are rejected
     */
    public function test_rejects_unsupported_value(): void
    {
        $this->expectException(InvalidValueException::class);

        (new SeoCast())->set($this->model, 'seo', 'Home', []);
    }
}
