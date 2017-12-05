<?php

namespace DreamFactory\Core\MQTT\Resources;

use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\MQTT\Jobs\Subscribe;
use DreamFactory\Core\Exceptions\BadRequestException;
use Illuminate\Support\Arr;
use Cache;

class Sub extends \DreamFactory\Core\PubSub\Resources\Sub
{
    /**
     * {@inheritdoc}
     */
    protected function handlePOST()
    {
        $payload = $this->request->getPayloadData();

        if (static::validatePayload($payload) === false) {
            throw new BadRequestException('Bad payload supplied. Could not find proper topic and/or service information in the payload.');
        }

        if (!$this->isJobRunning('MQTT')) {
            $job = new Subscribe($this->parent->getClient(), $payload);
            dispatch($job);

            return ['success' => true, 'job_count' => 1];
        } else {
            throw new ForbiddenException(
                'System is currently running a subscription job. ' .
                'Please terminate the current process before subscribing to new topic(s)'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function handleDELETE()
    {
        // Put a terminate flag in the cache to terminate the subscription job.
        Cache::put(Subscribe::TERMINATOR, true, config('df.default_cache_ttl', 300));

        return ["success" => true];
    }

    /**
     * Validates topic payload.
     *
     * @param array $payload
     *
     * @return bool
     */
    protected static function validatePayload(& $payload)
    {
        if (Arr::isAssoc($payload)) {
            $payload = [$payload];
        }

        return Subscribe::validatePayload($payload);
    }
}