<?php

namespace DreamFactory\Core\MQTT\Services;

use DreamFactory\Core\MQTT\Components\MosquittoClient;
use DreamFactory\Core\MQTT\Resources\Pub;
use DreamFactory\Core\MQTT\Resources\Sub;

class MQTT extends BaseService
{
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
     */
    protected function setClient($config)
    {
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

    /**
     * Returns the client component
     *
     * @return \DreamFactory\Core\MQTT\Components\MosquittoClient
     */
    public function getClient()
    {
        return $this->client;
    }
}