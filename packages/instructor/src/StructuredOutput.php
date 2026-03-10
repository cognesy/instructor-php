<?php declare(strict_types=1);

namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

/**
 * @template TResponse
 */
final class StructuredOutput implements CanCreateStructuredOutput
{
    private StructuredOutputRequest $request;
    private CanCreateStructuredOutput $runtime;

    public function __construct(
        ?CanCreateStructuredOutput $runtime = null,
    ) {
        $this->runtime = $runtime ?? StructuredOutputRuntime::fromDefaults();
        $this->request = new StructuredOutputRequest();
    }

    public function withRuntime(CanCreateStructuredOutput $runtime): self {
        $copy = clone $this;
        $copy->runtime = $runtime;
        return $copy;
    }

    public static function fromConfig(LLMConfig $config): self {
        return new self(StructuredOutputRuntime::fromConfig($config));
    }

    public static function using(string $preset, ?string $basePath = null): self {
        return self::fromConfig(LLMConfig::fromPreset($preset, $basePath));
    }

    public function withMessages(string|array|Message|Messages $messages): self {
        $copy = clone $this;
        $copy->request = $copy->request->withMessages($messages);
        return $copy;
    }

    public function withInput(string|array|object $input): self {
        $copy = clone $this;
        $copy->request = $copy->request->withMessages(Messages::fromInput($input));
        return $copy;
    }

    public function withResponseModel(string|array|object $responseModel): self {
        $copy = clone $this;
        $copy->request = $copy->request->withRequestedSchema($responseModel);
        return $copy;
    }

    public function withResponseJsonSchema(array|CanProvideJsonSchema $jsonSchema): self {
        $copy = clone $this;
        $copy->request = $copy->request->withRequestedSchema($jsonSchema);
        return $copy;
    }

    /**
     * @param class-string<TResponse> $class
     * @return StructuredOutput<TResponse>
     */
    public function withResponseClass(string $class): StructuredOutput {
        $copy = clone $this;
        $copy->request = $copy->request->withRequestedSchema($class);
        return $copy;
    }

    /**
     * @param object<TResponse> $responseObject
     * @return StructuredOutput<TResponse>
     */
    public function withResponseObject(object $responseObject): StructuredOutput {
        $copy = clone $this;
        $copy->request = $copy->request->withRequestedSchema($responseObject);
        return $copy;
    }

    public function withSystem(string $system): self {
        $copy = clone $this;
        $copy->request = $copy->request->withSystem($system);
        return $copy;
    }

    public function withPrompt(string $prompt): self {
        $copy = clone $this;
        $copy->request = $copy->request->withPrompt($prompt);
        return $copy;
    }

    public function withExamples(array $examples): self {
        $copy = clone $this;
        $copy->request = $copy->request->withExamples($examples);
        return $copy;
    }

    public function withModel(string $model): self {
        $copy = clone $this;
        $copy->request = $copy->request->withModel($model);
        return $copy;
    }

    public function withOptions(array $options): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOptions($options);
        return $copy;
    }

    public function withOption(string $key, mixed $value): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOptions([$key => $value]);
        return $copy;
    }

    public function withStreaming(bool $stream = true): self {
        $copy = clone $this;
        $copy->request = $copy->request->withStreamed($stream);
        return $copy;
    }

    public function withCachedContext(
        string|array $messages = '',
        string $system = '',
        string $prompt = '',
        array $examples = [],
    ): self {
        $copy = clone $this;
        $copy->request = $copy->request->withCachedContext(
            new CachedContext($messages, $system, $prompt, $examples)
        );
        return $copy;
    }

    public function intoArray(): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOutputFormat(OutputFormat::array());
        return $copy;
    }

    /** @param class-string $class */
    public function intoInstanceOf(string $class): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOutputFormat(OutputFormat::instanceOf($class));
        return $copy;
    }

    public function intoObject(CanDeserializeSelf $object): self {
        $copy = clone $this;
        $copy->request = $copy->request->withOutputFormat(OutputFormat::selfDeserializing($object));
        return $copy;
    }

    public function withRequest(StructuredOutputRequest $request): self {
        $copy = clone $this;
        $copy->request = $request;
        return $copy;
    }

    /**
     * @phpstan-ignore-next-line
     */
    public function with(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null $responseModel = null,
        ?string $system = null,
        ?string $prompt = null,
        ?array $examples = null,
        ?string $model = null,
        ?array $options = null,
    ): self {
        $copy = clone $this;
        $copy->request = $copy->request->with(
            messages: $messages,
            requestedSchema: $responseModel,
            system: $system,
            prompt: $prompt,
            examples: $examples,
            model: $model,
            options: $options,
        );
        return $copy;
    }

    #[\Override]
    public function create(?StructuredOutputRequest $request = null): PendingStructuredOutput {
        $request = $request ?? $this->request;
        if (!$request->hasRequestedSchema()) {
            throw new \InvalidArgumentException('Response model cannot be empty. Provide a class name, instance, or schema array.');
        }
        return $this->runtime->create($request);
    }

    public function response(): StructuredOutputResponse {
        return $this->create()->response();
    }

    public function inferenceResponse(): InferenceResponse {
        return $this->create()->inferenceResponse();
    }

    public function stream(): StructuredOutputStream {
        return $this->withStreaming()->create()->stream();
    }

    public function get(): mixed {
        return $this->create()->get();
    }

    public function getString(): string {
        return $this->create()->getString();
    }

    public function getFloat(): float {
        return $this->create()->getFloat();
    }

    public function getInt(): int {
        return $this->create()->getInt();
    }

    public function getBoolean(): bool {
        return $this->create()->getBoolean();
    }

    public function getObject(): object {
        return $this->create()->getObject();
    }

    public function getArray(): array {
        return $this->create()->getArray();
    }
}
