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
        ?StructuredOutputAttempts $responseAttempts = null,
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
        $this->responseAttempts = $responseAttempts ?: new StructuredOutputAttempts();
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

    public function with(
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
    ) : static {
        return new static(
            messages: $messages ?? $this->messages,
            requestedSchema: $requestedSchema ?? $this->requestedSchema,
            responseModel: $responseModel ?? $this->responseModel,
            system: $system ?? $this->system,
            prompt: $prompt ?? $this->prompt,
            examples: $examples ?? $this->examples,
            model: $model ?? $this->model,
            options: $options ?? $this->options,
            cachedContext: $cachedContext ?? $this->cachedContext,
            config: $config ?? $this->config,
        );
    }

    public function withMessages(string|array|Message|Messages $messages) : static {
        return $this->with(messages: $messages);
    }

    public function withRequestedSchema(string|array|object $requestedSchema) : static {
        return $this->with(requestedSchema: $requestedSchema);
    }

    public function withResponseModel(ResponseModel $responseModel) : static {
        return $this->with(responseModel: $responseModel);
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

    public function withConfig(StructuredOutputConfig $config) : static {
        return $this->with(config: $config);
    }

    // RETRIES //////////////////////////////////////////////////

    private StructuredOutputAttempts $responseAttempts;

    public function maxRetries(): int {
        return $this->config->maxRetries();
    }

    public function response(): StructuredOutputAttempt {
        return $this->responseAttempts->response();
    }

    public function attempts(): array {
        return $this->responseAttempts->attempts();
    }

    public function hasLastResponseFailed(): bool {
        return $this->responseAttempts->hasLastResponseFailed();
    }

    public function lastFailedResponse(): ?StructuredOutputAttempt {
        return $this->responseAttempts->lastFailedResponse();
    }

    public function hasResponse(): bool {
        return $this->responseAttempts->hasResponse();
    }

    public function hasAttempts(): bool {
        return $this->responseAttempts->hasAttempts();
    }

    public function hasFailures(): bool {
        return $this->responseAttempts->hasFailures();
    }

    public function setResponse(
        array $messages,
        InferenceResponse $inferenceResponse,
        array $partialInferenceResponses = [],
        mixed $returnedValue = null,
    ) {
        $this->responseAttempts->setResponse($messages, $inferenceResponse, $partialInferenceResponses, $returnedValue);
    }

    public function addFailedResponse(
        array $messages,
        InferenceResponse $inferenceResponse,
        array $partialInferenceResponses = [],
        array $errors = [],
    ) {
        $this->responseAttempts->addFailedResponse($messages, $inferenceResponse, $partialInferenceResponses, $errors);
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
            'requestedSchema' => $this->requestedSchema,
            'responseAttempts' => $this->responseAttempts?->toArray() ?? [],
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
            responseAttempts: $data['responseAttempts'] ?? null
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
            id: $this->id,
            createdAt: $this->createdAt,
            responseAttempts: $this->responseAttempts?->clone(),
        );
    }

    private function cloneExamples() {
        return is_array($this->examples)
            ? array_map(fn($e) => $e instanceof Example ? $e->clone() : $e, $this->examples)
            : $this->examples;
    }
}
