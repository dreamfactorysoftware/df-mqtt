<?php

namespace DreamFactory\Core\MQTT\Resources;

use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\MQTT\Jobs\Subscribe;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\Session;
use Illuminate\Support\Arr;

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
            // Bind the caller's identity to each topic so the deferred consumer
            // runs the triggered service request under the creator's role
            // (permission-checked), not with permissions disabled.
            $runAs = [
                'app_id'  => Session::get('app.id'),
                'user_id' => Session::getCurrentUserId(),
            ];
            if (empty($runAs['app_id']) && empty($runAs['user_id'])) {
                // Without an identity the consumer would run every callback
                // unauthenticated and every one of them would be denied. Say so
                // now, rather than accepting a subscription that can never work.
                throw new BadRequestException(
                    'Cannot determine the identity to run this subscription as. ' .
                    'Subscribe with an API key, a session token, or both.'
                );
            }
            foreach ($payload as &$entry) {
                if (is_array($entry)) {
                    $entry['run_as'] = $runAs;
                }
            }
            unset($entry);
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