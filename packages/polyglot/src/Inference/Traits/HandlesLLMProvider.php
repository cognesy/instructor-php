<?php declare(strict_types=1);

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

    public function withDsn(string $dsn) : static {
        $this->llmProvider->withDsn($dsn);
        return $this;
    }

    public function using(string $preset) : static {
        $this->llmProvider->withLLMPreset($preset);
        return $this;
    }

    public function withHttpClient(HttpClient $httpClient) : static {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function withHttpClientPreset(string $string) : static {
        // Build a client using the HTTP config preset at the facade level
        $builder = new \Cognesy\Http\HttpClientBuilder(events: $this->events);
        $this->httpClient = $builder->withPreset($string)->create();
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

    public function withDebugPreset(?string $preset) : static {
        $this->httpDebugPreset = $preset;
        return $this;
    }
}
