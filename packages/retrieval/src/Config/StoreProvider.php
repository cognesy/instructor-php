<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Config;

use Cognesy\Retrieval\Contracts\CanStoreDocuments;

final readonly class StoreProvider
{
    public function __construct(
        private StoreConfig $config = new StoreConfig,
        private ?CanStoreDocuments $driver = null,
    ) {}

    public function config(): StoreConfig
    {
        return $this->config;
    }

    public function explicitDriver(): ?CanStoreDocuments
    {
        return $this->driver;
    }

    public function withConfig(StoreConfig $config): self
    {
        return new self($config, $this->driver);
    }

    public function withDriver(CanStoreDocuments $driver): self
    {
        return new self($this->config, $driver);
    }
}
