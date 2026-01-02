# InstructorPHP Deep Dive Analysis (IN PROGRESS)

**Status:** Partial analysis - still exploring
**Date:** 2026-01-02
**Purpose:** Comprehensive technical analysis of instructor-php structured output mechanisms

---

## Architecture Overview

InstructorPHP uses a sophisticated **multi-layered architecture** with clear separation of concerns:

1. **Facade Layer** - `StructuredOutput` (entry point)
2. **Orchestration Layer** - `PendingStructuredOutput` (execution coordinator)
3. **Core Processing** - `RequestMaterializer`, `ResponseGenerator`, `AttemptIterator`
4. **Streaming Pipeline** - `StructuredOutputStream` + 3 pipeline modes
5. **Schema System** - Visitor pattern-based schema generation
6. **Deserialization** - Symfony Serializer with custom normalizers
7. **Validation** - Pluggable validation with retry support

### Key Design Patterns

- **Builder Pattern**: `StructuredOutputConfigBuilder`, `StructuredOutputRequestBuilder`
- **Visitor Pattern**: Schema conversion (`SchemaToJsonSchema`)
- **Strategy Pattern**: Multiple deserializers, validators, transformers
- **State Machine**: `StructuredOutputExecution` (immutable state)
- **Iterator Pattern**: Streaming via generators
- **Pipeline Pattern**: Modular streaming with transducers
- **Result Monad**: `Result<T>` for error handling

---

## Complete Execution Flow

### Non-Streaming Mode

```
StructuredOutput::with() -> configure request/config
  ↓
StructuredOutput::create() -> builds execution context
  ├── Creates StructuredOutputExecution (state)
  ├── Creates ResponseDeserializer, ResponseValidator, ResponseTransformer
  ├── Creates ResponseIteratorFactory
  └── Returns PendingStructuredOutput
      ↓
PendingStructuredOutput::get()
  ├── Checks if streamed → if yes, redirects to stream().finalValue()
  └── Calls getResponse()
      ↓
getResponse() [lines 92-124 in PendingStructuredOutput.php]
  ├── Dispatches StructuredOutputStarted event
  ├── Loop: while (attemptHandler.hasNext(execution))
  │   └── execution = attemptHandler.nextUpdate(execution)
  ├── Returns execution.inferenceResponse()
  └── Dispatches StructuredOutputResponseGenerated event
      ↓
AttemptIterator::nextUpdate() [lines 46-55 in AttemptIterator.php]
  ├── If !attemptActive → startNewAttempt()
  ├── Else → streamIterator.nextChunk(execution)
  └── If stream just finished → finalizeAttempt()
      ↓
AttemptIterator::finalizeAttempt() [lines 85-131]
  ├── Get finalInference from attemptState
  ├── validationResult = responseGenerator.makeResponse()
  │   ↓
  │   ResponseGenerator::makeResponse() [lines 35-43 in ResponseGenerator.php]
  │   ├── If response already has value → return success
  │   ├── Extract JSON from response
  │   └── Run through pipeline:
  │       ├── 1. Check JSON exists
  │       ├── 2. Deserialize (ResponseDeserializer)
  │       ├── 3. Validate (ResponseValidator)
  │       ├── 4. Transform (ResponseTransformer)
  │       └── Return Result<object>
  │
  ├── If success → return execution.withSuccessfulAttempt()
  ├── If failure:
  │   ├── failed = retryPolicy.recordFailure()
  │   ├── If shouldRetry() → prepareRetry()
  │   └── Else → finalizeOrThrow()
  └── Return updated execution
```

### Streaming Mode

