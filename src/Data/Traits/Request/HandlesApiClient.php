<?php

namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;

trait HandlesApiClient
{
    private ?CanCallApi $client;

    public function client() : ?CanCallApi {
        return $this->client;
    }

    public function withClient(CanCallApi $client) : self {
        $this->client = $client;
        return $this;
    }
}