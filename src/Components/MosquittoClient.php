<?php

namespace DreamFactory\Core\MQTT\Components;

use DreamFactory\Core\PubSub\Contracts\MessageQueueInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\MQTT\Exceptions\LoopException;
use DreamFactory\Core\MQTT\Jobs\Subscribe;
use DreamFactory\Core\Enums\Verbs;
use ServiceManager;
use Cache;
use Log;

class MosquittoClient implements MessageQueueInterface
{
    /** @var string */
    protected $host;

    /** @var int */
    protected $port = 1883;

    /** @var string */
    protected $clientId;

    /** @var null|string */
    protected $username;

    /** @var null|string */
    protected $password;

    /** @var null|string */
    protected $caPath;

    /**
     * MosquittoClient constructor.
     *
     * @param string $host
     * @param int    $port
     * @param string $clientId
     * @param null   $username
     * @param null   $password
     * @param null   $caPath
     */
    public function __construct($host, $port, $clientId, $username = null, $password = null, $caPath = null)
    {
        $this->host = $host;
        $this->port = $port;
        $this->clientId = $clientId;
        $this->username = $username;
        $this->password = $password;
        $this->caPath = $caPath;
    }

    /**
     * Fetch the configs
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'client_id' => $this->clientId . time(),
            'host'      => $this->host,
            'port'      => $this->port,
            'username'  => $this->username,
            'password'  => $this->password,
            'ca_path'   => $this->caPath
        ];
    }

    /**
     * Gets the Mosquitto client instance
     *
     * @param $config
     *
     * @return \Mosquitto\Client
     */
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
        if (!empty($caPath)) {
            $client->setTlsCertificates($caPath);
        }

        return $client;
    }

    /**
     * Publishes message
     *
     * @param array $data
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public function publish(array $data)
    {
        $topic = array_get($data, 'topic');
        $msg = array_get($data, 'message', array_get($data, 'msg'));

        if (empty($topic) || empty($msg)) {
            throw new InternalServerErrorException('No topic and/or message supplied for publishing.');
        }

        $client = static::client($this->getConfig());
        $client->onConnect(function () use ($client, $topic, $msg){
            Log::info('[MQTT] Connected to MQTT broker.');
            Log::debug('[MQTT] Now publishing message: ' . $msg . ' using topic: ' . $topic);
            $client->publish($topic, $msg);
        });
        $client->onMessage(function ($m) use ($topic, $msg){
            Log::info('[MQTT] Message received.');
            Log::debug('[MQTT] Received message on topic: ' . $m->topic . ' with payload ' . $m->payload);
            if ($topic === $m->topic && $msg === $m->payload) {
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

    /**
     * Subscribes to topic(s) for incoming messages
     *
     * @param array $payload
     */
    public function subscribe(array $payload)
    {
        $topics = $payload;
        try {
            $client = MosquittoClient::client($this->getConfig());

            $client->onConnect(function (){
                Log::info('[MQTT] Connected to MQTT broker for subscription.');
            });
            $client->onMessage(function ($m) use ($topics){
                Log::debug('[MQTT] Received message on topic: ' . $m->topic . ' with payload ' . $m->payload);

                $service = array_by_key_value($topics, 'topic', $m->topic, 'service');
                Log::debug('[MQTT] Triggering service: ' . json_encode($service));

                // Retrieve service information
                $endpoint = trim(array_get($service, 'endpoint'), '/');
                $endpoint = str_replace('api/v2/', '', $endpoint);
                $endpointArray = explode('/', $endpoint);
                $serviceName = array_get($endpointArray, 0);
                array_shift($endpointArray);
                $resource = implode('/', $endpointArray);
                $verb = strtoupper(array_get($service, 'verb', array_get($service, 'method', Verbs::POST)));
                $params = array_get($service, 'parameter', array_get($service, 'parameters', []));
                $header = array_get($service, 'header', array_get($service, 'headers', []));
                $payload = array_get($service, 'payload', []);
                $payload['message'] = $m->payload;

                /** @var \DreamFactory\Core\Utility\ServiceResponse $rs */
                $rs =
                    ServiceManager::handleRequest($serviceName, $verb, $resource, $params, $header, $payload, null,
                        false);
                $content = $rs->getContent();
                $content = (is_array($content)) ? json_encode($content) : $content;
                Log::debug('[MQTT] Trigger response: ' . $content);
            });
            $client->connect($this->host, $this->port);

            foreach ($topics as $t) {
                $client->subscribe($t['topic'], 0);
            }

            $this->execute($client);
        } catch (\Exception $e) {
            Log::error('[MQTT] Exception occurred. Terminating subscription. ' . $e->getMessage());
            Cache::forever(Subscribe::TERMINATOR, false);
        }
    }

    /**
     * Loops client and listens for message on subscribed topics.
     *
     * @param \Mosquitto\Client $client
     */
    protected function execute($client)
    {
        while (1) {
            try {
                $client->loop();
                if (Cache::get(Subscribe::TERMINATOR, false) === true) {
                    Log::info('[MQTT] Terminate subscription signal received. Ending subscription job.');
                    Cache::forever(Subscribe::TERMINATOR, false);

                    throw new LoopException('Terminated on demand.');
                }
            } catch (LoopException $e) {
                $client->disconnect();
                unset($client);

                return;
            }
        }
    }
}