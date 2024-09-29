<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Extras\Http\Contracts\CanHandleHttp;
use Cognesy\Instructor\Extras\LLM\Contracts\CanHandleInference;

trait HandlesLLMClient
{
    private string $connection = '';
    private ?CanHandleInference $driver = null;
    private ?CanHandleHttp $httpClient = null;

    public function connection() : string {
        return $this->connection ?? '';
    }

    public function driver() : ?CanHandleInference {
        return $this->driver;
    }

    public function httpClient() : ?CanHandleHttp {
        return $this->httpClient;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////
}