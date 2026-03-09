<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors\Data;

use Cognesy\Evals\Utils\Combination;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanProvideInferenceDrivers;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Generator;

class InferenceCases
{
    /** @var array<string, array<string, mixed>> */
    private const DEFAULT_CONNECTION_CONFIGS = [
        'a21' => ['driver' => 'a21', 'model' => 'jamba-large-1.7'],
        'anthropic' => ['driver' => 'anthropic', 'model' => 'claude-3-5-sonnet-latest'],
        'deepseek' => ['driver' => 'deepseek', 'model' => 'deepseek-chat'],
        'deepseek-r' => ['driver' => 'deepseek', 'model' => 'deepseek-reasoner'],
        'gemini-oai' => ['driver' => 'gemini-oai', 'model' => 'gemini-2.5-flash'],
        'openai' => ['driver' => 'openai', 'model' => 'gpt-4o-mini'],
        'perplexity' => ['driver' => 'perplexity', 'model' => 'sonar'],
        'sambanova' => ['driver' => 'sambanova', 'model' => 'Meta-Llama-3.1-70B-Instruct'],
    ];

    private array $connections = [];
    private array $modes = [];
    private array $stream = [];
    private bool $filterByCapabilities = true;
    /** @var array<string, LLMConfig> */
    private array $connectionConfigs = [];

    private ?CanProvideInferenceDrivers $drivers = null;
    private ?CanSendHttpRequests $httpClient = null;
    private ?CanHandleEvents $events = null;

    /**
     * @param array<string> $connections
     * @param array<OutputMode> $modes
     * @param array<bool> $stream
     * @param array<string, array<string, mixed>|LLMConfig> $connectionConfigs
     */
    private function __construct(
        array $connections = [],
        array $modes = [],
        array $stream = [],
        bool $filterByCapabilities = true,
        array $connectionConfigs = [],
    ) {
        $this->connections = $connections;
        $this->modes = $modes;
        $this->stream = $stream;
        $this->filterByCapabilities = $filterByCapabilities;
        $this->connectionConfigs = $this->normalizeConnectionConfigs($connectionConfigs);
    }

    /**
     * @param array<string, array<string, mixed>|LLMConfig> $connectionConfigs
     */
    public static function all(
        bool $filterByCapabilities = true,
        array $connectionConfigs = [],
    ) : Generator {
        return (new self(
            filterByCapabilities: $filterByCapabilities,
            connectionConfigs: $connectionConfigs,
        ))->initiateWithAll()->make();
    }

    /**
     * @param array<string> $connections
     * @param array<OutputMode> $modes
     * @param array<bool> $stream
     * @param array<string, array<string, mixed>|LLMConfig> $connectionConfigs
     */
    public static function except(
        array $connections = [],
        array $modes = [],
        array $stream = [],
        bool $filterByCapabilities = true,
        array $connectionConfigs = [],
    ) : Generator {
        $instance = (new self(
            filterByCapabilities: $filterByCapabilities,
            connectionConfigs: $connectionConfigs,
        ))->initiateWithAll();
        $instance->connections = match (true) {
            [] === $connections => $instance->connections,
            default => array_values(array_diff($instance->connections, $connections)),
        };
        $instance->modes = match (true) {
            [] === $modes => $instance->modes,
            default => array_values(array_filter($instance->modes, fn($mode) => !$mode->isIn($modes))),
        };
        $instance->stream = match (true) {
            [] === $stream => $instance->stream,
            default => array_values(array_diff($instance->stream, $stream)),
        };
        return $instance->make();
    }

    /**
     * @param array<string> $connections
     * @param array<OutputMode> $modes
     * @param array<bool> $stream
     * @param array<string, array<string, mixed>|LLMConfig> $connectionConfigs
     */
    public static function only(
        array $connections = [],
        array $modes = [],
        array $stream = [],
        bool $filterByCapabilities = true,
        array $connectionConfigs = [],
    ) : Generator {
        $instance = (new self(
            filterByCapabilities: $filterByCapabilities,
            connectionConfigs: $connectionConfigs,
        ))->initiateWithAll();
        $instance->connections = match (true) {
            [] === $connections => $instance->connections,
            default => array_values(array_intersect($instance->connections, $connections)),
        };
        $instance->modes = match (true) {
            [] === $modes => $instance->modes,
            default => array_values(array_filter($instance->modes, fn($mode) => $mode->isIn($modes))),
        };
        $instance->stream = match (true) {
            [] === $stream => $instance->stream,
            default => array_values(array_intersect($instance->stream, $stream)),
        };
        return $instance->make();
    }

