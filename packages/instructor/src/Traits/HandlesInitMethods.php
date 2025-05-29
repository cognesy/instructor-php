<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Polyglot\LLM\Contracts\CanHandleInference;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\LLM;
use Cognesy\Polyglot\LLM\LLMFactory;
use Cognesy\Utils\Deferred;

trait HandlesInitMethods
{
    private LLMFactory $llmFactory;
    private ?Deferred $llm = null;
    private ?HttpClient $httpClient = null;

    public function withConfig(StructuredOutputConfig $config) : static {
        $this->config = $config;
        return $this;
    }

    /**
     * Returns the config object for the current instance.
     *
     * @return StructuredOutputConfig The config object for the current instance.
     */
    public function config() : StructuredOutputConfig {
        return $this->config;
    }

    public function using(string $preset) : static {
        if (empty($preset)) {
            return $this;
        }
        $this->llm = new Deferred(
            function () use ($preset) {
                $config = LLMConfig::load($preset);
                return $this->llmFactory->fromConfig($config);
            }
        );
        return $this;
    }

    public function withDSN(string $dsn) : static {
        $this->llm = new Deferred(
            function () use ($dsn) {
                $config = LLMConfig::fromDSN($dsn);
                return $this->llmFactory->fromConfig($config);
            }
        );
        return $this;
    }

    public function withLLM(LLM $llm) : static {
        $this->llm = new Deferred(
            function () use ($llm) {
                return $llm;
            }
        );
        return $this;
    }

    public function withLLMConfig(LLMConfig $config) : static {
        $this->llm = new Deferred(
            function () use ($config) {
                return $this->llmFactory->fromConfig($config);
            }
        );
        return $this;
    }

    public function withDriver(CanHandleInference $driver) : static {
        $this->llm->defer(
            function () use ($driver) {
                return $this->llmFactory->forDriver($driver);
            }
        );
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Enables or disables debug mode for the current instance.
     *
     * @param bool $debug Optional. If true, enables debug mode; otherwise, disables it. Defaults to true.
     * @return static The current instance with the updated debug state.
     */
    public function withDebug(bool $debug = true) : static {
        $this->llm()->withDebug($debug);
        return $this;
    }

    /**
     * Returns LLM configuration object for the current instance.
     *
     * @return LLM The LLM object for the current instance.
     */
    public function llm() : LLM {
        if (is_null($this->llm)) {
            $this->llm = new Deferred(
                function () {
                    $config = LLMConfig::default();
                    return $this->llmFactory->fromConfig($config);
                }
            );
        }
        return $this->llm->resolve();
    }
}