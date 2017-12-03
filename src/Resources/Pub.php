<?php

namespace DreamFactory\Core\MQTT\Resources;

class Pub extends \DreamFactory\Core\PubSub\Resources\Pub
{
    /**
     * {@inheritdoc}
     */
    protected function handlePOST()
    {
        $topic = $this->request->input('topic');
        $message = $this->request->input('msg', $this->request->input('message'));
        $message = (is_array($message)) ? json_encode($message) : (string)$message;

        $this->parent->getClient()->publish(['topic' => $topic, 'message' => $message]);

        return ['success' => true];
    }
}