<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\LLMProvider;

trait HandlesLLMProvider
{
    private ?LLMProvider $llmProvider = null;

    public function withDsn(string $dsn) : static {
        $this->llmProvider->withDsn($dsn);
        return $this;
    }

    public function using(string $preset) : static {
        $this->llmProvider->withLLMPreset($preset);
        return $this;
    }

    public function withLLMProvider(LLMProvider $llm) : static {
        $this->llmProvider = $llm;
        return $this;
    }

    public function withLLMConfig(LLMConfig $config) : static {
        $this->llmProvider->withConfig($config);
        return $this;
    }

    public function withLLMConfigOverrides(array $overrides) : static {
        $this->llmProvider->withConfigOverrides($overrides);
        return $this;
    }

    public function withDriver(CanHandleInference $driver) : static {
        $this->llmProvider->withDriver($driver);
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $this->llmProvider->withHttpClient($httpClient);
        return $this;
    }

    public function withHttpClientPreset(string $string) : static {
        $this->llmProvider->withHttpPreset($string);
        return $this;
    }

    public function withDebugPreset(string $preset) : static {
        $this->llmProvider->withDebugPreset($preset);
        return $this;
    }

    public function withClientInstance(
        string $driverName,
        object $clientInstance
    ) : self {
        $this->llmProvider->withClientInstance($driverName, $clientInstance);
        return $this;
    }
}