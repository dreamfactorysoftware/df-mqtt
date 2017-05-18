<?php

namespace a15lam\MQTT\Components;

use a15lam\MQTT\Exceptions\LoopException;
use Log;

class MosquittoClient
{
    protected $client;

    protected $host;

    protected $port = 1883;

    public function __construct($host, $port, $clientId, $username = null, $password = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->client = new \Mosquitto\Client($clientId);
        if (!empty($username) && !empty($password)) {
            $this->client->setCredentials($username, $password);
        }
    }

    public function setCAPath($path)
    {
        $this->client->setTlsCertificates($path);
    }

    public function publish($topic, $msg)
    {
        $client = $this->client;
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
        $this->client = $client;
        $this->client->connect($this->host, $this->port);
        // Subscribing to the published topic in order to
        // successfully terminate the execution loop upon
        // checking the reception of the published topic/message.
        $this->client->subscribe($topic, 0);
        $this->execute();
    }

    protected function execute()
    {
        while (1) {
            try {
                $this->client->loop();
            } catch (LoopException $e) {
                return;
            }
        }
        $this->client->disconnect();
    }

    public function __destruct()
    {
        unset($this->client);
    }
}