<?php declare(strict_types=1);
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

class StructuredOutputRequest
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;

    protected Messages $messages;
    protected string $model = '';
    protected string $prompt = '';
    protected string $system = '';
    /** @var Example[] */
    protected array $examples = [];
    protected array $options = [];
    protected CachedContext $cachedContext;
    protected string|array|object $requestedSchema = [];
    protected ?ResponseModel $responseModel = null;
    protected StructuredOutputConfig $config;

    public function __construct(
        string|array|Message|Messages|null $messages = null,
        string|array|object|null $requestedSchema = null,
        ?ResponseModel $responseModel = null,
        ?string        $system = null,
        ?string        $prompt = null,
        ?array         $examples = null,
        ?string        $model = null,
        ?array         $options = null,
        ?CachedContext $cachedContext = null,
        ?StructuredOutputConfig $config = null,

        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();

        $this->messages = Messages::fromAny($messages);
        $this->requestedSchema = $requestedSchema;
        $this->responseModel = $responseModel;

        $this->options = $options ?: [];
        $this->prompt = $prompt ?: '';
        $this->examples = $examples ?: [];
        $this->system = $system ?: '';
        $this->model = $model ?: '';

        $this->config = $config;

        $this->cachedContext = $cachedContext ?: new CachedContext();
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

    public function config() : StructuredOutputConfig {
        return $this->config;
    }

    public function mode() : OutputMode {
        return $this->config->outputMode();
    }

    // MUTATORS /////////////////////////////////////////////////

    public function withStreamed(bool $streamed = true) : static {
        $new = $this->clone();
        $new->options['stream'] = $streamed;
        return $new;
    }

    // RETRIES //////////////////////////////////////////////////

    /** @var StructuredOutputAttempt[] */
    private array $failedResponses = [];
    private StructuredOutputAttempt $response;

    public function maxRetries(): int {
        return $this->config->maxRetries();
    }

    public function response(): StructuredOutputAttempt {
        return $this->response;
    }

    public function attempts(): array {
        return match (true) {
            !$this->hasAttempts() => [],
            !$this->hasResponse() => $this->failedResponses,
            default => array_merge(
                $this->failedResponses,
                [$this->response],
            )
        };
    }

    public function hasLastResponseFailed(): bool {
        return $this->hasFailures() && !$this->hasResponse();
    }

    public function lastFailedResponse(): ?StructuredOutputAttempt {
        return end($this->failedResponses) ?: null;
    }

    public function hasResponse(): bool {
        return isset($this->response) && $this->response !== null;
    }

    public function hasAttempts(): bool {
        return $this->hasResponse() || $this->hasFailures();
    }

    public function hasFailures(): bool {
        return count($this->failedResponses) > 0;
    }

    public function setResponse(
        array $messages,
        InferenceResponse $inferenceResponse,
        array $partialInferenceResponses = [],
        mixed $returnedValue = null,
    ) {
        $this->response = new StructuredOutputAttempt($messages, $inferenceResponse, $partialInferenceResponses, [], $returnedValue);
    }

    public function addFailedResponse(
        array $messages,
        InferenceResponse $inferenceResponse,
        array $partialInferenceResponses = [],
        array $errors = [],
    ) {
        $this->failedResponses[] = new StructuredOutputAttempt($messages, $inferenceResponse, $partialInferenceResponses, $errors, null);
    }

    // SCHEMA ///////////////////////////////////////////////////

    public function responseModel() : ?ResponseModel {
        return $this->responseModel;
    }

    public function requestedSchema() : string|array|object {
        return $this->requestedSchema;
    }

    public function toolName() : string {
        return $this->responseModel
            ? $this->responseModel->toolName()
            : $this->config->toolName();
    }

    public function toolDescription() : string {
        return $this->responseModel
            ? $this->responseModel->toolDescription()
            : $this->config->toolDescription();
    }

    public function responseFormat() : array {
        return match($this->mode()) {
            OutputMode::Json => [
                'type' => 'json_object',
                'schema' => $this->jsonSchema(),
            ],
            OutputMode::JsonSchema => [
                'type' => 'json_schema',
                'description' => $this->toolDescription(),
                'json_schema' => [
                    'name' => $this->schemaName(),
                    'schema' => $this->jsonSchema(),
                    'strict' => true,
                ],
            ],
            default => []
        };
    }

    public function jsonSchema() : ?array {
        return $this->responseModel?->toJsonSchema();
    }

    public function toolCallSchema() : ?array {
        return match($this->mode()) {
            OutputMode::Tools => $this->responseModel?->toolCallSchema(),
            default => [],
        };
    }

    public function toolChoice() : string|array {
        return match($this->mode()) {
            OutputMode::Tools => [
                'type' => 'function',
                'function' => [
                    'name' => ($this->toolName() ?: 'extract_data'),
                ]
            ],
            default => [],
        };
    }

    public function schemaName() : string {
        return $this->responseModel?->schemaName() ?? $this->config->schemaName() ?? 'default_schema';
    }

    // SERIALIZATION ////////////////////////////////////////////

    public function toArray() : array {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'messages' => $this->messages->toArray(),
            'responseModel' => $this->responseModel,
            'system' => $this->system,
            'prompt' => $this->prompt,
            'examples' => $this->examples,
            'model' => $this->model,
            'options' => $this->options,
            'mode' => $this->mode(),
            'cachedContext' => $this->cachedContext?->toArray() ?? [],
            'config' => $this->config->toArray(),
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            messages: $data['messages'] ?? null,
            requestedSchema: $data['requestedSchema'] ?? null,
            responseModel: $data['responseModel'] ?? null,
            system: $data['system'] ?? null,
            prompt: $data['prompt'] ?? null,
            examples: $data['examples'] ?? null,
            model: $data['model'] ?? null,
            options: $data['options'] ?? null,
            cachedContext: $data['cachedContext'] ?? null,
            config: $data['config'] ?? null,
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
        );
    }

    public function clone() : self {
        return new self(
            messages: $this->messages->clone(),
            requestedSchema: is_object($this->requestedSchema)
                ? clone $this->requestedSchema
                : $this->requestedSchema,
            responseModel: $this->responseModel->clone(),
            system: $this->system,
            prompt: $this->prompt,
            examples: $this->cloneExamples(),
            model: $this->model,
            options: $this->options,
            cachedContext: $this->cachedContext->clone(),
            config: $this->config->clone(),
        );
    }

    private function cloneExamples() {
        return is_array($this->examples)
            ? array_map(fn($e) => $e instanceof Example ? $e->clone() : $e, $this->examples)
            : $this->examples;
    }
}
