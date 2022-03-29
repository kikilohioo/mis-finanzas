<?php

namespace App\Exceptions;

class SigeException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null)
    {
        $message = '[Sige] ' . $message;
        parent::__construct($message, $code, $previous);
    }
}