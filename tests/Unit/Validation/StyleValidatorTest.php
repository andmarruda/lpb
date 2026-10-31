<?php

namespace Andmarruda\Lpb\Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Exceptions\TreeValidationException;
use Andmarruda\Lpb\Validation\StyleValidator;

/**
 * Unit tests for style variant validation
 */
class StyleValidatorTest extends TestCase
{
    private function validator(): StyleValidator
    {
        return new StyleValidator(['desktop', 'tablet', 'mobile']);
    }

    /**
     * Test valid variants are normalised with explicit breakpoint and state
     */
    public function test_accepts_and_normalises_variants(): void
    {
        $styles = $this->validator()->assertValid([
            ['props' => ['gap' => '24px', 'flex-grow' => 1, '--accent' => ['$var' => 'color-primary']]],
            ['breakpoint' => 'mobile', 'state' => 'hover', 'props' => []],
        ]);

        $this->assertSame([
            ['breakpoint' => null, 'state' => null, 'props' => ['gap' => '24px', 'flex-grow' => 1, '--accent' => ['$var' => 'color-primary']]],
            ['breakpoint' => 'mobile', 'state' => 'hover', 'props' => []],
        ], $styles);
        $this->assertSame([], $this->validator()->assertValid(null));
    }

    /**
     * Test invalid styles are rejected with the path of the problem
     */
    #[DataProvider('invalidStyles')]
    public function test_rejects_invalid_styles(mixed $styles, string $path): void
    {
        try {
            $this->validator()->assertValid($styles, '/styles');
            $this->fail('Invalid styles were accepted');
        } catch (TreeValidationException $e) {
            $this->assertSame($path, $e->errors()[0]['path']);
        }
    }

    public static function invalidStyles(): array
    {
        return [
            'not a list' => [['props' => []], '/styles'],
            'unknown breakpoint' => [[['breakpoint' => 'watch', 'props' => []]], '/styles/0/breakpoint'],
            'unknown state' => [[['state' => 'visited', 'props' => []]], '/styles/0/state'],
            'unknown key' => [[['media' => 'print', 'props' => []]], '/styles/0/media'],
            'property case' => [[['props' => ['fontSize' => '1rem']]], '/styles/0/props/fontSize'],
            'declaration escape' => [[['props' => ['color' => 'red;}body{x:y']]], '/styles/0/props/color'],
            'comment' => [[['props' => ['color' => 'red/*x*/']]], '/styles/0/props/color'],
            'bad token' => [[['props' => ['color' => ['$var' => 'Primary Color']]]], '/styles/0/props/color/$var'],
            'other reference' => [[['props' => ['color' => ['$media' => 1]]]], '/styles/0/props/color'],
            'duplicate variant' => [[['props' => []], ['breakpoint' => null, 'props' => []]], '/styles/1'],
        ];
    }
}
