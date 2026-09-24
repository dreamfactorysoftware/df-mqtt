<?php

namespace DreamFactory\Core\MQTT\Resources;

use DreamFactory\Core\Exceptions\BadRequestException;

class Pub extends \DreamFactory\Core\PubSub\Resources\Pub
{
    /**
     * {@inheritdoc}
     */
    protected function handlePOST()
    {
        $topic = $this->request->input('topic');
        self::assertSafePublishTopic($topic);
        $message = $this->request->input('msg', $this->request->input('message'));
        $message = (is_array($message)) ? json_encode($message) : (string)$message;

        $this->parent->getClient()->publish(['topic' => $topic, 'message' => $message]);

        return ['success' => true];
    }

    /**
     * Validate an MQTT topic for the publish-side syntax.
     *
     * Without validation a caller could publish to ANY topic the broker
     * accepts (including admin topics or topics outside the service's
     * intended namespace). MQTT publish topics must not contain
     * wildcards (those are subscribe-only) or NUL bytes.
     *
     * @throws BadRequestException
     */
    public static function assertSafePublishTopic($topic): void
    {
        if (!is_string($topic) || $topic === '') {
            throw new BadRequestException('MQTT topic is required.');
        }
        if (strlen($topic) > 65535) {
            throw new BadRequestException('MQTT topic exceeds 64 KiB.');
        }
        if (str_contains($topic, "\0")) {
            throw new BadRequestException('MQTT topic must not contain NUL bytes.');
        }
        if (str_contains($topic, '+') || str_contains($topic, '#')) {
            throw new BadRequestException('MQTT topic must not contain wildcards (+ / #) on publish.');
        }
    }
}