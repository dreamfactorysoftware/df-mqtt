<?php

namespace DreamFactory\Core\MQTT\Services;

use DreamFactory\Core\MQTT\Components\MosquittoClient;
use DreamFactory\Core\MQTT\Resources\Pub;
use DreamFactory\Core\MQTT\Resources\Sub;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\PubSub\Services\PubSub;

class MQTT extends PubSub
{
    const QUEUE_TYPE = 'MQTT';

    /** @type array Service Resources */
    protected static $resources = [
        Pub::RESOURCE_NAME => [
            'name'       => Pub::RESOURCE_NAME,
            'class_name' => Pub::class,
            'label'      => 'Publish'
        ],
        Sub::RESOURCE_NAME => [
            'name'       => Sub::RESOURCE_NAME,
            'class_name' => Sub::class,
            'label'      => 'Subscribe'
        ]
    ];

    /**
     * Sets the client component
     *
     * @param array $config
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function setClient($config)
    {
        if (empty($config)) {
            throw new InternalServerErrorException('No service configuration found for MQTT service.');
        }
        $host = array_get($config, 'host');
        $port = array_get($config, 'port');
        $clientId = array_get($config, 'client_id', 'df-client-' . time());
        $username = array_get($config, 'username');
        $password = array_get($config, 'password');
        $useTls = array_get($config, 'use_tls');
        $capath = array_get($config, 'capath');
        if (!$useTls) {
            $capath = null;
        }

        $this->client = new MosquittoClient($host, $port, $clientId, $username, $password, $capath);
    }

    /** {@inheritdoc} */
    public function getQueueType()
    {
        return static::QUEUE_TYPE;
    }
}