<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;

trait HandlesApiClient
{
    protected CanCallApi $client;

    public function client() : CanCallApi {
        return $this->client;
    }

    public function withClient(CanCallApi $client) : self {
        $this->client = $client->withEventDispatcher($this->events);
        return $this;
    }
}
