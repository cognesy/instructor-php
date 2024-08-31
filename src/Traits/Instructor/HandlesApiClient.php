<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallLLM;
use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;

trait HandlesApiClient
{
    protected ApiClientFactory $clientFactory;

    public function client() : CanCallLLM {
        return $this->clientFactory->getDefault();
    }

    public function withClient(string|CanCallLLM $client) : self {
        if (is_string($client)) {
            $client = $this->clientFactory->client($client);
        }
        $this->clientFactory->setDefault($client);
        return $this;
    }
}
