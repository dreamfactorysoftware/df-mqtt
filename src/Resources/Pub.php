<?php

namespace a15lam\MQTT\Resources;

use DreamFactory\Core\Resources\BaseRestResource;

class Pub extends BaseRestResource
{
    const RESOURCE_NAME = 'pub';

    /** @var \a15lam\MQTT\Services\MQTT */
    protected $parent;

    /**
     * {@inheritdoc}
     */
    protected function handlePOST()
    {
        $topic = $this->request->input('topic');
        $message = $this->request->input('msg', $this->request->input('message'));
        $message = (is_array($message)) ? json_encode($message) : (string)$message;

        $this->parent->getClient()->publish($topic, $message);

        return ['success' => true];
    }
}