<?php

namespace DreamFactory\Core\MQTT\Resources;

use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\MQTT\Jobs\Subscribe;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Resources\BaseRestResource;
use Illuminate\Support\Arr;
use DB;
use Cache;

class Sub extends BaseRestResource
{
    const RESOURCE_NAME = 'sub';

    /** A resource identifier used in swagger doc. */
    const RESOURCE_IDENTIFIER = 'name';

    /** @var \DreamFactory\Core\MQTT\Services\MQTT */
    protected $parent;

    /**
     * {@inheritdoc}
     */
    protected static function getResourceIdentifier()
    {
        return static::RESOURCE_IDENTIFIER;
    }

    /**
     * {@inheritdoc}
     */
    protected function handlePOST()
    {
        $payload = $this->request->getPayloadData();

        if (static::validatePayload($payload) === false) {
            throw new BadRequestException('Bad payload supplied. Could not find proper topic and service information in the payload.');
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

    /** {@inheritdoc} */
    protected function getApiDocPaths()
    {
        $service = $this->getServiceName();
        $capitalized = camelize($service);
        $resourceName = strtolower($this->name);
        $path = '/' . $resourceName;

        $base = [
            $path => [
                'get'    => [
                    'summary'     => 'Retrieves subscribed topic(s)',
                    'description' => 'Retrieves subscribed topic(s)',
                    'operationId' => 'get' . $capitalized . 'SubscriptionTopics',
                    'responses'   => [
                        '200' => [
                            'description' => 'Success',
                            'content'     => [
                                'application/json' => [
                                    'schema' => [
                                        'type'  => 'array',
                                        'items' => [
                                            'type'       => 'object',
                                            'required'   => ['topic', 'service'],
                                            'properties' => [
                                                'topic'   => ['type' => 'string'],
                                                'service' => [
                                                    'type'       => 'object',
                                                    'required'   => ['endpoint'],
                                                    'properties' => [
                                                        'endpoint'  => [
                                                            'type'        => 'string',
                                                            'description' => 'Internal DreamFactory Endpoint. Ex: system/role'
                                                        ],
                                                        'verb'      => [
                                                            'type'        => 'string',
                                                            'description' => 'GET, POST, PATCH, PUT, DELETE'
                                                        ],
                                                        'parameter' => [
                                                            'type'  => 'array',
                                                            'items' => [
                                                                "{name}" => "{value}"
                                                            ]
                                                        ],
                                                        'header'    => [
                                                            'type'  => 'array',
                                                            'items' => [
                                                                "{name}" => "{value}"
                                                            ]
                                                        ],
                                                        'payload'   => [
                                                            'type'  => 'array',
                                                            'items' => [
                                                                "{name}" => "{value}"
                                                            ]
                                                        ]
                                                    ]
                                                ],
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                    ],
                ],
                'post'   => [
                    'summary'     => 'Subscribes to topic(s)',
                    'description' => 'Subscribes to topic(s)',
                    'operationId' => 'subscribeTo' . $capitalized . 'Topics',
                    'requestBody' => [
                        'description' => 'Device token to register',
                        'content'     => [
                            'application/json' => [
                                'schema' => [
                                    'type'  => 'array',
                                    'items' => [
                                        'type'       => 'object',
                                        'required'   => ['topic', 'service'],
                                        'properties' => [
                                            'topic'   => ['type' => 'string'],
                                            'service' => [
                                                'type'       => 'object',
                                                'required'   => ['endpoint'],
                                                'properties' => [
                                                    'endpoint'  => [
                                                        'type'        => 'string',
                                                        'description' => 'Internal DreamFactory Endpoint. Ex: system/role'
                                                    ],
                                                    'header'    => [
                                                        'type'  => 'array',
                                                        'items' => [
                                                            "{name}" => "{value}"
                                                        ]
                                                    ],
                                                    'verb'      => [
                                                        'type'        => 'string',
                                                        'description' => 'GET, POST, PATCH, PUT, DELETE'
                                                    ],
                                                    'parameter' => [
                                                        'type'  => 'array',
                                                        'items' => [
                                                            "{name}" => "{value}"
                                                        ]
                                                    ],
                                                    'payload'   => [
                                                        'type'  => 'array',
                                                        'items' => [
                                                            "{name}" => "{value}"
                                                        ]
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'required'    => true
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/Success']
                    ],
                ],
                'delete' => [
                    'summary'     => 'Terminate subscriptions',
                    'description' => 'Terminates subscriptions to all topic(s)',
                    'operationId' => 'terminatesSubscriptionsTo' . $capitalized,
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/Success']
                    ],
                ]
            ]
        ];

        return $base;
    }
}