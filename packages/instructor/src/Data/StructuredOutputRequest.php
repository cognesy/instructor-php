<?php declare(strict_types=1);
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Enums\OutputFormatType;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use ReflectionClass;

class StructuredOutputRequest
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;
    public readonly DateTimeImmutable $updatedAt;

    protected Messages $messages;
    protected string $model = '';
    protected string $prompt = '';
    protected string $system = '';
    /** @var Example[] */
    protected array $examples = [];
    protected array $options = [];
    protected CachedContext $cachedContext;
    protected string|array|object $requestedSchema = [];
    protected ?OutputFormat $outputFormat = null;


    public function __construct(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null $requestedSchema = null,
        ?string        $system = null,
        ?string        $prompt = null,
        ?array         $examples = null,
        ?string        $model = null,
        ?array         $options = null,
        ?CachedContext $cachedContext = null,
        ?OutputFormat  $outputFormat = null,

        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->messages = Messages::fromAny($messages ?? []);
        $this->requestedSchema = $requestedSchema ?? [];

        $this->options = $options ?: [];
        $this->prompt = $prompt ?: '';
        $this->examples = $examples ?: [];
        $this->system = $system ?: '';
        $this->model = $model ?: '';

        $this->cachedContext = $cachedContext ?: new CachedContext();
        $this->outputFormat = $outputFormat;
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function messages() : Messages {
        return $this->messages;
    }

    public function options() : array {
        return $this->options;
    }

    public function option(string $name) : mixed {
        return $this->options[$name] ?? null;
    }

    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    public function prompt() : string {
        return $this->prompt;
    }

    public function system() : string {
        return $this->system;
    }

    public function examples() : array {
        return $this->examples;
    }

    public function cachedContext() : CachedContext {
        return $this->cachedContext;
    }

    public function model() : string {
        return $this->model;
    }

    public function requestedSchema() : string|array|object {
        return $this->requestedSchema;
    }

    public function outputFormat() : ?OutputFormat {
        return $this->outputFormat;
    }

    // MUTATORS /////////////////////////////////////////////////

    public function with(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null $requestedSchema = null,
        ?string        $system = null,
        ?string        $prompt = null,
        ?array         $examples = null,
        ?string        $model = null,
        ?array         $options = null,
        ?CachedContext $cachedContext = null,
        ?OutputFormat  $outputFormat = null,
    ) : static {
        return new static(
            messages: $messages ?? $this->messages,
            requestedSchema: $requestedSchema ?? $this->requestedSchema,
            system: $system ?? $this->system,
            prompt: $prompt ?? $this->prompt,
            examples: $examples ?? $this->examples,
            model: $model ?? $this->model,
            options: $options ?? $this->options,
            cachedContext: $cachedContext ?? $this->cachedContext,
            outputFormat: $outputFormat ?? $this->outputFormat,
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withMessages(string|array|Message|Messages $messages) : static {
        return $this->with(messages: $messages);
    }

    public function withRequestedSchema(string|array|object $requestedSchema) : static {
        return $this->with(requestedSchema: $requestedSchema);
    }

    public function withSystem(string $system) : static {
        return $this->with(system: $system);
    }

    public function withPrompt(string $prompt) : static {
        return $this->with(prompt: $prompt);
    }

    public function withExamples(array $examples) : static {
        return $this->with(examples: $examples);
    }

    public function withModel(string $model) : static {
        return $this->with(model: $model);
    }

    public function withOptions(array $options) : static {
        return $this->with(options: array_merge($this->options, $options));
    }

    public function withStreamed(bool $streamed = true) : static {
        return $this->withOptions(array_merge($this->options, ['stream' => $streamed]));
    }

    public function withCachedContext(CachedContext $cachedContext) : static {
        return $this->with(cachedContext: $cachedContext);
    }

    public function withOutputFormat(OutputFormat $outputFormat) : static {
        return $this->with(outputFormat: $outputFormat);
    }

    // SERIALIZATION ////////////////////////////////////////////

    public function toArray() : array {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'messages' => $this->messages->toArray(),
            'system' => $this->system,
            'prompt' => $this->prompt,
            'examples' => $this->examples,
            'model' => $this->model,
            'options' => $this->options,
            'cachedContext' => $this->cachedContext->toArray(),
            'requestedSchema' => $this->requestedSchema,
            'outputFormat' => $this->outputFormat !== null ? [
                'type' => $this->outputFormat->type->value,
                'class' => $this->outputFormat->targetClass(),
            ] : null,
        ];
    }

    public static function fromArray(array $data) : self {
        $rawOutputFormat = $data['outputFormat'] ?? null;
        $outputFormat = match (true) {
            $rawOutputFormat instanceof OutputFormat => $rawOutputFormat,
            is_array($rawOutputFormat) => self::outputFormatFromArray($rawOutputFormat),
            default => null,
        };
        return new self(
            messages: $data['messages'] ?? null,
            requestedSchema: $data['requestedSchema'] ?? null,
            system: $data['system'] ?? null,
            prompt: $data['prompt'] ?? null,
            examples: $data['examples'] ?? null,
            model: $data['model'] ?? null,
            options: $data['options'] ?? null,
            cachedContext: $data['cachedContext'] ?? null,
            outputFormat: $outputFormat,
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new DateTimeImmutable($data['updatedAt']) : null,
        );
    }

    private static function outputFormatFromArray(?array $data) : ?OutputFormat {
        if ($data === null) {
            return null;
        }
        $type = OutputFormatType::tryFrom($data['type'] ?? '');
        return match (true) {
            $type === OutputFormatType::Array => OutputFormat::array(),
            $type === OutputFormatType::Class && isset($data['class']) => OutputFormat::instanceOf($data['class']),
            $type === OutputFormatType::Object => self::selfDeserializingFromClass($data['class'] ?? null),
            default => null,
        };
    }

    private static function selfDeserializingFromClass(?string $class) : ?OutputFormat {
        if ($class === null || !class_exists($class)) {
            return null;
        }
        if (!is_subclass_of($class, CanDeserializeSelf::class)) {
            return null;
        }
        $reflection = new ReflectionClass($class);
        $instance = $reflection->newInstanceWithoutConstructor();
        return OutputFormat::selfDeserializing($instance);
    }
}
