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
    public static function getApiDocInfo($service, array $resource = [])
    {
        $base = parent::getApiDocInfo($service, $resource);
        $serviceName = strtolower($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        unset($base['paths'][$path]['get']);
        $base['paths'][$path]['post'] = [
            'tags'        => [$serviceName],
            'summary'     => 'publishMessage() - Publish message',
            'operationId' => 'publishMessage',
            'consumes'    => ['application/json', 'application/xml'],
            'produces'    => ['application/json', 'application/xml'],
            'description' => 'Publishes message to MQTT broker',
            'parameters'  => [
                [
                    'name'        => 'body',
                    'description' => 'Content - Message and topic to publish to',
                    'schema'      => [
                        'type'       => 'object',
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
                    'in'          => 'body',
                    'required'    => true
                ]
            ],
            'responses'   => [
                '200'     => [
                    'description' => 'Success',
                    'schema'      => [
                        'type'       => 'object',
                        'properties' => [
                            'success' => ['type' => 'boolean']
                        ]
                    ]
                ],
                'default' => [
                    'description' => 'Error',
                    'schema'      => ['$ref' => '#/definitions/Error']
                ]
            ],
        ];

        return $base;
    }
}