<?php

namespace Andmarruda\Lpb\Tests\Unit\ValueObjects;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Exceptions\InvalidStateTransitionException;
use Andmarruda\Lpb\ValueObjects\PublishState;

/**
 * Unit tests for the PublishState enum
 */
class PublishStateTest extends TestCase
{
    /**
     * Test allowed transitions return the next state
     */
    #[DataProvider('allowedTransitions')]
    public function test_allowed_transition(PublishState $from, PublishState $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
        $this->assertSame($to, $from->transitionTo($to));
    }

    public static function allowedTransitions(): array
    {
        return [
            'draft to published' => [PublishState::Draft, PublishState::Published],
            'draft to archived' => [PublishState::Draft, PublishState::Archived],
            'published to draft' => [PublishState::Published, PublishState::Draft],
            'published to archived' => [PublishState::Published, PublishState::Archived],
            'archived to draft' => [PublishState::Archived, PublishState::Draft],
        ];
    }

    /**
     * Test illegal transitions throw
     */
    #[DataProvider('illegalTransitions')]
    public function test_illegal_transition_throws(PublishState $from, PublishState $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));

        $this->expectException(InvalidStateTransitionException::class);
        $from->transitionTo($to);
    }

    public static function illegalTransitions(): array
    {
        return [
            'archived to published' => [PublishState::Archived, PublishState::Published],
            'draft to draft' => [PublishState::Draft, PublishState::Draft],
            'published to published' => [PublishState::Published, PublishState::Published],
            'archived to archived' => [PublishState::Archived, PublishState::Archived],
        ];
    }

    /**
     * Test only draft is publishable
     */
    public function test_is_publishable(): void
    {
        $this->assertTrue(PublishState::Draft->isPublishable());
        $this->assertFalse(PublishState::Published->isPublishable());
        $this->assertFalse(PublishState::Archived->isPublishable());
    }

    /**
     * Test only published is visible
     */
    public function test_is_visible(): void
    {
        $this->assertTrue(PublishState::Published->isVisible());
        $this->assertFalse(PublishState::Draft->isVisible());
        $this->assertFalse(PublishState::Archived->isVisible());
    }

    /**
     * Test backing values match the stored status strings
     */
    public function test_backing_values(): void
    {
        $this->assertSame(['draft', 'published', 'archived'], array_column(PublishState::cases(), 'value'));
    }
}
