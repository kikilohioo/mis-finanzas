<?php

namespace App\Customs;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Laravel\Lumen\Bootstrap\LoadEnvironmentVariables;

class CustomLoadEnvironmentVariables extends LoadEnvironmentVariables
{

    /**
     * Setuup the environment variables.
     *
     * If no environment file exists, we continue silently.
     *
     * @return void
     */
    public function bootstrap()
    {
        try {
            $this->createDotenv()->load();
        } catch (InvalidFileException $e) {
            $this->writeErrorAndDie([
                'The environment file is invalid!',
                $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a Dotenv instance.
     *
     * @return \Dotenv\Dotenv
     */
    protected function createDotenv()
    {
        return Dotenv::createImmutable(
            $this->filePath,
            $this->fileName
        );
    }

}