<?php

namespace DreamFactory\Core\MQTT\Components;

use DreamFactory\Core\MQTT\Exceptions\LoopException;
use DreamFactory\Core\MQTT\Jobs\Subscribe;
use Log;

class MosquittoClient
{
    protected $host;

    protected $port = 1883;

    protected $clientId;

    protected $username;

    protected $password;

    protected $caPath;

    public function __construct($host, $port, $clientId, $username = null, $password = null, $caPath = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->clientId = $clientId;
        $this->username = $username;
        $this->password = $password;
        $this->caPath = $caPath;
    }

    public function getConfig($clientIdSuffix = '')
    {
        return [
            'client_id' => $this->clientId . $clientIdSuffix,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'ca_path' => $this->caPath
        ];
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public static function client($config)
    {
        $clientId = array_get($config, 'client_id', 'df-client-' . time());
        $username = array_get($config, 'username');
        $password = array_get($config, 'password');
        $caPath = array_get($config, 'ca_path');
        $client = new \Mosquitto\Client($clientId);
        if (!empty($username) && !empty($password)) {
            $client->setCredentials($username, $password);
        }
        if(!empty($caPath)){
            $client->setTlsCertificates($caPath);
        }

        return $client;
    }

    public function publish($topic, $msg)
    {
        $client = static::client($this->getConfig('-pub'));
        $client->onConnect(function () use ($client, $topic, $msg){
            Log::info('[MQTT] Connected to MQTT broker.');
            Log::debug('[MQTT] Now publishing message: ' . $msg . ' using topic: ' . $topic);
            $client->publish($topic, $msg);
        });
        $client->onMessage(function($m) use ($topic, $msg){
            Log::info('[MQTT] Message received.');
            Log::debug('[MQTT] Received message on topic: ' . $m->topic . ' with payload ' . $m->payload);
            if($topic === $m->topic && $msg === $m->payload){
                throw new LoopException('Publish Completed.');
            }
        });
        $client->connect($this->host, $this->port);
        // Subscribing to the published topic in order to
        // successfully terminate the execution loop upon
        // checking the reception of the published topic/message.
        $client->subscribe($topic, 0);
        while (1) {
            try {
                $client->loop();
            } catch (LoopException $e) {
                $client->disconnect();
                unset($client);
                return;
            }
        }
    }

    public function subscribe($payload)
    {
        $job = new Subscribe($this, $payload);
        $id = dispatch($job);

        return $id;
    }
}