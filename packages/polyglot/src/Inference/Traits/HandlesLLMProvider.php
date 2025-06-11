<?php

namespace Cognesy\Polyglot\Inference\Traits;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\LLMProvider;

trait HandlesLLMProvider
{
    protected ?LLMProvider $llmProvider;

    public function withLLMProvider(LLMProvider $llm) : static {
        $this->llmProvider = $llm;
        return $this;
    }

    public function withConfig(LLMConfig $config) : static {
        $this->llmProvider->withConfig($config);
        return $this;
    }

    public function withConfigProvider(CanProvideConfig $configProvider) : static {
        $this->llmProvider = $this->llmProvider->withConfigProvider($configProvider);
        return $this;
    }

    public function fromDSN(string $dsn) : static {
        $this->llmProvider->withDSN($dsn);
        return $this;
    }

    public function using(string $preset) : static {
        $this->llmProvider->withLLMPreset($preset);
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $this->llmProvider->withHttpClient($httpClient);
        return $this;
    }

    public function withDriver(CanHandleInference $driver) : static {
        $this->llmProvider->withDriver($driver);
        return $this;
    }

    public function withDebugPreset(?string $preset) : static {
        $this->llmProvider->withDebugPreset($preset);
        return $this;
    }
}