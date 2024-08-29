<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Factories\ApiClientFactory;

trait HandlesApiClient
{
    protected ApiClientFactory $clientFactory;

    public function client() : CanCallApi {
        return $this->clientFactory->getDefault();
    }

    public function withClient(string|CanCallApi $client) : self {
        if (is_string($client)) {
            $client = $this->clientFactory->client($client);
        }
        $this->clientFactory->setDefault($client);
        return $this;
    }
}
