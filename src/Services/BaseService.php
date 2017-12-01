<?php

namespace DreamFactory\Core\MQTT\Services;

use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\Session;

abstract class BaseService extends BaseRestService
{
    /** @var \DreamFactory\Core\Contracts\MessageQueueInterface */
    protected $client;

    public function __construct(array $settings)
    {
        parent::__construct($settings);

        $config = array_get($settings, 'config');
        Session::replaceLookups($config, true);
        $this->setClient($config);
    }

    /**
     * Returns the client component
     *
     * @return \DreamFactory\Core\Contracts\MessageQueueInterface
     */
    public function getClient()
    {
        return $this->client;
    }

    protected abstract function setClient($config);
}