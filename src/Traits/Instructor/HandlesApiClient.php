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

    public function withClient(CanCallApi $client) : self {
        $this->clientFactory->setDefault($client);
        return $this;
    }
}
