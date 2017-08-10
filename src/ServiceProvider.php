<?php

namespace DreamFactory\Core\MQTT;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\MQTT\Models\MQTTConfig;
use DreamFactory\Core\MQTT\Services\MQTT;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        // Add our service types.
        $this->app->resolving('df.service', function (ServiceManager $df){
            $df->addType(
                new ServiceType([
                    'name'            => 'mqtt',
                    'label'           => 'MQTT Client',
                    'description'     => 'MQTT Client based on Mosquitto',
                    'group'           => ServiceTypeGroups::IOT,
                    'config_handler'  => MQTTConfig::class,
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