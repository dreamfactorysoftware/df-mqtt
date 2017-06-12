<?php

namespace DreamFactory\Core\MQTT\Models;

use DreamFactory\Core\Models\BaseServiceConfigModel;

class MQTTConfig extends BaseServiceConfigModel
{
    /** @var string */
    protected $table = 'mqtt_config';

    /** @var array */
    protected $fillable = [
        'service_id',
        'host',
        'port',
        'client_id',
        'username',
        'password',
        'use_tls',
        'capath',
        'subscription'
    ];

    /** @var array */
    protected $casts = [
        'service_id' => 'integer',
        'port'       => 'integer',
        'use_tls'    => 'boolean'
    ];

    /** @var array */
    protected $encrypted = ['password'];

    /** @var array */
    protected $protected = ['password'];

    /**
     * {@inheritdoc}
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'host':
                $schema['label'] = 'Broker Host';
                $schema['description'] = 'Host name or IP address of your MQTT broker.';
                break;
            case 'port':
                $schema['label'] = 'Broker Port';
                $schema['default'] = 1883;
                $schema['description'] = 'Port number of your MQTT broker.';
                break;
            case 'client_id':
                $schema['label'] = 'Client ID';
                $schema['description'] =
                    'Client ID used for this connection. Make sure this is unique for all of your clients connecting to broker.';
                break;
            case 'username':
                $schema['label'] = 'Username';
                $schema['description'] = 'Provide username if your broker requires authentication.';
                break;
            case 'password':
                $schema['type'] = 'password';
                $schema['label'] = 'Password';
                $schema['description'] = 'Provide password for your username if your broker requires authentication.';
                break;
            case 'use_tls':
                $schema['type'] = 'boolean';
                $schema['label'] = 'Use TLS';
                $schema['description'] = 'Check this if your connection requires TLS.';
                break;
            case 'capath':
                $schema['label'] = 'CA Certificate Path';
                $schema['description'] = 'Path to your CA Certificate files.';
                $schema['default'] = '/etc/ssl/certs';
                break;
        }
    }
}