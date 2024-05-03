<?php

namespace Cognesy\Instructor\ApiClient\Factories;

use Cognesy\Instructor\ApiClient\ApiClient;
use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Events\EventDispatcher;

class ApiClientFactory
{
    public function __construct(
        public EventDispatcher $events,
        public ApiRequestFactory $apiRequestFactory,
        protected CanCallApi $defaultClient,
        protected array $clients = [],
    ) {}

    public function client(ClientType $type) : CanCallApi {
        $clientName = $type->value;
        if (!isset($this->clients[$clientName])) {
            throw new \InvalidArgumentException("Client '$clientName' does not exist");
        }
        $client = $this->clients[$clientName];
        if (!$client instanceof ApiClient) {
            throw new \InvalidArgumentException("Client '$clientName' is not an instance of ApiClient");
        }
        return $client->withApiRequestFactory($this->apiRequestFactory);
    }

    public function getDefault() : CanCallApi {
        if (!$this->defaultClient) {
            throw new \RuntimeException("No default client has been set");
        }
        return $this->defaultClient;
    }

    public function setDefault(CanCallApi $client) : self {
        $this->defaultClient = $client
            ->withEventDispatcher($this->events)
            ->withApiRequestFactory($this->apiRequestFactory);
        return $this;
    }
}