```
StructuredOutput::stream()
  ↓
PendingStructuredOutput::stream() [lines 84-88]
  ├── execution = execution.withStreamed()
  ├── handler = executorFactory.makeExecutor(execution)
  └── Return new StructuredOutputStream(execution, handler, events)
      ↓
StructuredOutputStream::partials() [lines 79-84]
  └── foreach streamResponses() as partialResponse
      └── yield partialResponse.value()
          ↓
streamResponses() [lines 178-192 in StructuredOutputStream.php]
  └── foreach getStream(execution) as execution
      ├── response = execution.inferenceResponse()
      ├── lastResponse = response
      ├── Dispatch StructuredOutputResponseUpdated event
      └── yield response
          ↓
getStream() [lines 200-207]
  ├── Dispatch StructuredOutputStarted event
  └── Match cacheProcessedResponse:
      ├── false → streamWithoutCaching()
      └── true → streamWithCaching()
          ↓
streamWithoutCaching() [lines 214-220]
  └── while attemptHandler.hasNext(execution)
      ├── execution = attemptHandler.nextUpdate(execution)
      └── yield execution
```

---

## 1. Entry Point: StructuredOutput Facade

**File:** `packages/instructor/src/StructuredOutput.php`

### Responsibilities
- Fluent API for configuration
- Composition of dependencies (validators, deserializers, transformers)
- LLM provider integration
- HTTP client management

### Key Methods

**Configuration:**
```php
// Line 103-134
public function with(
    string|array|Message|Messages|null $messages = null,
    string|array|object|null $responseModel = null,
    ?string $system = null,
    ?string $prompt = null,
    ?array $examples = null,
    ?string $model = null,
    ?int $maxRetries = null,
    ?array $options = null,
    ?string $toolName = null,
    ?string $toolDescription = null,
    ?string $retryPrompt = null,
    ?OutputMode $mode = null,
) : static
```

**Execution Creation:**
```php
// Lines 144-197
public function create() : PendingStructuredOutput {
    $config = $this->configBuilder->create();
    $request = $this->requestBuilder->create();
    $execution = $this->executionBuilder->createWith(
        request: $request,
        config: $config,
    );

    $responseDeserializer = new ResponseDeserializer(/* ... */);
    $responseValidator = new ResponseValidator(/* ... */);
    $responseTransformer = new ResponseTransformer(/* ... */);

    $executorFactory = new ResponseIteratorFactory(/* ... */);

    return new PendingStructuredOutput(
        execution: $execution,
        executorFactory: $executorFactory,
        events: $this->events,
    );
}
```

### Dependencies Injected
- **Validators**: Default `SymfonyValidator`, overridable via `withValidators()`
- **Deserializers**: Default `SymfonyDeserializer`, overridable via `withDeserializers()`
- **Transformers**: Default none, addable via `withTransformers()`

---

## 2. Execution State: StructuredOutputExecution

**File:** `packages/instructor/src/Data/StructuredOutputExecution.php`

### Design: Immutable State Machine

**Immutability Pattern:**
```php
// Lines 16-60
final readonly class StructuredOutputExecution {
    private string $id;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private int $step;

    private StructuredOutputRequest $request;
    private StructuredOutputConfig $config;
    private ?ResponseModel $responseModel;

    private StructuredOutputAttemptList $attempts;
    private StructuredOutputAttempt $currentAttempt;
    private bool $isFinalized;
    private ?StructuredOutputAttemptState $attemptState;
```

### State Transitions

**Key Predicates:**
```php
// Line 100
public function maxRetriesReached(): bool {
    return $this->attempts->count() > $this->config->maxRetries();
}

public function isFinalized(): bool {
    return $this->isFinalized;
}

public function isAttemptActive(): bool {
    return $this->attemptState !== null && !$this->attemptState->isExhausted();
}

public function isFinalFailed(): bool {
    return !$this->isFinalized
        && $this->attempts->isNotEmpty()
        && $this->attempts->last()->isFailed();
}
```

**State Updates (Immutable):**
- `withSuccessfulAttempt()` - Records successful attempt, finalizes execution
- `withFailedAttempt()` - Records failed attempt with errors
- `withStreamed()` - Marks execution as streaming
- `withAttemptState()` - Updates streaming state

---

## 3. Request Materialization

**File:** `packages/instructor/src/Core/RequestMaterializer.php`

### Responsibilities
- Convert `StructuredOutputRequest` → LLM API messages
- Handle message sections (system, messages, prompt, examples)
- Support cached context (Anthropic prompt caching)
- Add retry feedback messages

