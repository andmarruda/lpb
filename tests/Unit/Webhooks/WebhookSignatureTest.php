<?php

namespace Andmarruda\Lpb\Tests\Unit\Webhooks;

use PHPUnit\Framework\TestCase;
use Andmarruda\Lpb\Webhooks\WebhookSignature;

/**
 * Unit tests for webhook signatures
 */
class WebhookSignatureTest extends TestCase
{
    /**
     * Test a signature verifies with the same body, secret and a fresh timestamp
     */
    public function test_sign_and_verify(): void
    {
        $header = WebhookSignature::header('{"a":1}', 'secret', 1000);

        $this->assertSame('t=1000,v1=' . hash_hmac('sha256', '1000.{"a":1}', 'secret'), $header);
        $this->assertTrue(WebhookSignature::verify('{"a":1}', $header, 'secret', 300, 1100));
    }

    /**
     * Test tampered bodies, wrong secrets, old timestamps and malformed headers fail
     */
    public function test_rejects_invalid_signatures(): void
    {
        $header = WebhookSignature::header('{"a":1}', 'secret', 1000);

        $this->assertFalse(WebhookSignature::verify('{"a":2}', $header, 'secret', 300, 1000));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', $header, 'other', 300, 1000));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', $header, 'secret', 300, 1400));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', 'v1=abc', 'secret', 300, 1000));
        $this->assertFalse(WebhookSignature::verify('{"a":1}', 't=x,v1=abc', 'secret', 300, 1000));
    }
}
