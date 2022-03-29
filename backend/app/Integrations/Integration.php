<?php

namespace App\Integrations;

class Integration
{
    private $integrations = [];
    private $integrationExceptions = [];
    private $transaction = null;
    private $transactionException = null;
    private $throwIfNotIntegrated = false;

    public function __construct()
    {

    }

    public function add(string $name, \Closure $integration, ?\Closure $exception = null): self
    {
        $this->integrations[] = [
            $name = $integration,
        ];
        
        if (isset($exception)) {
            $this->integrationExceptions[] = [
                $name => $exception,
            ];
        }

        return $this;
    }

    public function setTransaction(\Closure $transaction, ?\Closure $exception = null): self
    {
        $this->transaction = $transaction;

        if (isset($exception)) {
            $this->transactionException = $exception;
        }

        return $this;
    }

    public function throwIfNotIntegrated(bool $flag): self
    {
        $this->throwIfNotIntegrated = $flag;
        
        return $this;
    }

    public static function newInstance(): self
    {
        return new self();
    }

    private function hasIntegration(): bool
    {
        return env('INTEGRADO', 'false') === true && count($this->integrations);
    }
}