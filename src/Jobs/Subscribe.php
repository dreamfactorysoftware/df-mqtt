<?php

namespace a15lam\MQTT\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use DreamFactory\Core\Enums\Verbs;
use a15lam\MQTT\Exceptions\LoopException;
use a15lam\MQTT\Components\MosquittoClient;
use ServiceManager;
use Log;

class Subscribe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Topic for subscription terminator */
    const TERMINATOR = 'DF/TERMINATE';

    /** @var \a15lam\MQTT\Components\MosquittoClient */
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
     * Job handler
     */
    public function handle()
    {
        $client = MosquittoClient::client($this->client->getConfig(time()));
        $topics = $this->topics;

        $client->onConnect(function (){
            Log::info('[MQTT] Connected to MQTT broker for subscription.');
        });
        $client->onMessage(function ($m) use ($topics){
            Log::debug('[MQTT] Received message on topic: ' . $m->topic . ' with payload ' . $m->payload);

            if (static::TERMINATOR === $m->topic && is_numeric($m->payload)) {
                $jobId = (int)$m->payload;
                if ($jobId > 0) {
                    Log::debug('[MQTT] Terminating subscription jobs');

                    throw new LoopException('Subscription terminated on-demand.');
                }
            } else {
                $service = array_by_key_value($topics, 'topic', $m->topic, 'service');
                Log::debug('[MQTT] Triggering service: ' . json_encode($service));

                // Retrieve service information
                $name = array_get($service, 'name');
                $resource = array_get($service, 'resource');
                $verb = strtoupper(array_get($service, 'verb', array_get($service, 'method', Verbs::POST)));
                $params = array_get($service, 'parameter', array_get($service, 'parameters', []));
                $payload = array_get($service, 'payload', []);
                $payload['message'] = $m->payload;

                /** @var \DreamFactory\Core\Utility\ServiceResponse $rs */
                $rs = ServiceManager::handleRequest($name, $verb, $resource, $params, [], $payload);
                $content = $rs->getContent();
                $content = (is_array($content)) ? json_encode($content) : $content;
                Log::debug('[MQTT] Trigger response: ' . $content);
            }
        });
        $client->connect($this->client->getHost(), $this->client->getPort());

        foreach ($topics as $t) {
            $client->subscribe($t['topic'], 0);
        }
        $client->subscribe(static::TERMINATOR, 0);
        $this->execute($client);
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
            } catch (LoopException $e) {
                $client->disconnect();
                unset($client);

                return;
            }
        }
    }
}