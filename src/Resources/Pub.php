<?php

namespace DreamFactory\Core\MQTT\Resources;

use DreamFactory\Core\Resources\BaseRestResource;

class Pub extends BaseRestResource
{
    const RESOURCE_NAME = 'pub';

    /** A resource identifier used in swagger doc. */
    const RESOURCE_IDENTIFIER = 'name';

    /** @var \DreamFactory\Core\MQTT\Services\MQTT */
    protected $parent;

    /**
     * {@inheritdoc}
     */
    protected static function getResourceIdentifier()
    {
        return static::RESOURCE_IDENTIFIER;
    }

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

    /** {@inheritdoc} */
    protected function getApiDocPaths()
    {
        $resourceName = strtolower($this->name);
        $path = '/' . $resourceName;
        $base = [
            $path => [
                'post' => [
                    'summary'     => 'publishMessage() - Publish message',
                    'operationId' => 'publishMessage',
                    'description' => 'Publishes message to MQTT broker',
                    'requestBody' => [
                        'description' => 'Content - Message and topic to publish to',
                        'schema'      => [
                            'type'       => 'object',
                            'required'   => ['topic', 'message'],
                            'properties' => [
                                'topic'   => [
                                    'type'        => 'string',
                                    'description' => 'Topic name'
                                ],
                                'message' => [
                                    'type'        => 'string',
                                    'description' => 'Payload message'
                                ],
                            ]
                        ],
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/Success']
                    ],
                ],
            ],
        ];

        return $base;
    }
}