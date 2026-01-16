<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors\Data;

use Cognesy\Config\Settings;
use Cognesy\Evals\Utils\Combination;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanHandleInference;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class InferenceCases
{
    private array $presets = [];
    private array $modes = [];
    private array $stream = [];
    private bool $filterByCapabilities = true;

    private ?InferenceDriverFactory $driverFactory = null;
    private ?HttpClient $httpClient = null;
    private ?EventDispatcherInterface $events = null;

    private function __construct(
        array $presets = [],
        array $modes = [],
        array $stream = [],
        bool $filterByCapabilities = true,
    ) {
        $this->presets = $presets;
        $this->modes = $modes;
        $this->stream = $stream;
        $this->filterByCapabilities = $filterByCapabilities;
    }

    public static function all(bool $filterByCapabilities = true) : Generator {
        return (new self(filterByCapabilities: $filterByCapabilities))->initiateWithAll()->make();
    }

    public static function except(
        array $presets = [],
        array $modes = [],
        array $stream = [],
        bool $filterByCapabilities = true,
    ) : Generator {
        $instance = (new self(filterByCapabilities: $filterByCapabilities))->initiateWithAll();
        $instance->presets = match(true) {
            [] === $presets => $instance->presets,
            default => array_diff($instance->presets, $presets),
        };
        $instance->modes = match(true) {
            [] === $modes => $instance->modes,
            default => array_filter($instance->modes, fn($mode) => !$mode->isIn($modes)),
        };
        $instance->stream = match(true) {
            [] === $stream => $instance->stream,
            default => array_diff($instance->stream, $stream),
        };
        return $instance->make();
    }

    public static function only(
        array $presets = [],
        array $modes = [],
        array $stream = [],
        bool $filterByCapabilities = true,
    ) : Generator {
        $instance = (new self(filterByCapabilities: $filterByCapabilities))->initiateWithAll();
        $instance->presets = match(true) {
            [] === $presets => $instance->presets,
            default => array_intersect($instance->presets, $presets),
        };
        $instance->modes = match(true) {
            [] === $modes => $instance->modes,
            default => array_filter($instance->modes, fn($mode) => $mode->isIn($modes)),
        };
        $instance->stream = match(true) {
            [] === $stream => $instance->stream,
            default => array_intersect($instance->stream, $stream),
        };
        return $instance->make();
    }

    // INTERNAL //////////////////////////////////////////////////

    private function initiateWithAll() : self {
        $instance = new self(
            presets: $this->presets(),
            modes: $this->modes(),
            stream: $this->streamingModes(),
            filterByCapabilities: $this->filterByCapabilities,
        );
        $instance->driverFactory = $this->driverFactory;
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
                'preset' => $this->presets ?: $this->presets(),
            ],
        );

        if (!$this->filterByCapabilities) {
            yield from $generator;
            return;
        }

        // Filter by driver capabilities
        foreach ($generator as $case) {
            if ($this->isSupported($case)) {
                yield $case;
            }
        }
    }

    /**
     * Check if the given case parameters are supported by the driver.
     */
    private function isSupported(InferenceCaseParams $case) : bool {
        try {
            $driver = $this->getDriverForPreset($case->preset);
            if ($driver === null) {
                return true; // If we can't determine, include the case
            }

            $capabilities = $driver->capabilities();

            // Check if the mode is supported
            if (!$capabilities->supportsOutputMode($case->mode)) {
                return false;
            }

            if ($case->mode === OutputMode::JsonSchema && !$capabilities->supportsJsonSchema()) {
                return false;
            }

            // For Tools mode, also check if tool calling is supported
            if ($case->mode === OutputMode::Tools && !$capabilities->supportsToolCalling()) {
                return false;
            }

            // Check if streaming is supported (only filter if streaming is requested)
            if ($case->isStreamed && !$capabilities->supportsStreaming()) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // If we can't resolve the driver, include the case (fail-open)
            return true;
        }
    }

    /**
     * Get driver instance for a given preset.
     */
    private function getDriverForPreset(string $preset) : ?CanHandleInference {
        $config = $this->getConfigForPreset($preset);
        if ($config === null) {
            return null;
        }

        $factory = $this->getDriverFactory();
        $httpClient = $this->getHttpClient();

        return $factory->makeDriver($config, $httpClient);
    }

    /**
     * Get LLMConfig for a given preset name.
     */
    private function getConfigForPreset(string $preset) : ?LLMConfig {
        $presetData = Settings::get(LLMConfig::group(), "presets.{$preset}", []);
        if (empty($presetData)) {
            return null;
        }
        return LLMConfig::fromArray($presetData);
    }

    /**
     * Get or create the driver factory.
     */
    private function getDriverFactory() : InferenceDriverFactory {
        if ($this->driverFactory === null) {
            $this->driverFactory = new InferenceDriverFactory($this->getEvents());
        }
        return $this->driverFactory;
    }

    /**
     * Get or create a mock HTTP client (capabilities check doesn't need real HTTP).
     */
    private function getHttpClient() : HttpClient {
        if ($this->httpClient === null) {
            $this->httpClient = (new HttpClientBuilder())
                ->withDriver(new MockHttpDriver())
                ->create();
        }
        return $this->httpClient;
    }

    /**
     * Get or create an event dispatcher.
     */
    private function getEvents() : EventDispatcherInterface {
        if ($this->events === null) {
            $this->events = new EventDispatcher();
        }
        return $this->events;
    }

    private function presets() : array {
        $presets = Settings::get(LLMConfig::group(), 'presets', []);
        return array_keys($presets);
    }

    private function streamingModes() : array {
        return [
            true,
            false,
        ];
    }

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

    // DEPENDENCY INJECTION FOR TESTING /////////////////////////////

    /**
     * Set a custom driver factory for testing.
     */
    public function withDriverFactory(InferenceDriverFactory $factory) : self {
        $this->driverFactory = $factory;
        return $this;
    }

    /**
     * Set a custom HTTP client for testing.
     */
    public function withHttpClient(HttpClient $httpClient) : self {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * Set a custom event dispatcher for testing.
     */
    public function withEvents(EventDispatcherInterface $events) : self {
        $this->events = $events;
        return $this;
    }
}
