<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Config\LLMConfig;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\LLMProvider;

trait HandlesLLMProvider
{
    private ?LLMProvider $llmProvider = null;

    public function withDSN(string $dsn) : static {
        $this->llmProvider->withDSN($dsn);
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

    public function withDriver(CanHandleInference $driver) : static {
        $this->llmProvider->withDriver($driver);
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $this->llmProvider->withHttpClient($httpClient);
        return $this;
    }

    /**
     * Enables or disables debug mode for the current instance.
     *
     * @param string $preset Optional. If empty, the default debug preset will be used.
     * @return static The current instance with the updated debug state.
     */
    public function withDebugPreset(?string $preset) : static {
        $this->llmProvider->withDebugPreset($preset);
        return $this;
    }
}