### Message Store Sections

The system uses a **sectioned message store** for flexible composition:

```php
// Lines 45-60
$store = (new MessageStore())
    ->section('system')->appendMessages($this->makeSystem($messages, $request->system()))
    ->section('messages')->appendMessages($this->makeMessages($messages))
    ->section('prompt')->appendMessages($this->makePrompt($request->prompt()))
    ->section('examples')->setMessages($this->makeExamples($request->examples()));
```

**Section Order for Chat Structure:**
```php
// Line 35
$output = $this->withSections($store)
    ->select($execution->config()->chatStructure());
```

### Retry Message Injection

```php
// Lines 142-160
protected function addRetryMessages(
    StructuredOutputExecution $execution,
    MessageStore $store
) : MessageStore {
    if (!$execution->isFinalFailed()) {
        return $store;
    }

    $messages = [];
    foreach($execution->attempts() as $attempt) {
        $messages[] = [
            'role' => 'assistant',
            'content' => $attempt->inferenceResponse()?->content() ?? ''
        ];
        $retryFeedback = $execution->config()->retryPrompt()
            . Arrays::flattenToString($attempt->errors(), "; ");
        $messages[] = ['role' => 'user', 'content' => $retryFeedback];
    }
    return $store->section('retries')->setMessages(Messages::fromArray($messages));
}
```

### Cached Context Support

**Anthropic Prompt Caching:**
```php
// Lines 62-97
protected function makeCachedMessageStore(CachedContext $cachedContext) : MessageStore {
    // Adds cache_control: {type: 'ephemeral'} to sections:
    // - system (cached)
    // - cached-messages
    // - cached-prompt
    // - cached-examples
}
```

---

## 4. Response Generation Pipeline

**File:** `packages/instructor/src/Core/ResponseGenerator.php`

### Pipeline Architecture

Uses **Cognesy Pipeline** (functional pipeline library) with `ErrorStrategy::FailFast`:

```php
// Lines 47-63
private function makeResponsePipeline(ResponseModel $responseModel) : Pipeline {
    return Pipeline::builder(ErrorStrategy::FailFast)
        ->through(fn($responseContent) => match(true) {
            ($responseContent === '') => Result::failure('No JSON found'),
            default => Result::success($responseContent)
        })
        ->through(fn($responseContent) =>
            $this->responseDeserializer->deserialize($responseContent, $responseModel)
        )
        ->through(fn($responseObject) =>
            $this->responseValidator->validate($responseObject, $responseModel)
        )
        ->through(fn($responseObject) =>
            $this->responseTransformer->transform($responseObject, $responseModel)
        )
        ->tap(fn($responseObject) =>
            $this->events->dispatch(new ResponseConvertedToObject([...]))
        )
        ->onFailure(fn($state) =>
            $this->events->dispatch(new ResponseGenerationFailed([...]))
        )
        ->finally(fn(CanCarryState $state) => match(true) {
            $state->isSuccess() => $state->result(),
            default => Result::failure(implode('; ', $this->extractErrors($state)))
        })
        ->create();
}
```

### Pipeline Stages

1. **Empty Check** - Validates JSON exists
2. **Deserialization** - JSON → PHP object
3. **Validation** - Object validation (constraints, self-validation)
4. **Transformation** - Post-processing transformations
5. **Event Emission** - Success/failure events
6. **Error Aggregation** - Collect all errors

---

## 5. Retry Orchestration: AttemptIterator

**File:** `packages/instructor/src/Core/AttemptIterator.php`

### Composition

```php
// Lines 30-34
public function __construct(
    private CanStreamStructuredOutputUpdates $streamIterator,  // Streaming or sync
    private CanGenerateResponse $responseGenerator,            // Validation pipeline
    private CanDetermineRetry $retryPolicy,                   // Retry logic
) {}
```

### State Machine Logic

**hasNext()** - Determines if more updates are needed:
```php
// Lines 37-43
public function hasNext(StructuredOutputExecution $execution): bool {
    return match(true) {
        $execution->isFinalized() => false,
        $execution->isAttemptActive() => $this->streamIterator->hasNext($execution),
        default => !$execution->maxRetriesReached(),
    };
}
```

