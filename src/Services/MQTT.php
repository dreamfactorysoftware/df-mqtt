<?php

namespace DreamFactory\Core\MQTT\Services;

use DreamFactory\Core\MQTT\Components\MosquittoClient;
use DreamFactory\Core\MQTT\Resources\Pub;
use DreamFactory\Core\MQTT\Resources\Sub;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\PubSub\Services\PubSub;
use Arr;

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
        $host = Arr::get($config, 'host');
        $port = Arr::get($config, 'port');
        $clientId = Arr::get($config, 'client_id', 'df-client-' . time());
        $username = Arr::get($config, 'username');
        $password = Arr::get($config, 'password');
        $useTls = Arr::get($config, 'use_tls');
        $capath = Arr::get($config, 'capath');
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