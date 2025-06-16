<?php

namespace Cognesy\Polyglot\Inference;

use Cognesy\Config\ConfigPresets;
use Cognesy\Config\ConfigResolver;
use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Config\Dsn;
use Cognesy\Config\Events\ConfigResolutionFailed;
use Cognesy\Config\Events\ConfigResolved;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Http\HttpClient;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Drivers\InferenceDriverFactory;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;

final class LLMProvider
{
    private readonly CanHandleEvents $events;
    private readonly CanProvideConfig $configProvider;

    private ConfigPresets $presets;

    // Configuration - all immutable after construction
    private ?string $dsn;
    private ?string $llmPreset;
    private ?string $debugPreset;
    private ?string $httpClientPreset;

    private ?LLMConfig $explicitConfig;
    private ?HttpClient $explicitHttpClient;
    private ?CanHandleInference $explicitDriver;

    private function __construct(
        ?CanHandleEvents          $events = null,
        ?CanProvideConfig         $configProvider = null,
        ?string                   $debugPreset = null,
        ?string                   $dsn = null,
        ?string                   $preset = null,
        ?LLMConfig                $explicitConfig = null,
        ?HttpClient               $explicitHttpClient = null,
        ?CanHandleInference       $explicitDriver = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->configProvider = $configProvider ?? ConfigResolver::using($configProvider);
        $this->presets = ConfigPresets::using($configProvider)->for(LLMConfig::group());

        $this->debugPreset = $debugPreset;
        $this->dsn = $dsn;
        $this->llmPreset = $preset;
        $this->explicitConfig = $explicitConfig;
        $this->explicitHttpClient = $explicitHttpClient;
        $this->explicitDriver = $explicitDriver;
    }

    /**
     * Quick creation with preset
     */
    public static function using(string $preset): LLMProvider {
        return self::new()->using($preset);
    }

    /**
     * Quick creation with DSN
     */
    public static function dsn(string $dsn): LLMProvider {
        return self::new()->withDsn($dsn);
    }

    /**
     * Create a new builder instance
     */
    public static function new(
        ?EventDispatcherInterface $events = null,
        ?CanProvideConfig         $configProvider = null,
    ): self {
        return new self($events, $configProvider);
    }

    /**
     * Configure with a preset name
     */
    public function withLLMPreset(string $preset): self {
        $this->llmPreset = $preset;
        return $this;
    }

    /**
     * Configure with explicit LLM configuration
     */
    public function withConfig(LLMConfig $config): self {
        $this->explicitConfig = $config;
        return $this;
    }

    /**
     * Configure with a custom config provider
     */
    public function withConfigProvider(CanProvideConfig $configProvider): self {
        $this->presets = $this->presets->withConfigProvider($configProvider);
        return $this;
    }

    /**
     * Configure with DSN string
     */
    public function withDsn(string $dsn): self {
        $this->dsn = $dsn;
        return $this;
    }

    /**
     * Configure with explicit HTTP client
     */
    public function withHttpClient(HttpClient $httpClient): self {
        $this->explicitHttpClient = $httpClient;
        return $this;
    }

    public function withHttpPreset(string $preset): self {
        // Create a new HTTP client builder with the specified preset
        $this->httpClientPreset = $preset;
        return $this;
    }

    /**
     * Configure with explicit inference driver
     */
    public function withDriver(CanHandleInference $driver): self {
        $this->explicitDriver = $driver;
        return $this;
    }

    /**
     * Configure debug mode
     */
    public function withDebugPreset(?string $preset): self {
        $this->debugPreset = $preset;
        return $this;
    }

    /**
     * Create the fully configured inference driver
     * This is the terminal operation that builds and returns the final instance
     */
    public function createDriver(): CanHandleInference {
        // If explicit driver provided, return it directly
        if ($this->explicitDriver !== null) {
            return $this->explicitDriver;
        }

        // Build all required components
        $config = $this->buildConfig();
        $httpClient = $this->buildHttpClient($config);

        // Create and return the inference driver
        return (new InferenceDriverFactory(events: $this->events))
            ->makeDriver($config, $httpClient);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    /**
     * Build the LLM configuration
     */
    private function buildConfig(): LLMConfig {
        // If explicit config provided, use it
        if ($this->explicitConfig !== null) {
            $this->events->dispatch(new ConfigResolved([
                'group' => 'llm',
                'config' => $this->explicitConfig->toArray()
            ]));
            return $this->explicitConfig;
        }

        // Determine effective preset
        $effectivePreset = $this->determinePreset();

        // Get DSN overrides if any
        $dsnOverrides = $this->dsn !== null ? Dsn::fromString($this->dsn)->toArray() : [];

        // Build config based on preset
        $result = Result::try(fn() => $this->presets->getOrDefault($effectivePreset));

        if ($result->isFailure()) {
            $this->events->dispatch(new ConfigResolutionFailed([
                'group' => 'llm',
                'effectivePreset' => $effectivePreset,
                'preset' => $this->llmPreset,
                'dsn' => $this->dsn,
                'error' => $result->errorMessage(),
            ]));
            throw $result->exception();
        }

        $config = LLMConfig::fromArray($result->unwrap());

        // Apply DSN overrides if present
        $final = !empty($dsnOverrides)
            ? $config->withOverrides($dsnOverrides)
            : $config;

        // Dispatch event
        $this->events->dispatch(new ConfigResolved([
            'group' => 'llm',
            'effectivePreset' => $effectivePreset,
            'preset' => $this->llmPreset,
            'dsn' => $this->dsn,
            'config' => $final->toArray(),
        ]));

        return $final;
    }

    /**
     * Build the HTTP client
     */
    private function buildHttpClient(LLMConfig $config): HttpClient {
        // If explicit client provided, use it
        if ($this->explicitHttpClient !== null) {
            return $this->explicitHttpClient;
        }

        $preset = $this->httpClientPreset ?? $config->httpClientPreset;

        // Build new client
        $builder = (new HttpClientBuilder(
            $this->events,
            $this->configProvider,
        ))->withPreset($preset);

        // Apply debug setting if specified
        $builder = $builder->withDebugPreset($this->debugPreset);

        return $builder->create();
    }

    /**
     * Determine the effective preset from various sources
     */
    private function determinePreset(): ?string {
        return match (true) {
            $this->llmPreset !== null => $this->llmPreset,
            $this->dsn !== null => Dsn::fromString($this->dsn)->param('preset'),
            default => null,
        };
    }
}