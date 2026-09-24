<?php

namespace DreamFactory\Core\MQTT\Tests\Security;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\MQTT\Resources\Pub;
use PHPUnit\Framework\TestCase;

class PublishTopicValidationTest extends TestCase
{
    /** @dataProvider validProvider */
    public function testValidTopicAccepted(string $topic): void
    {
        Pub::assertSafePublishTopic($topic);
        $this->assertTrue(true);
    }

    public static function validProvider(): array
    {
        return [
            'simple'      => ['sensors/temp'],
            'leading'     => ['/devices/42/state'],
            'segments'    => ['a/b/c/d'],
        ];
    }

    /** @dataProvider invalidProvider */
    public function testInvalidTopicRejected($topic): void
    {
        $this->expectException(BadRequestException::class);
        Pub::assertSafePublishTopic($topic);
    }

    public static function invalidProvider(): array
    {
        return [
            'empty'         => [''],
            'null'          => [null],
            'plus wildcard' => ['sensors/+/state'],
            'hash wildcard' => ['sensors/#'],
            'nul byte'      => ["sensors/\0/x"],
            'array'         => [['x']],
        ];
    }
}
