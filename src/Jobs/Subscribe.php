<?php

namespace DreamFactory\Core\MQTT\Jobs;

use DreamFactory\Core\PubSub\Jobs\BaseSubscriber;

class Subscribe extends BaseSubscriber
{
    /** {@inheritdoc} */
    public static function validatePayload(array $payload)
    {
        foreach ($payload as $i => $pd) {
            if (!isset($pd['topic']) || !isset($pd['service'])) {
                return false;
            }
            if (is_array($pd['service'])) {
                if (!isset($pd['service']['endpoint'])) {
                    return false;
                }
            } else {
                $payload[$i]['service'] = ['endpoint' => $pd['service']];
            }
        }

        return true;
    }

    /** {@inheritdoc} */
    public function handle()
    {
        $this->client->subscribe($this->payload);
    }
}