**nextUpdate()** - Advances execution state:
```php
// Lines 46-55
public function nextUpdate(StructuredOutputExecution $execution): StructuredOutputExecution {
    if (!$execution->isAttemptActive()) {
        return $this->startNewAttempt($execution);
    }
    $updated = $this->streamIterator->nextChunk($execution);
    return match(true) {
        $this->didStreamJustFinish($execution, $updated) => $this->finalizeAttempt($updated),
        default => $updated,
    };
}
```

### Finalization with Validation

```php
// Lines 85-131
private function finalizeAttempt(StructuredOutputExecution $execution): StructuredOutputExecution {
    $finalInference = $streamState->lastInference() ?? InferenceResponse::empty();
    $partial = $streamState->accumulatedPartial();

    // Validate response
    $validationResult = $this->responseGenerator->makeResponse(
        $finalInference,
        $responseModel,
        $execution->outputMode()
    );

    // Success path
    if ($validationResult->isSuccess()) {
        $finalValue = $validationResult->unwrap();
        return $execution->withSuccessfulAttempt(
            inferenceResponse: $finalInference->withValue($finalValue),
            partialInferenceResponse: $partial,
            returnedValue: $finalValue,
        );
    }

    // Failure path - record and decide on retry
    $failed = $this->retryPolicy->recordFailure(
        $execution, $validationResult, $finalInference, $partial
    );

    if ($this->retryPolicy->shouldRetry($failed, $validationResult)) {
        return $this->retryPolicy->prepareRetry($failed);
    }

    // No more retries
    $this->retryPolicy->finalizeOrThrow($failed, $validationResult);
    return $failed;
}
```

---

## 6. Response Model

**File:** `packages/instructor/src/Data/ResponseModel.php`

### Dual Purpose: Schema + Instance

```php
// Lines 13-49
class ResponseModel implements CanProvideJsonSchema {
    private mixed $instance;        // Prototype object or instance
    private string $class;          // Original class name
    private Schema $schema;         // Internal schema representation
    private array $jsonSchema;      // JSON Schema format
    private string $toolName;       // For OutputMode::Tools
    private string $toolDescription;
    private string $schemaName;     // For OutputMode::JsonSchema
    private string $schemaDescription;
    private bool $useObjectReferences;
    private StructuredOutputConfig $config;
}
```

### Output Mode Adaptation

**JSON Mode:**
```php
// Lines 158-162
OutputMode::Json => [
    'type' => 'json_object',
    'schema' => $this->jsonSchema(),
]
```

**JSON Schema Mode (OpenAI Structured Outputs):**
```php
// Lines 163-172
OutputMode::JsonSchema => [
    'type' => 'json_schema',
    'description' => $this->toolDescription(),
    'json_schema' => [
        'name' => $this->schemaName(),
        'schema' => $this->jsonSchema(),
        'strict' => true,
    ],
]
```

**Tools Mode (Function Calling):**
```php
// Lines 149-154
OutputMode::Tools => $this->makeToolCallSchema()

// Lines 225-237
private function makeToolCallSchema() : array {
    return match(true) {
        $this->instance() instanceof CanHandleToolSelection =>
            $this->instance()->toToolCallsJson(),
        default => $toolCallBuilder->renderToolCall(
            $this->toJsonSchema(),
            $this->toolName,
            $this->toolDescription
        ),
    };
}
```

---

## 7. Streaming Architecture

### Three Pipeline Implementations

InstructorPHP has **3 distinct streaming pipelines** (configurable via `config()->responseIterator`):

1. **ModularPipeline** (default) - Clean transducer-based architecture
2. **DecoratedPipeline** (partials) - Optimized for immediate emissions
3. **GeneratorBased** (legacy) - Original implementation

