<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Streaming\InferenceStream;

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

    public static function using(string $preset): self {
        return new self(InferenceRuntime::using($preset));
    }

    public static function fromDsn(string $dsn): self {
        return new self(InferenceRuntime::fromDsn($dsn));
    }

    public static function fromRuntime(CanCreateInference $runtime): self {
        return new self($runtime);
    }

    public function withRuntime(CanCreateInference $runtime): self {
        $copy = clone $this;
        $copy->runtime = $runtime;
        return $copy;
    }

    public function runtime(): CanCreateInference {
        return $this->runtime;
    }

    /**
     * @param string|callable $driver
     */
    public static function registerDriver(string $name, string|callable $driver): void {
        InferenceDriverFactory::registerDriver($name, $driver);
    }

    public static function unregisterDriver(string $name): void {
        InferenceDriverFactory::unregisterDriver($name);
    }

    public static function resetDrivers(): void {
        InferenceDriverFactory::resetDrivers();
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

    // INVOCATION //////////////////////////////////////////////////////////

    public function with(
        string|array|null $messages = null,
        ?string      $model = null,
        ?array       $tools = null,
        string|array|null $toolChoice = null,
        ?array       $responseFormat = null,
        ?array       $options = null,
        ?OutputMode  $mode = null,
    ) : static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->with(
            messages: $messages,
            model: $model,
            tools: $tools,
            toolChoice: $toolChoice,
            responseFormat: $responseFormat,
            options: $options,
            mode: $mode,
        );
        return $copy;
    }

    public function withRequest(InferenceRequest $request): static {
        $copy = $this->cloneWithRequestBuilder();
        $copy->requestBuilder->withRequest($request);
        return $copy;
    }

    public function create(?InferenceRequest $request = null): PendingInference {
        return $this->runtime->create($request ?? $this->requestBuilder->create());
    }
}
