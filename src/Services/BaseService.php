<?php

namespace a15lam\MQTT\Services;

use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

abstract class BaseService extends BaseRestService
{
    /** @var  \a15lam\Components\MosquittoClient */
    protected $client;

    public function __construct(array $settings)
    {
        parent::__construct($settings);

        $config = array_get($settings, 'config');
        Session::replaceLookups($config, true);

        if (empty($config)) {
            throw new InternalServerErrorException('No service configuration found for mqtt service.');
        }

        $this->setClient($config);
    }

    protected abstract function setClient($config);
}