**Selection:**
```php
// ResponseIteratorFactory line ~70
$pipeline = $execution->config()->responseIterator;
return match($pipeline) {
    'modular' => $this->makeModularStreamingIterator(),
    'partials' => $this->makePartialStreamingIterator(),
    'legacy' => $this->makeLegacyStreamingIterator($execution),
    default => $this->makeModularStreamingIterator(),
};
```

### ModularPipeline Architecture

**File:** `packages/instructor/src/ResponseIterators/ModularPipeline/ModularStreamFactory.php`

**Three-Stage Transducer Chain:**

1. **ExtractDelta** - Extract content deltas from LLM stream chunks
2. **DeserializeAndDeduplicate** - Accumulate JSON, deserialize to objects, deduplicate
3. **EnrichResponse** - Add metadata, wrap in InferenceResponse

**Composition:**
```php
Transducer::compose(
    ExtractDelta::make($llmProvider, $mode),
    DeserializeAndDeduplicate::make($deserializer, $responseModel),
    EnrichResponse::make($validator),
)
```

### StructuredOutputStream Public API

**File:** `packages/instructor/src/StructuredOutputStream.php`

```php
// Streaming methods:
public function partials() : Generator<TResponse>          // Yield partial objects
public function sequence() : Generator<Sequenceable>       // Yield completed sequence items
public function responses() : Generator<InferenceResponse> // Yield full responses
public function finalValue() : TResponse                   // Get final result
public function finalResponse() : InferenceResponse        // Get final response
public function lastUpdate() : TResponse                   // Last received value
public function lastResponse() : InferenceResponse         // Last response object
public function usage() : Usage                            // Aggregated usage
```

---

## 8. Schema System

**Files:**
- `packages/schema/src/Factories/SchemaFactory.php`
- `packages/schema/src/Data/Schema/Schema.php` (base class)
- `packages/schema/src/Data/TypeDetails.php`
- `packages/schema/src/Visitors/SchemaToJsonSchema.php`

### Architecture: Type-Driven Schema Generation

**TypeDetails** - Core type representation:
```php
// Lines 23-33
public function __construct(
    public string $type,          // object, enum, array, int, string, bool, float
    public ?string $class = null,  // For objects/enums
    public ?TypeDetails $nestedType = null,  // For arrays
    public ?string $enumType = null,
    public ?array $enumValues = null,
    public ?string $docString = null,
) {}
```

**SchemaFactory** - Caching and Delegation:
```php
// Lines 49-84
public function schema(string|object $anyType) : Schema {
    // 1. Already a Schema → return
    if ($anyType instanceof Schema) return $anyType;

    // 2. CanProvideSchema → delegate
    if ($anyType instanceof CanProvideSchema) return $anyType->toSchema();

    // 3. CanProvideJsonSchema → convert
    if ($anyType instanceof CanProvideJsonSchema)
        return $this->schemaConverter->fromJsonSchema($anyType->toJsonSchema());

    // 4. Extract TypeDetails
    $type = match(true) {
        $anyType instanceof TypeDetails => $anyType,
        is_string($anyType) => TypeDetails::fromTypeName($anyType),
        default => throw new Exception('Unknown input type')
    };

    // 5. Cache lookup/register
    if (!$this->schemaMap->has($typeString)) {
        $this->schemaMap->register($typeString, $this->makeSchema($type));
    }
    return $this->schemaMap->get($typeString);
}
```

### Schema Hierarchy

```
Schema (base)
├── ScalarSchema (int, string, float, bool)
├── ArraySchema (untyped arrays)
├── CollectionSchema (typed arrays with item schema)
├── EnumSchema (PHP enums)
├── ObjectSchema (classes with properties)
├── ObjectRefSchema (references to objects - for $defs)
├── OptionSchema (nullable types)
└── MixedSchema (any type)
```

### Visitor Pattern for JSON Schema Conversion