    private function initiateWithAll() : self {
        $instance = new self(
            connections: $this->connections(),
            modes: $this->modes(),
            stream: $this->streamingModes(),
            filterByCapabilities: $this->filterByCapabilities,
            connectionConfigs: $this->connectionConfigs,
        );
        $instance->drivers = $this->drivers;
        $instance->httpClient = $this->httpClient;
        $instance->events = $this->events;
        return $instance;
    }

    /**
     * @return Generator<int, InferenceCaseParams>
     */
    private function make() : Generator {
        $generator = Combination::generator(
            mapping: InferenceCaseParams::class,
            sources: [
                'isStreamed' => $this->stream ?: $this->streamingModes(),
                'mode' => $this->modes ?: $this->modes(),
                'connection' => $this->connections ?: $this->connections(),
            ],
        );

        foreach ($generator as $case) {
            $case->llmConfig = $this->connectionConfigs[$case->connection] ?? null;
            if (!$this->filterByCapabilities) {
                yield $case;
                continue;
            }

            if ($this->isSupported($case)) {
                yield $case;
            }
        }
    }

    private function isSupported(InferenceCaseParams $case) : bool {
        try {
            $driver = $this->getDriverForConnection($case->llmConfig);
            if ($driver === null) {
                return true;
            }

            $capabilities = $driver->capabilities();
            $supportsMode = match ($case->mode) {
                OutputMode::Json => $capabilities->supportsResponseFormatJsonObject(),
                OutputMode::JsonSchema => $capabilities->supportsResponseFormatJsonSchema(),
                OutputMode::Tools => $capabilities->supportsToolCalling(),
                default => true,
            };

            if (!$supportsMode) {
                return false;
            }

            if ($case->isStreamed && !$capabilities->supportsStreaming()) {
                return false;
            }

            return true;
        } catch (\Exception) {
            return true;
        }
    }

    private function getDriverForConnection(?LLMConfig $config) : ?CanProcessInferenceRequest {
        if ($config === null) {
            return null;
        }

        return $this->getDrivers()->makeDriver(
            name: $config->driver,
            config: $config,
            httpClient: $this->getHttpClient(),
            events: $this->getEvents(),
        );
    }

    private function getDrivers() : CanProvideInferenceDrivers {
        if ($this->drivers === null) {
            $this->drivers = BundledInferenceDrivers::registry();
        }
        return $this->drivers;
    }

    private function getHttpClient() : CanSendHttpRequests {
        if ($this->httpClient === null) {
            $this->httpClient = (new HttpClientBuilder())
                ->withDriver(new MockHttpDriver())
                ->create();
        }
        return $this->httpClient;
    }

    private function getEvents() : CanHandleEvents {
        if ($this->events === null) {
            $this->events = new EventDispatcher();
        }
        return $this->events;
    }

    /** @return array<string> */
    private function connections() : array {
        return array_keys($this->connectionConfigs);
    }

    /**
     * @param array<string, array<string, mixed>|LLMConfig> $connectionConfigs
     * @return array<string, LLMConfig>
     */
    private function normalizeConnectionConfigs(array $connectionConfigs) : array {
        $source = match (true) {
            [] === $connectionConfigs => self::DEFAULT_CONNECTION_CONFIGS,
            default => $connectionConfigs,
        };

        $normalized = [];
        foreach ($source as $connection => $config) {
            $normalized[$connection] = match (true) {
                $config instanceof LLMConfig => $config,
                is_array($config) => LLMConfig::fromArray($config),
                default => throw new \InvalidArgumentException("LLM config for connection '{$connection}' must be array or LLMConfig."),
            };
        }
        return $normalized;
    }

    /** @return array<bool> */
    private function streamingModes() : array {
        return [
            true,
            false,
        ];
    }

    /** @return array<OutputMode> */
    private function modes() : array {
        return [
            OutputMode::Text,
            OutputMode::MdJson,
            OutputMode::Json,
            OutputMode::JsonSchema,
            OutputMode::Tools,
            OutputMode::Unrestricted,
        ];
    }

    public function withDrivers(CanProvideInferenceDrivers $drivers) : self {
        $this->drivers = $drivers;
        return $this;
    }

    public function withHttpClient(CanSendHttpRequests $httpClient) : self {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function withEvents(CanHandleEvents $events) : self {
        $this->events = $events;
        return $this;
    }

    /**
     * @param array<string, array<string, mixed>|LLMConfig> $connectionConfigs
     */
    public function withConnectionConfigs(array $connectionConfigs) : self {
        $this->connectionConfigs = $this->normalizeConnectionConfigs($connectionConfigs);
        return $this;
    }
}
