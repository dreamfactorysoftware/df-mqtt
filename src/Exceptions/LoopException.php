<?php

namespace a15lam\MQTT\Exceptions;

use Throwable;
use Exception;

class LoopException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}