**SchemaToJsonSchema** - Visitor implementation:
```php
// Lines 34-38
public function toArray(Schema $schema, ?callable $refCallback = null): array {
    $this->refCallback = $refCallback;
    $schema->accept($this);  // Visitor pattern
    return $this->result;
}

// Lines 76-100
public function visitObjectSchema(ObjectSchema $schema): void {
    // Special case: DateTime
    if (in_array($schema->typeDetails->class, [DateTime::class, DateTimeImmutable::class])) {
        $this->handleDateTimeSchema($schema);
        return;
    }

    // Build properties
    $propertyDefs = [];
    foreach ($schema->properties as $property) {
        $propertyDefs[$property->name] = (new SchemaToJsonSchema)->toArray($property, $this->refCallback);
    }

    $this->result = array_filter([
        'type' => 'object',
        'x-title' => $schema->name,
        'description' => $schema->description,
        'properties' => $propertyDefs,
        'required' => $schema->required,
        'x-php-class' => $schema->typeDetails->class,
    ]);
    $this->result['additionalProperties'] = false;
}
```

**Key Features:**
- Recursive schema building
- Automatic caching (prevents infinite loops on circular references)
- Special handling for DateTime (→ string with format)
- Support for `$defs` references (optional via `useObjectReferences`)
- Metadata preservation (`x-php-class`, `x-title`)

---

## 9. Deserialization System

**File:** `packages/instructor/src/Deserialization/Deserializers/SymfonyDeserializer.php`

### Architecture: Symfony Serializer Component

**Type Extraction Pipeline:**
```php
// Lines 73-97
protected function defaultTypeExtractor(): PropertyInfoExtractor {
    $phpDocExtractor = new PhpDocExtractor();
    $phpStanExtractor = new PhpStanExtractor();
    $reflectionExtractor = new ReflectionExtractor();

    return new PropertyInfoExtractor(
        listExtractors: [$reflectionExtractor],
        typeExtractors: [
            $phpStanExtractor,  // Highest priority - PHPStan types
            $phpDocExtractor,   // @param/@var annotations
            $reflectionExtractor // Reflection type hints
        ],
        descriptionExtractors: [$phpDocExtractor],
        accessExtractors: [$reflectionExtractor],
        initializableExtractors: [$reflectionExtractor],
    );
}
```

**Normalizer Stack:**
```php
// Lines 106-132
protected function defaultSerializer(PropertyInfoExtractor $typeExtractor): Serializer {
    return new Serializer(
        normalizers: [
            new FlexibleDateDenormalizer(),     // Custom: flexible date parsing
            new BackedEnumNormalizer(),         // Custom: PHP 8.1+ enums
            new ObjectNormalizer(               // Symfony: constructor args + properties
                classMetadataFactory: $classMetadataFactory,
                propertyAccessor: $propertyAccessor,
                propertyTypeExtractor: $typeExtractor,
            ),
            new PropertyNormalizer(             // Symfony: direct property access
                classMetadataFactory: $classMetadataFactory,
                propertyTypeExtractor: $typeExtractor,
            ),
            new GetSetMethodNormalizer(         // Symfony: getter/setter methods
                classMetadataFactory: $classMetadataFactory,
                propertyTypeExtractor: $typeExtractor,
            ),
            new ArrayDenormalizer(),            // Symfony: nested arrays
        ],
        encoders: [new JsonEncoder()]
    );
}
```

### Custom Normalizers

**BackedEnumNormalizer** - Handles PHP 8.1 enums
**FlexibleDateDenormalizer** - Multiple date format parsing

**Deserialization Entry Point:**
```php
// Lines 36-41
public function fromJson(string $jsonData, string $dataType): mixed {
    return $this->deserializeObject($this->serializer(), $jsonData, $dataType);
}

// Lines 141-147
protected function deserializeObject(Serializer $serializer, string $jsonData, string $dataClass): object {
    try {
        return $serializer->deserialize($jsonData, $dataClass, 'json');
    } catch (\Exception $e) {
        throw new DeserializationException($e->getMessage(), $dataClass, $jsonData);
    }
}
```

---

## 10. Validation System

**File:** `packages/instructor/src/Validation/ResponseValidator.php`

### Two Validation Paths

