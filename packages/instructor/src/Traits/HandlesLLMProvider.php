<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\LLMProvider;

trait HandlesLLMProvider
{
    private ?LLMProvider $llm = null;

    public function withDSN(string $dsn) : static {
        $this->llm->withDSN($dsn);
        return $this;
    }

    public function using(string $preset) : static {
        $this->llm->withPreset($preset);
        return $this;
    }

    public function withLLMProvider(LLMProvider $llm) : static {
        $this->llm = $llm;
        return $this;
    }

    public function withLLMConfig(LLMConfig $config) : static {
        $this->llm->withConfig($config);
        return $this;
    }

    public function withDriver(CanHandleInference $driver) : static {
        $this->llm->withDriver($driver);
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $this->llm->withHttpClient($httpClient);
        return $this;
    }

    /**
     * Enables or disables debug mode for the current instance.
     *
     * @param bool $debug Optional. If true, enables debug mode; otherwise, disables it. Defaults to true.
     * @return static The current instance with the updated debug state.
     */
    public function withDebug(bool $debug = true) : static {
        $this->llm->withDebug($debug);
        return $this;
    }

    /**
     * Returns LLM configuration object for the current instance.
     *
     * @return LLMProvider The LLM object for the current instance.
     */
    public function llm() : LLMProvider {
        return $this->llm;
    }

    /**
     * Returns the config object for the current instance.
     *
     * @return StructuredOutputConfig The config object for the current instance.
     */
    public function config() : StructuredOutputConfig {
        return $this->config;
    }
}