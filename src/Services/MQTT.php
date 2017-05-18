<?php

namespace a15lam\MQTT\Services;

use a15lam\MQTT\Components\MosquittoClient;

class MQTT extends BaseService
{
    protected function setClient($config)
    {
        $host = array_get($config, 'host');
        $port = array_get($config, 'port');
        $clientId = array_get($config, 'client_id', 'df-client-' . time());
        $username = array_get($config, 'username');
        $password = array_get($config, 'password');
        $useTls = array_get($config, 'use_tls');
        $capath = array_get($config, 'capath');

        $this->client = new MosquittoClient($host, $port, $clientId, $username, $password);
        if($useTls && !empty($capath)){
            $this->client->setCAPath($capath);
        }
    }

    protected function handlePOST()
    {
        $topic = $this->request->input('topic');
        $message = (string) $this->request->input('msg', $this->request->input('message'));

        $this->client->publish($topic, $message);

        return ['success' => true];
    }
}