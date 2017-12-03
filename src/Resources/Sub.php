<?php

namespace DreamFactory\Core\MQTT\Resources;

use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\MQTT\Jobs\Subscribe;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use Illuminate\Support\Arr;
use DB;
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

        if (!$this->isJobRunning()) {
            $jobId = $this->parent->getClient()->subscribe($payload);

            return ['success' => true, 'job_id' => $jobId];
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
    protected function handleGET()
    {
        if (config('queue.default') == 'database') {
            $subscription = Cache::get(Subscribe::SUBSCRIPTION);

            if (!empty($subscription)) {
                $payload = json_decode($subscription, true);

                return $payload;
            } else {
                throw new NotFoundException('Did not find any subscribed topic(s). Subscription job may not be running.');
            }
        } else {
            throw new NotImplementedException('Viewing subscribed topics is only supported for database queue at this time.');
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

    /**
     * Checks to see if a subscription job is running.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function isJobRunning()
    {
        $subscription = Cache::get(Subscribe::SUBSCRIPTION);

        if (!empty($subscription)) {
            return true;
        }

        $jobs = DB::table('jobs')
            ->where('payload', 'like', "%Subscribe%")
            ->where('payload', 'like', "%MQTT%")
            ->where('payload', 'like', "%DreamFactory%")
            ->get(['id', 'attempts']);

        foreach ($jobs as $job) {
            if ($job->attempts == 1) {
                return true;
            } elseif ($job->attempts == 0) {
                throw new InternalServerErrorException('Unprocessed job found in the queue. Please make sure queue worker is running');
            }
        }

        return false;
    }
}