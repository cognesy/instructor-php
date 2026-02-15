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

    public function withLLMProvider(LLMProvider $llm) : static {
        $this->llmProvider = $llm;
        $this->llmResolver = null;
        return $this;
    }

    public function withLLMResolver(CanResolveLLMConfig $resolver) : static {
        // Allow custom config resolver injection alongside provider
        $this->llmResolver = $resolver;
        return $this;
    }

    public function withLLMConfig(LLMConfig $config) : static {
        $this->llmProvider = $this->llmProvider->withLLMConfig($config);
        $this->llmResolver = null;
        return $this;
    }

    public function withConfig(LLMConfig $config) : static {
        $this->llmProvider = $this->llmProvider->withLLMConfig($config);
        $this->llmResolver = null;
        return $this;
    }

    public function withConfigProvider(CanProvideConfig $configProvider) : static {
        $this->llmProvider = $this->llmProvider->withConfigProvider($configProvider);
        $this->llmResolver = null;
        return $this;
    }

    public function withDsn(string $dsn) : static {
        $this->llmProvider = $this->llmProvider->withDsn($dsn);
        $this->llmResolver = null;
        return $this;
    }

    public function using(string $preset) : static {
        $this->llmProvider = $this->llmProvider->withLLMPreset($preset);
        $this->llmResolver = null;
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function withHttpClientPreset(string $string) : static {
        // Build a client using the HTTP config preset at the facade level
        $builder = new HttpClientBuilder(events: $this->events);
        $this->httpClient = $builder->withPreset($string)->create();
        return $this;
    }

    public function withLLMConfigOverrides(array $overrides) : static {
        $this->llmProvider = $this->llmProvider->withConfigOverrides($overrides);
        $this->llmResolver = null;
        return $this;
    }

    public function withDriver(CanProcessInferenceRequest $driver) : static {
        $this->llmProvider = $this->llmProvider->withDriver($driver);
        $this->llmResolver = null;
        return $this;
    }

    /**
     * Set HTTP debug preset explicitly (clearer than withDebugPreset()).
     */
    public function withHttpDebugPreset(?string $preset) : static {
        $this->httpDebugPreset = $preset;
        return $this;
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

    /**
     * Backward-compatible alias for HTTP debug presets.
     */
    public function withDebugPreset(?string $preset) : static {
        return $this->withHttpDebugPreset($preset);
    }
}
