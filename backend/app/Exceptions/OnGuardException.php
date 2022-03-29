<?php

namespace App\Exceptions;

class OnGuardException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null)
    {
        $message = '[OnGuard] ' . $message;
        parent::__construct($message, $code, $previous);
    }
}