**1. Self-Validation:**
```php
// Lines 32-36, 61-64
$validation = match(true) {
    $response instanceof CanValidateSelf => $this->validateSelf($response),
    default => $this->validateObject($response)
};

protected function validateSelf(CanValidateSelf $response) : ValidationResult {
    $this->events->dispatch(new CustomResponseValidationAttempt([...]));
    return $response->validate();  // Object validates itself
}
```

**2. External Validation:**
```php
// Lines 66-79
protected function validateObject(object $response) : ValidationResult {
    $this->events->dispatch(new ResponseValidationAttempt(['object' => $response]));
    $results = [];
    foreach ($this->validators as $validator) {
        $validator = match(true) {
            is_string($validator) && is_subclass_of($validator, CanValidateObject::class) => new $validator(),
            $validator instanceof CanValidateObject => $validator,
            default => throw new Exception('Validator must implement CanValidateObject interface'),
        };
        $results[] = $validator->validate($response);
    }
    return ValidationResult::merge($results);  // Aggregate all validation results
}
```

### Validation Flow

```
ResponseValidator::validate(object, ResponseModel) : Result
  ↓
Match object type:
  ├─ CanValidateSelf → object.validate() : ValidationResult
  └─ Default → foreach validators:
      ├─ SymfonyValidator (default)
      ├─ Custom validators
      └─ Merge results
          ↓
ValidationResult::merge(results[])
  ├─ If any invalid → isInvalid() = true
  └─ Collect all errors
      ↓
Convert to Result<object>
  ├─ Valid → Result::success(object)
  └─ Invalid → Result::failure(errorMessage)
```

**Default Validator:** `SymfonyValidator` uses Symfony Validator component with constraint attributes.

---

## 11. Retry Policy

**File:** `packages/instructor/src/RetryPolicy/DefaultRetryPolicy.php`

### Stateless Policy Object

```php
// Lines 21-25
final readonly class DefaultRetryPolicy implements CanDetermineRetry {
    public function __construct(
        private CanHandleEvents $events,
    ) {}
```

**All state stored in `StructuredOutputExecution`** - policy is pure logic.

### Four Policy Methods

**1. shouldRetry() - Decide if retry allowed:**
```php
// Lines 28-34
public function shouldRetry(
    StructuredOutputExecution $execution,
    Result $validationResult,
): bool {
    return !$execution->maxRetriesReached();  // Simple: check attempt count
}
```

**2. recordFailure() - Record failed attempt:**
```php
// Lines 37-62
public function recordFailure(
    StructuredOutputExecution $execution,
    Result $validationResult,
    InferenceResponse $inference,
    PartialInferenceResponse $partial,
): StructuredOutputExecution {
    $error = $validationResult->error();
    $errors = is_array($error) ? $error : [$error];

    // Immutable update: record failure
    $updated = $execution->withFailedAttempt(
        inferenceResponse: $inference,
        partialInferenceResponse: $partial,
        errors: $errors,
    );

    // Event for observability
    if ($updated->attemptCount() <= $maxRetries) {
        $this->events->dispatch(new NewValidationRecoveryAttempt([
            'retries' => $updated->attemptCount(),
            'errors' => $updated->currentErrors(),
        ]));
    }

    return $updated;
}
```

**3. prepareRetry() - Adjust for retry:**
```php
// Lines 65-70
public function prepareRetry(
    StructuredOutputExecution $execution,
): StructuredOutputExecution {
    // Default: no modifications
    // Subclasses could adjust: prompt, temperature, model, etc.
    return $execution;
}
```

**4. finalizeOrThrow() - Terminal state:**
```php
// Lines 73-97
public function finalizeOrThrow(
    StructuredOutputExecution $execution,
    Result $validationResult,
): mixed {
    if ($validationResult->isSuccess()) {
        return $validationResult->unwrap();
    }

    // Failure - collect all errors
    $errors = $execution->errors();

    $this->events->dispatch(new StructuredOutputRecoveryLimitReached([
        'retries' => $execution->attemptCount(),
        'errors' => $errors,
    ]));

    throw new StructuredOutputRecoveryException(
        message: "Structured output recovery attempts limit reached after {$execution->attemptCount()} attempt(s)",
        errors: $errors,
    );
}
```

