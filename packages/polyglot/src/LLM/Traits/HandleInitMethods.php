<?php

namespace Cognesy\Polyglot\LLM\Traits;

use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\LLMProvider;

trait HandleInitMethods
{
    /**
     * Sets the LLM instance to be used.
     *
     * @param LLMProvider $llm The LLM instance to set.
     * @return self Returns the current instance.
     */
    public function withLLMProvider(LLMProvider $llm): self {
        $this->llm = $llm;
        return $this;
    }

    /**
     * Updates the configuration and re-initializes the driver.
     *
     * @param LLMConfig $config The configuration object to set.
     *
     * @return self
     */
    public function withConfig(LLMConfig $config): self {
        $this->llm->withConfig($config);
        return $this;
    }

    public function withDsn(string $dsn): self {
        return $this->withConfig(LLMConfig::fromDSN($dsn));
    }

    /**
     * Sets the connection and updates the configuration and driver.
     *
     * @param string $preset The connection string to be used.
     *
     * @return self Returns the current instance with the updated connection.
     */
    public function using(string $preset): self {
        if (empty($preset)) {
            return $this;
        }
        $this->llm->using($preset);
        return $this;
    }

    /**
     * Sets a custom HTTP client and updates the inference driver accordingly.
     *
     * @param HttpClient $httpClient The custom HTTP client handler.
     *
     * @return self Returns the current instance for method chaining.
     */
    public function withHttpClient(HttpClient $httpClient): self {
        $this->llm->withHttpClient($httpClient);
        return $this;
    }

    /**
     * Sets the driver for inference handling and returns the current instance.
     *
     * @param CanHandleInference $driver The inference handler to be set.
     *
     * @return self
     */
    public function withDriver(CanHandleInference $driver): self {
        $this->llm->withDriver($driver);
        return $this;
    }

    /**
     * Enable or disable debugging for the current instance.
     *
     * @param bool $debug Whether to enable debug mode. Default is true.
     *
     * @return self
     */
    public function withDebug(bool $debug = true): self {
        $this->llm->withDebug($debug);
        return $this;
    }

    /**
     * Retrieves the LLM instance.
     *
     * @return LLMProvider The LLM instance.
     */
    public function llm() : LLMProvider {
        return $this->llm;
    }

    /**
     * Retrieves the LLM configuration instance.
     *
     * @return LLMConfig The LLM configuration instance.
     */
    public function config() : LLMConfig {
        return $this->llm->config();
    }
}