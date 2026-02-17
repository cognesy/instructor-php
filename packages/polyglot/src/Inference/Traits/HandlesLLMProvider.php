<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Traits;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanResolveLLMConfig;
use Cognesy\Polyglot\Inference\LLMProvider;

trait HandlesLLMProvider
{
    protected LLMProvider $llmProvider;
    protected ?CanResolveLLMConfig $llmResolver = null;
    abstract protected function invalidateRuntimeCache(): void;

    public function withLLMProvider(LLMProvider $llm) : static {
        $copy = clone $this;
        $copy->llmProvider = $llm;
        $copy->llmResolver = null;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withLLMResolver(CanResolveLLMConfig $resolver) : static {
        // Allow custom config resolver injection alongside provider
        $copy = clone $this;
        $copy->llmResolver = $resolver;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withLLMConfig(LLMConfig $config) : static {
        $copy = clone $this;
        $copy->llmProvider = $copy->llmProvider->withLLMConfig($config);
        $copy->llmResolver = null;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withConfigProvider(CanProvideConfig $configProvider) : static {
        $copy = clone $this;
        $copy->llmProvider = $copy->llmProvider->withConfigProvider($configProvider);
        $copy->llmResolver = null;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withDsn(string $dsn) : static {
        $copy = clone $this;
        $copy->llmProvider = $copy->llmProvider->withDsn($dsn);
        $copy->llmResolver = null;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function using(string $preset) : static {
        $copy = clone $this;
        $copy->llmProvider = $copy->llmProvider->withLLMPreset($preset);
        $copy->llmResolver = null;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $copy = clone $this;
        $copy->httpClient = $httpClient;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withHttpClientPreset(string $string) : static {
        $copy = clone $this;
        // Build a client using the HTTP config preset at the facade level
        $builder = new HttpClientBuilder(events: $copy->events);
        $copy->httpClient = $builder->withPreset($string)->create();
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withLLMConfigOverrides(array $overrides) : static {
        $copy = clone $this;
        $copy->llmProvider = $copy->llmProvider->withConfigOverrides($overrides);
        $copy->llmResolver = null;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    public function withDriver(CanProcessInferenceRequest $driver) : static {
        $copy = clone $this;
        $copy->llmProvider = $copy->llmProvider->withDriver($driver);
        $copy->llmResolver = null;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    /**
     * Set HTTP debug preset explicitly.
     */
    public function withHttpDebugPreset(?string $preset) : static {
        $copy = clone $this;
        $copy->httpDebugPreset = $preset;
        $copy->invalidateRuntimeCache();
        return $copy;
    }

    /**
     * Convenience toggle for HTTP debugging.
     */
    public function withHttpDebug(bool $enabled = true) : static {
        $preset = match ($enabled) {
            true => 'on',
            false => 'off',
        };
        return $this->withHttpDebugPreset($preset);
    }
}