### Error Feedback to LLM

Errors are fed back via `RequestMaterializer::addRetryMessages()` (see section 3):
- Append assistant message (failed response)
- Append user message (retry prompt + errors)
- Repeat for each attempt

---

## TODO: Still to Explore (Lower Priority)

- [ ] All 3 streaming pipeline implementations in detail
- [ ] Output modes in detail (Json vs JsonSchema vs Tools)
- [ ] Tool calling integration mechanics
- [ ] Sequence tracking implementation
- [ ] Event system details
- [ ] Configuration system

---

## File Map (Read So Far)

### Core
- ✅ `StructuredOutput.php` - Main facade
- ✅ `PendingStructuredOutput.php` - Execution orchestrator
- ✅ `StructuredOutputStream.php` - Streaming facade
- ✅ `Core/AttemptIterator.php` - Retry orchestration
- ✅ `Core/RequestMaterializer.php` - Message building
- ✅ `Core/ResponseGenerator.php` - Validation pipeline
- ✅ `Core/InferenceProvider.php` - (seen in glob)
- ✅ `ResponseIteratorFactory.php` - (seen in glob)

### Data Structures
- ✅ `Data/StructuredOutputExecution.php` - State machine (partial read)
- ✅ `Data/ResponseModel.php` - Schema + instance wrapper
- ⏳ `Data/StructuredOutputRequest.php` - Request data
- ⏳ `Data/StructuredOutputConfig.php` - Configuration
- ⏳ `Data/StructuredOutputAttempt.php` - Attempt record
- ⏳ `Data/StructuredOutputAttemptState.php` - Streaming state
- ⏳ `Data/CachedContext.php` - Prompt caching

### Schema (Seen in Glob, Not Read)
- ⏸️ 50+ files in `packages/schema/src/`

### Deserialization (Seen in Glob, Not Read)
- ⏸️ `Deserialization/ResponseDeserializer.php`
- ⏸️ `Deserialization/Deserializers/SymfonyDeserializer.php`
- ⏸️ `Deserialization/Deserializers/BackedEnumNormalizer.php`
- ⏸️ `Deserialization/Deserializers/FlexibleDateDenormalizer.php`
- ⏸️ `Deserialization/Deserializers/CustomObjectNormalizer.php`

### Validation (Seen in Glob, Not Read)
- ⏸️ `Validation/ResponseValidator.php`
- ⏸️ `Validation/Validators/SymfonyValidator.php`
- ⏸️ `Validation/Validators/SelfValidator.php`
- ⏸️ `Validation/PartialValidation.php`
- ⏸️ `Validation/ValidationResult.php`

### Streaming Pipelines (Seen in Glob, Not Read)
- ⏸️ 60+ files across ModularPipeline, DecoratedPipeline, GeneratorBased

---

## Initial Observations

### Strengths

1. **Clean Separation of Concerns** - Each component has clear responsibility
2. **Immutability** - State transitions are explicit and traceable
3. **Composability** - Validators, deserializers, transformers are pluggable
4. **Multiple Streaming Modes** - 3 different implementations for different needs
5. **Comprehensive Event System** - Observable execution flow
6. **Result Monad** - No exceptions in hot paths
7. **Retry Intelligence** - Integrated validation + retry with error feedback
8. **Output Mode Flexibility** - Supports Json, JsonSchema, Tools modes

### Architecture Decisions

1. **Why 3 streaming pipelines?**
   - Evolution: Legacy → Partials (optimized) → Modular (clean)
   - Different trade-offs: simplicity vs performance vs maintainability

2. **Why immutable execution state?**
   - Enables time-travel debugging
   - Safe concurrent access
   - Clear state transitions

3. **Why Pipeline for response processing?**
   - Composable error handling
   - Clear data flow
   - Easy to test individual stages

4. **Why sectioned message store?**
   - Flexible chat structure composition
   - Supports prompt caching
   - Enables retry message injection

---

**Next Steps:** Continue reading schema system, deserialization, validation, and streaming pipelines.
