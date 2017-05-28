<?php

namespace a15lam\MQTT\Resources;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Resources\BaseRestResource;
use DB;
use DreamFactory\Core\Utility\ResourcesWrapper;
use Illuminate\Support\Arr;

class Sub extends BaseRestResource
{
    const RESOURCE_NAME = 'sub';

    /** @var \a15lam\MQTT\Services\MQTT */
    protected $parent;

    /**
     * {@inheritdoc}
     */
    protected function handlePOST()
    {
        $payload = $this->request->getPayloadData(ResourcesWrapper::getWrapper());

        if (static::validatePayload($payload) === false) {
            throw new BadRequestException('Bad payload supplied. Could not find proper topic and service information in the payload.');
        }

        $job = $this->isJobRunning();

        if ($job === false) {
            $jobId = $this->parent->getClient()->subscribe($payload);

            return ['success' => true, 'job_id' => $jobId];
        } else {
            throw new BadRequestException(
                'System is currently running a subscription job with id ' . $job . '. ' .
                'Please terminate the current process before subscribing to new topic(s)'
            );
        }
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

        foreach ($payload as $i => $pd) {
            if (!isset($pd['topic']) || !isset($pd['service'])) {
                return false;
            }
            if (is_array($pd['service'])) {
                if (!isset($pd['service']['name'])) {
                    return false;
                }
            } else {
                $payload[$i]['service'] = ['name' => $pd['service']];
            }
        }

        return true;
    }

    /**
     * Checks to see if a subscription job is running.
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected function isJobRunning()
    {
        $jobs = DB::table('jobs')
            ->where('payload', 'like', "%Subscribe%")
            ->where('payload', 'like', "%MQTT%")
            ->where('payload', 'like', "%a15lam%")
            ->get(['id', 'attempts']);

        foreach ($jobs as $job) {
            if ($job->attempts == 1) {
                return $job->id;
            } elseif ($job->attempts == 0) {
                throw new InternalServerErrorException('Unprocessed job found in the queue. Please make sure queue worker is running');
            }
        }

        return false;
    }
}