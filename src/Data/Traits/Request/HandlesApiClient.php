<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Contracts\CanCallLLM;
use Cognesy\Instructor\ApiClient\Enums\ClientType;

trait HandlesApiClient
{
    private ?CanCallLLM $client;
    private ClientType $clientType;

    public function client() : ?CanCallLLM {
        return $this->client;
    }

    public function clientType() : ClientType {
        return $this->clientType;
    }

    // INTERNAL //////////////////////////////////////////////////////////////////

    protected function withClient(CanCallLLM $client) : self {
        $this->client = $client;
        return $this;
    }

    protected function withClientType(ClientType $clientType) : self {
        $this->clientType = $clientType;
        return $this;
    }
}