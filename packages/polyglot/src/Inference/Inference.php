<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Contracts\CanProvideInferenceDrivers;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;
use Cognesy\Messages\Messages;

/**
 * Inference class is facade for handling inference requests and responses.
 */
final class Inference implements CanCreateInference
{
    use Traits\HandlesRequestBuilder;

    private CanCreateInference $runtime;

    /**
     * Constructor for initializing dependencies and configurations.
     */
    public function __construct(
        ?CanCreateInference $runtime = null,
    ) {
        $this->requestBuilder = new InferenceRequestBuilder();
        $this->runtime = $runtime ?? InferenceRuntime::fromProvider(LLMProvider::new());
    }

    public static function fromConfig(LLMConfig $config, ?CanProvideInferenceDrivers $drivers = null): self {
        return new self(InferenceRuntime::fromConfig($config, drivers: $drivers));
    }

    public static function fromProvider(LLMProvider $provider, ?CanProvideInferenceDrivers $drivers = null): self {
        return new self(InferenceRuntime::fromProvider($provider, drivers: $drivers));
    }

    public static function fromRuntime(CanCreateInference $runtime): self {
        return new self($runtime);
    }

    public static function using(string $preset, ?string $basePath = null, ?CanProvideInferenceDrivers $drivers = null): self {
        return self::fromConfig(LLMConfig::fromPreset($preset, $basePath), drivers: $drivers);
    }

    public function withRuntime(CanCreateInference $runtime): self {
        $copy = clone $this;
        $copy->runtime = $runtime;
        return $copy;
    }

    // SHORTCUTS ///////////////////////////////////////////////////////////

    public function stream(): InferenceStream {
        return $this->withStreaming(true)->create()->stream();
    }

    public function response(): InferenceResponse {
        return $this->create()->response();
    }

    // Shortcuts for creating responses in different formats

    public function get(): string {
        return $this->create()->get();
    }

    public function asJson(): string {
        return $this->create()->asJson();
    }

    public function asJsonData(): array {
        return $this->create()->asJsonData();
    }

    public function asToolCallJson(): string {
        return $this->create()->asToolCallJson();
    }

    public function asToolCallJsonData(): array {
        return $this->create()->asToolCallJsonData();
    }

    // INVOCATION //////////////////////////////////////////////////////////

    public function with(
        ?Messages $messages = null,
        ?string $model = null,
        ?ToolDefinitions $tools = null,
        ?ToolChoice $toolChoice = null,
        ?ResponseFormat $responseFormat = null,
        ?array $options = null,
    ) : static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->with(
            messages: $messages,
            model: $model,
            tools: $tools,
            toolChoice: $toolChoice,
            responseFormat: $responseFormat,
            options: $options,
        );
        return $copy;
    }

    public function withRequest(InferenceRequest $request): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withRequest($request);
        return $copy;
    }

    #[\Override]
    public function create(?InferenceRequest $request = null): PendingInference {
        return $this->runtime->create($request ?? $this->requestBuilder->create());
    }
}
