<?php

namespace DreamFactory\Core\MQTT\Services;

use DreamFactory\Core\MQTT\Components\MosquittoClient;
use DreamFactory\Core\MQTT\Resources\Pub;
use DreamFactory\Core\MQTT\Resources\Sub;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Utility\Session;

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

    /** @inheritdoc */
    public function getResources($only_handlers = false)
    {
        return ($only_handlers) ? static::$resources : array_values(static::$resources);
    }

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

    /** @inheritdoc */
    public static function getApiDocInfo($service)
    {
        $base = parent::getApiDocInfo($service);

        $apis = [];
        $models = [];
        foreach (static::$resources as $resourceInfo) {
            $resourceClass = array_get($resourceInfo, 'class_name');

            if (!class_exists($resourceClass)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $resourceClass);
            }

            $resourceName = array_get($resourceInfo, static::RESOURCE_IDENTIFIER);
            if (Session::checkForAnyServicePermissions($service->name, $resourceName)) {
                $results = $resourceClass::getApiDocInfo($service->name, $resourceInfo);
                if (isset($results, $results['paths'])) {
                    $apis = array_merge($apis, $results['paths']);
                }
                if (isset($results, $results['definitions'])) {
                    $models = array_merge($models, $results['definitions']);
                }
            }
        }

        $base['paths'] = array_merge($base['paths'], $apis);
        $base['definitions'] = array_merge($base['definitions'], $models);
        unset($base['paths']['/' . $service->name]['get']['parameters']);

        return $base;
    }
}