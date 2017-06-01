<?php

namespace DreamFactory\Core\MQTT;

use DreamFactory\Core\MQTT\Models\MQTTConfig;
use DreamFactory\Core\MQTT\Services\MQTT;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use DreamFactory\Core\Components\ServiceDocBuilder;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    use ServiceDocBuilder;

    public function register()
    {
        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df){
            $df->addType(
                new ServiceType([
                    'name'            => 'mqtt',
                    'label'           => 'MQTT Client',
                    'description'     => 'MQTT Client based on Mosquitto',
                    'group'           => 'IOT',
                    'config_handler'  => MQTTConfig::class,
                    'default_api_doc' => function ($service){
                        return $this->buildServiceDoc($service->id, MQTT::getApiDocInfo($service));
                    },
                    'factory'         => function ($config){
                        return new MQTT($config);
                    },
                ])
            );
        });
    }

    public function boot()
    {
        // add migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

}