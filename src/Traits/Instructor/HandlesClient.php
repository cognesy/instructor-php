<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;
use JetBrains\PhpStorm\Deprecated;

trait HandlesClient
{
    public function withDriver(CanHandleInference $driver) : self {
        $this->requestData->driver = $driver;
        return $this;
    }

    public function withHttpClient(CanHandleHttp $httpClient) : self {
        $this->requestData->httpClient = $httpClient;
        return $this;
    }

    #[Deprecated]
    public function withClient(string $client) : self {
        $this->requestData->connection = $client;
        return $this;
    }

    public function withConnection(string $connection) : self {
        $this->requestData->connection = $connection;
        return $this;
    }
}
