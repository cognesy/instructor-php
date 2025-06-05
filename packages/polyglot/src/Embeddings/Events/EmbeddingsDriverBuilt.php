<?php

namespace Cognesy\Polyglot\Embeddings\Events;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsConfig;
use Cognesy\Utils\Events\Event;

class EmbeddingsDriverBuilt extends Event
{
    public string $driverClass;
    public EmbeddingsConfig $config;
    public array $httpClientData;

    public function __construct(
        string $driverClass,
        EmbeddingsConfig $config,
        HttpClient $httpClient
    ) {
        parent::__construct();

        $this->driverClass = $driverClass;
        $this->config = $config;
        $this->httpClientData = $httpClient->toDebugArray();
    }

    public function toArray(): array {
        return [
            'driverClass' => $this->driverClass,
            'config' => $this->config->toArray(),
            'httpClientData' => $this->httpClientData,
        ];
    }

    public function __toString(): string {
        return json_encode($this->config->toArray());
    }
}