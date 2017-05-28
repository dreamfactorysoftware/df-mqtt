<?php

namespace a15lam\MQTT\Exceptions;

use Throwable;
use Exception;

class LoopException extends Exception
{
    /**
     * LoopException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}