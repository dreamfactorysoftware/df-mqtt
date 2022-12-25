<?php

namespace DreamFactory\Core\MQTT\Components;

use \PhpMqtt\Client\Exceptions\MqttClientException;
use \PhpMqtt\Client\ConnectionSettings;
use \PhpMqtt\Client\MqttClient;
use DreamFactory\Core\PubSub\Contracts\MessageQueueInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\DfServiceException;
use DreamFactory\Core\MQTT\Jobs\Subscribe;
use DreamFactory\Core\Enums\Verbs;
use ServiceManager;
use Cache;
use Log;
use Arr;

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

    protected \PhpMqtt\Client\ConnectionSettings $connectionSettings;

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
     * @return \PhpMqtt\Client\MqttClient
     */
    public function client($config)
    {
        $clientId = Arr::get($config, 'client_id');
        $username = Arr::get($config, 'username');
        $password = Arr::get($config, 'password');
        $caPath = Arr::get($config, 'ca_path');
        $host = Arr::get($config, 'host');
        $port = Arr::get($config, 'port');
        try {
            $client = new MqttClient($host, $port, $clientId, MqttClient::MQTT_3_1_1);

            if (!empty($username) && !empty($password)) {
                $this->connectionSettings = (new ConnectionSettings)
                                                ->setUsername($username)
                                                ->setPassword($password);
            }
            if (!empty($caPath)) {
                $this->connectionSettings = (new ConnectionSettings)
                                                ->setUseTls(true)
                                                ->setTlsCertificateAuthorityPath($caPath);
            }
            return $client;
        } catch (MqttClientException $e) {
            Log::error('Error occurred while creating MQTT client. ' . $e->getMessage());
            throw new DfServiceException('Couldn\'t create MQTT client. ' . $e->getMessage());
        }
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
        try {
            $topic = Arr::get($data, 'topic');
            $msg = Arr::get($data, 'message', Arr::get($data, 'msg'));

            if (empty($topic) || empty($msg)) {
                throw new InternalServerErrorException('No topic and/or message supplied for publishing.');
            }

            $client = $this->client($this->getConfig());

            $client->connect($this->connectionSettings, true);
            Log::info('[MQTT] Connected to MQTT broker.');

            $handler = function (MqttClient $client, string $topic, string $message) {
                Log::debug('[MQTT] Now publishing message: ' . $message . ' using topic: ' . $topic);
            };

            $client->registerPublishEventHandler($handler);

            $client->publish($topic, $msg, MqttClient::QOS_AT_LEAST_ONCE, true);

            $client->loop(true, true);

            $client->disconnect();
            unset($client);

            Log::info('[MQTT] Message received.');
            Log::debug('[MQTT] Received message on topic: "' . $topic . '" with payload "' . $msg . '"');

        } catch (MqttClientException $e) {
            Log::error('[MQTT] Exception occurred. Terminating publish. ' . $e->getMessage());
            throw new DfServiceException('Couldn\'t publish. ' . $e->getMessage());
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
            $client = $this->client($this->getConfig());

            $client->connect($this->connectionSettings, true);

            Log::info('[MQTT] Connected to MQTT broker for subscription.');

            $handler = function (MqttClient $client, string $topic, string $message) use ($topics) {
                Log::info('[MQTT] Received message on topic: ' . $topic . ' with payload ' . $message);

                /** @var \DreamFactory\Core\Utility\ServiceResponse $response */
                $response = $this->sendServiceRequest($topics, $topic, $message);
                $content = $response->getContent();
                $content = (is_array($content)) ? json_encode($content) : $content;

                Log::debug('[MQTT] Trigger response: ' . $content);
            };
            
            // Register an event handler which is called whenever a message is received
            $client->registerMessageReceivedEventHandler($handler);

            foreach ($topics as $t) {
                $client->subscribe($t['topic'], null,MqttClient::QOS_AT_MOST_ONCE);
            }

            $this->execute($client);
            
        } catch (MqttClientException $e) {
            Log::error('[MQTT] Exception occurred. Terminating subscription. ' . $e->getMessage());
            Cache::forever(Subscribe::TERMINATOR, false);
        }
    }

    /**
     * Loops client and listens for message on subscribed topics.
     *
     * @param \PhpMqtt\Client\MqttClient $client
     */
    protected function execute($client)
    {
        while (true) {
            $client->loopOnce(microtime(true), true);
            if (Cache::get(Subscribe::TERMINATOR, false) === true) {

                Log::info('[MQTT] Terminate subscription signal received. Ending subscription job.');
                Cache::forever(Subscribe::TERMINATOR, false);

                $client->disconnect();
                unset($client);

                return;
            }
        }
    }

    /**
     * Send request to appointed endpoint
     *
     * @param array $topics
     * @param string $currentTopic
     * @param string $message
     *  
     * @return \DreamFactory\Core\Utility\ServiceResponse
     */
    protected function sendServiceRequest($topics, $currentTopic, $message) {
        // Retrieve service information
        $service = array_by_key_value($topics, 'topic', $currentTopic, 'service');
        Log::info('[MQTT] Triggering service: ' . json_encode($service));

        $endpoint = trim(Arr::get($service, 'endpoint'), '/');
        $endpoint = str_replace('api/v2/', '', $endpoint);
        $endpointArray = explode('/', $endpoint);

        $serviceName = Arr::get($endpointArray, 0);
        array_shift($endpointArray);
        $verb = strtoupper(Arr::get($service, 'verb', Arr::get($service, 'method', Verbs::POST)));
        $resource = implode('/', $endpointArray);
        $params = Arr::get($service, 'parameter', Arr::get($service, 'parameters', []));
        $header = Arr::get($service, 'header', Arr::get($service, 'headers', []));
        $payload = Arr::get($service, 'payload', []);
        $payload['message'] = $message;

        return ServiceManager::handleRequest($serviceName, $verb, $resource, $params, $header, $payload, null, false);
    }
}