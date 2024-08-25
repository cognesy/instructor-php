<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Enums\ClientType;

trait HandlesApiClient
{
    private ?CanCallApi $client;
    private ClientType $clientType;

    public function client() : ?CanCallApi {
        return $this->client;
    }

    public function clientType() : ClientType {
        return $this->clientType;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    protected function withClient(CanCallApi $client) : self {
        $this->client = $client;
        return $this;
    }

    protected function withClientType(ClientType $clientType) : self {
        $this->clientType = $clientType;
        return $this;
    }
}