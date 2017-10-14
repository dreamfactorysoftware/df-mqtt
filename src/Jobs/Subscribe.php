<?php

namespace DreamFactory\Core\MQTT\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\MQTT\Exceptions\LoopException;
use DreamFactory\Core\MQTT\Components\MosquittoClient;
use ServiceManager;
use Log;
use Cache;

class Subscribe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Topic for subscription terminator */
    const TERMINATOR = 'DF:MQTT:TERMINATE';

    const SUBSCRIPTION = 'DF:MQTT:SUBSCRIPTION';

    /** @var \DreamFactory\Core\MQTT\Components\MosquittoClient */
    protected $client;

    /** @var array topic -> service mapping */
    protected $topics;

    /** @var int job retry count */
    public $tries = 1;

    /**
     * Subscribe constructor.
     *
     * @param $client
     * @param $topics
     */
    public function __construct($client, $topics)
    {
        $this->client = $client;
        $this->topics = $topics;
    }

    /**
     * @return array
     */
    public function getTopics()
    {
        return $this->topics;
    }

    /**
     * Job handler
     */
    public function handle()
    {
        $topics = $this->topics;
        $topicsJson = json_encode($topics, JSON_UNESCAPED_SLASHES);
        Cache::forever(static::SUBSCRIPTION, $topicsJson);

        try {
            $client = MosquittoClient::client($this->client->getConfig(time()));

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
                $resource = array_get($endpointArray, 1);
                $verb = strtoupper(array_get($service, 'verb', array_get($service, 'method', Verbs::POST)));
                $params = array_get($service, 'parameter', array_get($service, 'parameters', []));
                $header = array_get($service, 'header', array_get($service, 'headers', []));
                $payload = array_get($service, 'payload', []);
                $payload['message'] = $m->payload;

                /** @var \DreamFactory\Core\Utility\ServiceResponse $rs */
                $rs = ServiceManager::handleRequest($serviceName, $verb, $resource, $params, $header, $payload, null, false);
                $content = $rs->getContent();
                $content = (is_array($content)) ? json_encode($content) : $content;
                Log::debug('[MQTT] Trigger response: ' . $content);
            });
            $client->connect($this->client->getHost(), $this->client->getPort());

            foreach ($topics as $t) {
                $client->subscribe($t['topic'], 0);
            }

            $this->execute($client);
        } catch (\Exception $e) {
            Log::error('[MQTT] Exception occurred. Terminating subscription. ' . $e->getMessage());
            Cache::forget(static::SUBSCRIPTION);
            Cache::forever(static::TERMINATOR, false);
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
                if (Cache::get(static::TERMINATOR, false) === true) {
                    Log::info('[MQTT] Terminate subscription signal received. Ending subscription job.');
                    Cache::forever(static::TERMINATOR, false);
                    Cache::forget(static::SUBSCRIPTION);

                    throw new LoopException('Terminated on demand.');
                }
            } catch (LoopException $e) {
                $client->disconnect();
                unset($client);

                return;
            }
        }
    }

    /**
     * @param \Exception $exception
     */
    public function failed(\Exception $exception)
    {
        if (!$exception instanceof MaxAttemptsExceededException) {
            Log::debug("[MQTT] Job failed. " . $exception->getMessage());
            Cache::forget(static::SUBSCRIPTION);
            Cache::forever(static::TERMINATOR, false);
        }
    }
}