<?php

declare(strict_types=1);

namespace Cognesy\Retrieval;

use Cognesy\Retrieval\Config\RetrievalConfig;
use Cognesy\Retrieval\Config\StoreProvider;

final readonly class RetrievalProvider
{
    public function __construct(
        private RetrievalConfig $config = new RetrievalConfig,
        private ?StoreProvider $store = null,
    ) {}

    public function config(): RetrievalConfig
    {
        return $this->config;
    }

    public function store(): StoreProvider
    {
        return $this->store ?? new StoreProvider($this->config->store);
    }

    public function withConfig(RetrievalConfig $config): self
    {
        return new self($config, $this->store);
    }

    public function withStore(StoreProvider $store): self
    {
        return new self($this->config, $store);
    }
}
