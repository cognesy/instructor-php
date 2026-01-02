# InstructorPHP V2: Evolution Plan Based on Comparative Analysis

**Date:** 2026-01-02
**Status:** Planning Phase
**Purpose:** Design modular architecture for InstructorPHP V2 incorporating insights from Prism, NeuronAI, and Symfony-AI

---

## Executive Summary

This study proposes architectural improvements for InstructorPHP V2 based on analysis of three alternative PHP LLM libraries. The goal is to evolve from a sophisticated but coupled architecture to a highly modular, format-agnostic, source-agnostic system while maintaining streaming capabilities and reliability.

### Key Objectives

1. **Increased Modularity** - Decouple pipeline stages with clear interfaces
2. **Format Agnosticism** - Support arbitrary schema formats (JSON Schema, YAML, OpenAPI, XSD)
3. **Source Agnosticism** - Process any content source (LLM, CLI, file, stream)
4. **Data Format Flexibility** - Extract multiple structured formats (JSON, YAML, XML)
5. **Preserve Streaming** - Maintain partial/streaming support across formats
6. **Keep Sophistication** - Retain validation, retry, state machine advantages

---

## Part 1: Learnings from Other Libraries

### From Prism: Simplicity & Explicit Control

#### What Prism Does Well

**1. Manual Schema Builders - Full Control**

```php
// Prism approach
new ObjectSchema(
    name: 'user',
    description: 'User data',
    properties: [
        new StringSchema('name', 'User name'),
        new NumberSchema('age', 'User age'),
    ],
    requiredFields: ['name'],
)
```

**Insight:** While InstructorPHP's reflection-based approach is powerful, offering OPTIONAL manual schema building gives users:
- Fine-grained control over exact schema output
- Ability to adapt schemas for specific providers
- Override reflection when needed

**2. Array-Only Responses - Developer Choice**

```php
// Prism returns arrays, developer controls deserialization
$response->structured; // ['name' => 'John', 'age' => 30]
```

**Insight:** InstructorPHP forces deserialization. Offering an option to return raw arrays would:
- Allow middleware processing before deserialization
- Enable custom deserialization strategies
- Support scenarios where objects aren't needed

**3. Provider Strategy Pattern**

```php
// Prism: Different strategies for different LLM capabilities
NativeOutputFormatStructuredStrategy
JsonModeStructuredStrategy
ToolStructuredStrategy
```

**Insight:** InstructorPHP has hardcoded modes. A strategy pattern would:
- Support provider-specific optimizations
- Allow runtime strategy selection
- Enable third-party provider extensions

#### What to Adopt

✅ **Optional manual schema builders** alongside reflection
✅ **Raw data mode** (skip deserialization when requested)
✅ **Provider strategy pattern** for output modes
❌ **Skip deserialization entirely** - keep it as default with opt-out

---

### From NeuronAI: Error Feedback & Extraction Robustness

#### What NeuronAI Does Well

**1. Explicit Error Feedback to LLM**

```php
// NeuronAI approach
do {
    if ($error) {
        $this->addToChatHistory(new UserMessage(
            "There was a problem: $error. Try again."
        ));
    }
    $response = $this->provider->structured(...);
    return $this->processResponse($response);
} catch (Exception $ex) {
    $error = $ex->getMessage();
    $maxRetries--;
}
```

**Insight:** InstructorPHP's retry policy is sophisticated but opaque. Making error feedback explicit would:
- Improve LLM self-correction
- Enable customizable error message templates
- Support provider-specific error formatting

**2. Four JSON Extraction Strategies**

```php
// NeuronAI fallback chain
1. Direct parsing (try as-is)
2. Markdown code block extraction (```json...```)
3. Bracket extraction (find first { to last })
4. Smart brace matching (handles escaped quotes)
```

**Insight:** InstructorPHP has basic extraction. A pluggable extraction chain would:
- Handle more edge cases
- Allow format-specific extractors (YAML, XML)
- Enable custom extraction strategies

**3. Custom Deserializer - Full Control**

```php
// NeuronAI custom implementation
$instance = $reflection->newInstanceWithoutConstructor();
foreach ($properties as $property) {
    $value = $this->castValue($value, $type, $property);
    $property->setValue($instance, $value);
}
```

**Insight:** While Symfony Serializer is powerful, a lighter alternative for simple cases would:
- Reduce overhead for basic types
- Allow performance optimization
- Support custom type handling

#### What to Adopt

✅ **Pluggable extraction strategies** with fallback chain
✅ **Explicit error feedback** mechanism in retry policy
✅ **Optional lightweight deserializer** for simple cases
❌ **Replace Symfony Serializer** - keep as default, add alternatives

---

### From Symfony-AI: TypeInfo & Event-Driven Architecture

#### What Symfony-AI Does Well

**1. Symfony TypeInfo Integration**

```php
// Symfony-AI uses TypeResolver
$typeResolver = TypeResolver::create();
$type = $typeResolver->resolve($reflectionProperty);
```

**Insight:** InstructorPHP uses custom TypeDetails. Symfony TypeInfo offers:
- Standardized type resolution
- Better union type support
- Native nullable handling

**2. Rich Constraint Attributes**

```php
// Symfony-AI comprehensive constraints
#[With(
    pattern: '^[A-Z]{2}[0-9]{3}$',
    minLength: 5,
    maxLength: 5,
    minimum: 0,
    maximum: 100,
)]
public string $code;
```

**Insight:** InstructorPHP has custom validation. A comprehensive attribute system would:
- Align with JSON Schema constraints
- Support all validation keywords
- Enable declarative validation

**3. Event-Driven Transformation**

```php
// Symfony-AI uses event subscribers
public function processResult(ResultEvent $event): void {
    $converter = new ResultConverter(...);
    $event->setDeferredResult(new DeferredResult($converter, ...));
}
```

**Insight:** InstructorPHP has hardcoded pipeline. Event-driven architecture would:
- Enable middleware injection
- Support third-party extensions
- Allow pre/post processing hooks

#### What to Adopt

✅ **Event hooks** for pipeline extension
✅ **Comprehensive constraint attributes** aligned with JSON Schema
⚠️ **Symfony TypeInfo** - consider as optional provider (not replacement)
❌ **Deep Symfony coupling** - remain framework-agnostic

---

## Part 2: Current InstructorPHP V1 Limitations

### L1: Tight Coupling to JSON Schema Format

**Current State:**
```php
// packages/schema/src/Visitors/SchemaToJsonSchema.php
// Hardcoded to always produce JSON Schema
$visitor = new SchemaToJsonSchema();
$schema->accept($visitor);
```

**Problem:** Cannot generate:
- YAML schemas
- OpenAPI specifications
- XML Schema Definitions (XSD)
- Custom provider formats

**Impact:** Users locked into JSON Schema even when provider supports better formats.

---

### L2: Tight Coupling to LLM as Data Source

**Current State:**
```php
// packages/instructor/src/Core/StreamIterator.php
// Assumes LLM streaming format
public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
    $chunk = $this->streamHandler->nextChunk($execution->streamState());
    // ...
}
```

**Problem:** Cannot process:
- CLI output streams
- File parsing
- Agent terminal output
- HTTP API responses

**Impact:** InstructorPHP can't be used as general structured data extraction tool.

---

### L3: Tight Coupling to JSON as Data Format

**Current State:**
```php
// packages/instructor/src/Utils/Json/JsonParser.php
// Only handles JSON
public function parse(string $json) : Result {
    // ...
}
```

**Problem:** Cannot extract:
- YAML data
- XML data
- TOML data
- Custom formats

**Impact:** Users need separate tools for non-JSON structured data.

---

### L4: Monolithic Pipeline Stages

**Current State:**
```php
// packages/instructor/src/Core/ResponseGenerator.php
private function makeResponsePipeline(ResponseModel $responseModel) : Pipeline {
    return Pipeline::builder(ErrorStrategy::FailFast)
        ->through(/* extraction */)
        ->through(/* deserialization */)
        ->through(/* validation */)
        ->through(/* transformation */)
        ->create();
}
```

**Problem:**
- Stages tightly coupled to specific implementations
- Cannot swap out individual stages
- Difficult to add middleware
- No provider-specific optimizations

**Impact:** Hard to extend, customize, or optimize for specific use cases.

---

### L5: Inflexible Retry Policy

**Current State:**
```php
// packages/instructor/src/Retry/RetryPolicy.php
// Policy hardcoded to validation results
public function prepareRetry(StructuredOutputExecution $execution): StructuredOutputExecution
```

**Problem:**
- Error messages not customizable
- No provider-specific error formatting
- Limited feedback strategies

**Impact:** LLMs can't always self-correct effectively.

---

## Part 3: InstructorPHP V2 Architecture Proposal

### Overview: Layered Modular Design

```
┌─────────────────────────────────────────────────────────────┐
│                   Public API Layer                          │
│  StructuredOutput::create() -> PendingStructuredOutput      │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────▼─────────────────────────────────┐
│                   Orchestration Layer                         │
│  - AttemptIterator (retry loop)                              │
│  - StateManager (execution state)                            │
│  - EventDispatcher (hooks & middleware)                      │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────▼─────────────────────────────────┐
│                   Pipeline Layer (NEW)                        │
│  - SchemaGenerator (format-agnostic)                         │
│  - ContentExtractor (source-agnostic)                        │
│  - DataParser (multi-format)                                │
│  - Deserializer (pluggable)                                 │
│  - Validator (constraint-based)                             │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────▼─────────────────────────────────┐
│                   Format Providers (NEW)                      │
│  - JsonSchemaProvider                                        │
│  - YamlSchemaProvider                                        │
│  - OpenApiProvider                                           │
│  - XmlSchemaProvider                                         │
└─────────────────────────────────────────────────────────────┘
                              │
┌─────────────────────────────▼─────────────────────────────────┐
│                   Content Sources (NEW)                       │
│  - LlmSource (HTTP streaming)                                │
│  - CliSource (process output)                                │
│  - FileSource (file parsing)                                 │
│  - StreamSource (arbitrary streams)                          │
└─────────────────────────────────────────────────────────────┘
```

---

### A1: Format Abstraction Layer

**New Interface Hierarchy:**

```php
namespace Cognesy\Instructor\Schema\Formats;

/**
 * Base interface for all schema format providers
 */
interface SchemaFormatProvider {
    /** Generate schema in this format from Schema object */
    public function generate(Schema $schema): string;

    /** Content type for this format */
    public function contentType(): string;

    /** File extension for this format */
    public function extension(): string;

    /** Whether this format supports streaming */
    public function supportsStreaming(): bool;
}

/**
 * JSON Schema format provider (default)
 */
class JsonSchemaProvider implements SchemaFormatProvider {
    public function generate(Schema $schema): string {
        $visitor = new SchemaToJsonSchema();
        return json_encode($schema->accept($visitor));
    }

    public function contentType(): string {
        return 'application/schema+json';
    }

    public function extension(): string {
        return 'json';
    }

    public function supportsStreaming(): bool {
        return true;
    }
}

/**
 * YAML schema format provider
 */
class YamlSchemaProvider implements SchemaFormatProvider {
    public function generate(Schema $schema): string {
        $visitor = new SchemaToYaml();
        return Yaml::dump($schema->accept($visitor));
    }

    public function contentType(): string {
        return 'application/yaml';
    }

    public function extension(): string {
        return 'yaml';
    }

    public function supportsStreaming(): bool {
        return false; // YAML doesn't support partial parsing well
    }
}

/**
 * OpenAPI 3.1 format provider
 */
class OpenApiProvider implements SchemaFormatProvider {
    public function __construct(
        private string $title,
        private string $version = '1.0.0',
    ) {}

    public function generate(Schema $schema): string {
        $visitor = new SchemaToOpenApi($this->title, $this->version);
        return json_encode($schema->accept($visitor));
    }

    public function contentType(): string {
        return 'application/vnd.oai.openapi+json';
    }

    public function extension(): string {
        return 'openapi.json';
    }

    public function supportsStreaming(): bool {
        return true;
    }
}

/**
 * XML Schema Definition format provider
 */
class XmlSchemaProvider implements SchemaFormatProvider {
    public function generate(Schema $schema): string {
        $visitor = new SchemaToXsd();
        return $schema->accept($visitor);
    }

    public function contentType(): string {
        return 'application/xml';
    }

    public function extension(): string {
        return 'xsd';
    }

    public function supportsStreaming(): bool {
        return false;
    }
}
```

**Usage:**

```php
// Default JSON Schema (V1 compatible)
$output = StructuredOutput::create()
    ->using(User::class)
    ->execute();

// Use YAML schema format
$output = StructuredOutput::create()
    ->using(User::class)
    ->withSchemaFormat(new YamlSchemaProvider())
    ->execute();

// Use OpenAPI format
$output = StructuredOutput::create()
    ->using(User::class)
    ->withSchemaFormat(new OpenApiProvider('User API', '2.0'))
    ->execute();

// Custom format
$output = StructuredOutput::create()
    ->using(User::class)
    ->withSchemaFormat(new CustomFormatProvider())
    ->execute();
```

---

### A2: Content Source Abstraction Layer

**New Interface Hierarchy:**

```php
namespace Cognesy\Instructor\Sources;

/**
 * Represents a source of content that may contain structured data
 */
interface ContentSource {
    /** Whether this source supports streaming */
    public function isStreamable(): bool;

    /** Get next chunk (for streaming sources) */
    public function nextChunk(): ?ContentChunk;

    /** Get complete content (for non-streaming sources) */
    public function complete(): string;

    /** Check if source is exhausted */
    public function isExhausted(): bool;
}

/**
 * Represents a chunk of content from a streaming source
 */
readonly class ContentChunk {
    public function __construct(
        public string $content,
        public bool $isFinal,
        public array $metadata = [],
    ) {}
}

/**
 * LLM streaming source (current behavior)
 */
class LlmSource implements ContentSource {
    public function __construct(
        private StreamHandler $streamHandler,
    ) {}

    public function isStreamable(): bool {
        return true;
    }

    public function nextChunk(): ?ContentChunk {
        $chunk = $this->streamHandler->nextChunk();
        if ($chunk === null) {
            return null;
        }

        return new ContentChunk(
            content: $chunk->content,
            isFinal: $chunk->finishReason !== null,
            metadata: ['role' => $chunk->role],
        );
    }

    public function complete(): string {
        while (!$this->isExhausted()) {
            $this->nextChunk();
        }
        return $this->streamHandler->getAccumulated();
    }

    public function isExhausted(): bool {
        return $this->streamHandler->isFinished();
    }
}

/**
 * CLI process output source
 */
class CliSource implements ContentSource {
    private Process $process;
    private string $accumulated = '';

    public function __construct(
        string|array $command,
        private ?string $workingDirectory = null,
    ) {
        $this->process = Process::fromShellCommandline(
            command: is_array($command) ? implode(' ', $command) : $command,
            cwd: $workingDirectory,
        );
        $this->process->start();
    }

    public function isStreamable(): bool {
        return true;
    }

    public function nextChunk(): ?ContentChunk {
        $output = $this->process->getIncrementalOutput();
        if ($output === '') {
            return null;
        }

        $this->accumulated .= $output;

        return new ContentChunk(
            content: $output,
            isFinal: !$this->process->isRunning(),
            metadata: ['pid' => $this->process->getPid()],
        );
    }

    public function complete(): string {
        $this->process->wait();
        return $this->accumulated . $this->process->getOutput();
    }

    public function isExhausted(): bool {
        return !$this->process->isRunning();
    }
}

/**
 * File source (non-streaming)
 */
class FileSource implements ContentSource {
    private ?string $content = null;

    public function __construct(
        private string $filePath,
    ) {}

    public function isStreamable(): bool {
        return false;
    }

    public function nextChunk(): ?ContentChunk {
        if ($this->content !== null) {
            return null;
        }

        $this->content = file_get_contents($this->filePath);

        return new ContentChunk(
            content: $this->content,
            isFinal: true,
            metadata: ['path' => $this->filePath],
        );
    }

    public function complete(): string {
        if ($this->content === null) {
            $this->content = file_get_contents($this->filePath);
        }
        return $this->content;
    }

    public function isExhausted(): bool {
        return $this->content !== null;
    }
}

/**
 * Generic stream source
 */
class StreamSource implements ContentSource {
    private string $accumulated = '';
    private bool $exhausted = false;

    public function __construct(
        /** @var resource */
        private $stream,
        private int $chunkSize = 8192,
    ) {}

    public function isStreamable(): bool {
        return true;
    }

    public function nextChunk(): ?ContentChunk {
        if ($this->exhausted) {
            return null;
        }

        $chunk = fread($this->stream, $this->chunkSize);

        if ($chunk === false || $chunk === '') {
            $this->exhausted = true;
            return null;
        }

        $this->accumulated .= $chunk;
        $isFinal = feof($this->stream);

        if ($isFinal) {
            $this->exhausted = true;
        }

        return new ContentChunk(
            content: $chunk,
            isFinal: $isFinal,
        );
    }

    public function complete(): string {
        while (!$this->isExhausted()) {
            $this->nextChunk();
        }
        return $this->accumulated;
    }

    public function isExhausted(): bool {
        return $this->exhausted;
    }
}
```

**Usage:**

```php
// LLM source (default, V1 compatible)
$output = StructuredOutput::create()
    ->using(User::class)
    ->execute();

// CLI process output
$output = StructuredOutput::create()
    ->using(User::class)
    ->fromSource(new CliSource('my-agent --extract-user'))
    ->execute();

// File parsing
$output = StructuredOutput::create()
    ->using(User::class)
    ->fromSource(new FileSource('/tmp/response.json'))
    ->execute();

// Generic stream
$stream = fopen('https://api.example.com/stream', 'r');
$output = StructuredOutput::create()
    ->using(User::class)
    ->fromSource(new StreamSource($stream))
    ->execute();

// Streaming from CLI with partials
$stream = StructuredOutput::create()
    ->using(User::class)
    ->fromSource(new CliSource('my-agent --extract-user'))
    ->stream();

foreach ($stream->partials() as $partial) {
    // Process partial User objects as CLI outputs data
}
```

---

### A3: Data Format Abstraction Layer

**New Interface Hierarchy:**

```php
namespace Cognesy\Instructor\Data\Formats;

/**
 * Extracts structured data from raw content
 */
interface DataExtractor {
    /** Extract data from content, return format-specific structure */
    public function extract(string $content): Result;

    /** Content type this extractor handles */
    public function handles(): string;

    /** Whether extraction supports streaming */
    public function supportsStreaming(): bool;

    /** Extract partial data (for streaming) */
    public function extractPartial(string $content): Result;
}

/**
 * Parses extracted data into PHP arrays/values
 */
interface DataParser {
    /** Parse data string into PHP structure */
    public function parse(string $data): Result;

    /** Format this parser handles */
    public function format(): string;
}

/**
 * JSON extractor with multiple strategies (inspired by NeuronAI)
 */
class JsonExtractor implements DataExtractor {
    /** @var array<ExtractionStrategy> */
    private array $strategies;

    public function __construct(array $strategies = null) {
        $this->strategies = $strategies ?? [
            new DirectJsonStrategy(),
            new MarkdownCodeBlockStrategy(),
            new BracketMatchingStrategy(),
            new SmartBraceMatchingStrategy(),
        ];
    }

    public function extract(string $content): Result {
        foreach ($this->strategies as $strategy) {
            $result = $strategy->extract($content);
            if ($result->isSuccess()) {
                return $result;
            }
        }

        return Result::failure('No JSON found in content');
    }

    public function handles(): string {
        return 'application/json';
    }

    public function supportsStreaming(): bool {
        return true;
    }

    public function extractPartial(string $content): Result {
        // Try to extract JSON even if incomplete
        return (new PartialJsonStrategy())->extract($content);
    }
}

/**
 * YAML extractor
 */
class YamlExtractor implements DataExtractor {
    public function extract(string $content): Result {
        // Try direct YAML parsing
        if (Yaml::isValid($content)) {
            return Result::success($content);
        }

        // Try extracting from markdown code block
        $pattern = '/^```(?:yaml|yml)?\s*\n?(.*?)\n?```$/s';
        if (preg_match($pattern, trim($content), $matches)) {
            return Result::success(trim($matches[1]));
        }

        return Result::failure('No YAML found in content');
    }

    public function handles(): string {
        return 'application/yaml';
    }

    public function supportsStreaming(): bool {
        return false; // YAML doesn't parse well incrementally
    }

    public function extractPartial(string $content): Result {
        return Result::failure('YAML does not support partial extraction');
    }
}

/**
 * XML extractor
 */
class XmlExtractor implements DataExtractor {
    public function extract(string $content): Result {
        // Try direct XML parsing
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if ($xml !== false) {
            return Result::success($content);
        }

        // Try extracting from markdown code block
        $pattern = '/^```(?:xml)?\s*\n?(.*?)\n?```$/s';
        if (preg_match($pattern, trim($content), $matches)) {
            $extracted = trim($matches[1]);
            $xml = simplexml_load_string($extracted);

            if ($xml !== false) {
                return Result::success($extracted);
            }
        }

        return Result::failure('No valid XML found in content');
    }

    public function handles(): string {
        return 'application/xml';
    }

    public function supportsStreaming(): bool {
        return false;
    }

    public function extractPartial(string $content): Result {
        return Result::failure('XML does not support partial extraction');
    }
}

/**
 * JSON parser
 */
class JsonParser implements DataParser {
    public function parse(string $data): Result {
        try {
            $parsed = json_decode(
                json: $data,
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );

            return Result::success($parsed);
        } catch (\JsonException $e) {
            return Result::failure("JSON parse error: {$e->getMessage()}");
        }
    }

    public function format(): string {
        return 'json';
    }
}

/**
 * YAML parser
 */
class YamlParser implements DataParser {
    public function parse(string $data): Result {
        try {
            $parsed = Yaml::parse($data);
            return Result::success($parsed);
        } catch (ParseException $e) {
            return Result::failure("YAML parse error: {$e->getMessage()}");
        }
    }

    public function format(): string {
        return 'yaml';
    }
}

/**
 * XML parser
 */
class XmlParser implements DataParser {
    public function parse(string $data): Result {
        libxml_use_internal_errors(true);

        $xml = simplexml_load_string($data);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $message = 'XML parse errors: ' . implode(', ', array_map(
                fn($e) => $e->message,
                $errors
            ));

            return Result::failure($message);
        }

        // Convert to array
        $json = json_encode($xml);
        $array = json_decode($json, true);

        return Result::success($array);
    }

    public function format(): string {
        return 'xml';
    }
}
```

**Extraction Strategy Interface:**

```php
namespace Cognesy\Instructor\Data\Extraction;

interface ExtractionStrategy {
    public function extract(string $content): Result;
}

class DirectJsonStrategy implements ExtractionStrategy {
    public function extract(string $content): Result {
        try {
            json_decode($content, flags: JSON_THROW_ON_ERROR);
            return Result::success($content);
        } catch (\JsonException) {
            return Result::failure('Not valid JSON');
        }
    }
}

class MarkdownCodeBlockStrategy implements ExtractionStrategy {
    public function extract(string $content): Result {
        $pattern = '/^```(?:json)?\s*\n?(.*?)\n?```$/s';

        if (preg_match($pattern, trim($content), $matches)) {
            $json = trim($matches[1]);

            try {
                json_decode($json, flags: JSON_THROW_ON_ERROR);
                return Result::success($json);
            } catch (\JsonException) {
                return Result::failure('Invalid JSON in code block');
            }
        }

        return Result::failure('No markdown code block found');
    }
}

class BracketMatchingStrategy implements ExtractionStrategy {
    public function extract(string $content): Result {
        $firstBrace = strpos($content, '{');
        $lastBrace = strrpos($content, '}');

        if ($firstBrace === false || $lastBrace === false) {
            return Result::failure('No braces found');
        }

        $json = substr($content, $firstBrace, $lastBrace - $firstBrace + 1);

        try {
            json_decode($json, flags: JSON_THROW_ON_ERROR);
            return Result::success($json);
        } catch (\JsonException) {
            return Result::failure('Invalid JSON between braces');
        }
    }
}

class SmartBraceMatchingStrategy implements ExtractionStrategy {
    public function extract(string $content): Result {
        $depth = 0;
        $start = null;
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($content); $i++) {
            $char = $content[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = !$inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '{') {
                if ($depth === 0) {
                    $start = $i;
                }
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $json = substr($content, $start, $i - $start + 1);

                    try {
                        json_decode($json, flags: JSON_THROW_ON_ERROR);
                        return Result::success($json);
                    } catch (\JsonException) {
                        // Continue searching
                        $start = null;
                    }
                }
            }
        }

        return Result::failure('No valid JSON with smart brace matching');
    }
}

class PartialJsonStrategy implements ExtractionStrategy {
    public function extract(string $content): Result {
        // Attempt to extract valid JSON even if incomplete
        // Useful for streaming scenarios

        // Find first { and try to close it properly
        $firstBrace = strpos($content, '{');
        if ($firstBrace === false) {
            return Result::failure('No opening brace found');
        }

        $json = substr($content, $firstBrace);

        // Try parsing as-is first
        try {
            json_decode($json, flags: JSON_THROW_ON_ERROR);
            return Result::success($json);
        } catch (\JsonException) {
            // Try adding closing braces
            $depth = 0;
            for ($i = 0; $i < strlen($json); $i++) {
                if ($json[$i] === '{') $depth++;
                if ($json[$i] === '}') $depth--;
            }

            if ($depth > 0) {
                $completed = $json . str_repeat('}', $depth);

                try {
                    json_decode($completed, flags: JSON_THROW_ON_ERROR);
                    return Result::success($completed);
                } catch (\JsonException) {
                    return Result::failure('Cannot complete partial JSON');
                }
            }

            return Result::failure('Invalid partial JSON');
        }
    }
}
```

**Usage:**

```php
// Default JSON extraction (V1 compatible)
$output = StructuredOutput::create()
    ->using(User::class)
    ->execute();

// Use YAML data format
$output = StructuredOutput::create()
    ->using(User::class)
    ->withDataFormat('yaml')
    ->execute();

// Use XML data format
$output = StructuredOutput::create()
    ->using(User::class)
    ->withDataFormat('xml')
    ->execute();

// Custom extraction strategies for JSON
$output = StructuredOutput::create()
    ->using(User::class)
    ->withExtractor(new JsonExtractor([
        new CustomExtractionStrategy(),
        new DirectJsonStrategy(),
    ]))
    ->execute();

// Combine format flexibility
$output = StructuredOutput::create()
    ->using(User::class)
    ->withSchemaFormat(new OpenApiProvider('User', '1.0'))
    ->fromSource(new CliSource('my-agent'))
    ->withDataFormat('yaml')
    ->execute();
// Sends OpenAPI schema, parses YAML response from CLI agent
```

---

### A4: Pluggable Pipeline Architecture

**New Modular Pipeline:**

```php
namespace Cognesy\Instructor\Pipeline;

/**
 * Pipeline stage interface
 */
interface PipelineStage {
    /** Execute this stage */
    public function execute(PipelineContext $context): Result;

    /** Stage name for debugging */
    public function name(): string;

    /** Whether this stage can be skipped */
    public function isOptional(): bool;
}

/**
 * Pipeline context carries state through stages
 */
class PipelineContext {
    private array $data = [];
    private array $metadata = [];

    public function set(string $key, mixed $value): self {
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool {
        return isset($this->data[$key]);
    }

    public function setMetadata(string $key, mixed $value): self {
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getMetadata(string $key, mixed $default = null): mixed {
        return $this->metadata[$key] ?? $default;
    }
}

/**
 * Modular pipeline builder
 */
class StructuredOutputPipeline {
    /** @var array<PipelineStage> */
    private array $stages = [];

    private EventDispatcher $events;

    public function __construct(EventDispatcher $events) {
        $this->events = $events;
    }

    public function addStage(PipelineStage $stage): self {
        $this->stages[] = $stage;
        return $this;
    }

    public function execute(PipelineContext $context): Result {
        $this->events->dispatch(new PipelineStarted($context));

        foreach ($this->stages as $stage) {
            $this->events->dispatch(new StageStarting($stage, $context));

            $result = $stage->execute($context);

            $this->events->dispatch(new StageCompleted($stage, $context, $result));

            if ($result->isFailure() && !$stage->isOptional()) {
                $this->events->dispatch(new PipelineFailed($stage, $result));
                return $result;
            }

            if ($result->isSuccess()) {
                // Update context with result
                $context->set($stage->name(), $result->unwrap());
            }
        }

        $this->events->dispatch(new PipelineCompleted($context));

        return Result::success($context);
    }
}

/**
 * Standard pipeline stages
 */

class SchemaGenerationStage implements PipelineStage {
    public function __construct(
        private SchemaFormatProvider $formatProvider,
        private SchemaFactory $schemaFactory,
    ) {}

    public function execute(PipelineContext $context): Result {
        $responseModel = $context->get('responseModel');

        $schema = $this->schemaFactory->schema($responseModel->class);
        $formattedSchema = $this->formatProvider->generate($schema);

        return Result::success($formattedSchema);
    }

    public function name(): string {
        return 'schema';
    }

    public function isOptional(): bool {
        return false;
    }
}

class ContentExtractionStage implements PipelineStage {
    public function __construct(
        private ContentSource $source,
    ) {}

    public function execute(PipelineContext $context): Result {
        if ($this->source->isStreamable()) {
            // For streaming, accumulate until complete or timeout
            $accumulated = '';

            while (!$this->source->isExhausted()) {
                $chunk = $this->source->nextChunk();
                if ($chunk === null) {
                    break;
                }

                $accumulated .= $chunk->content;

                if ($chunk->isFinal) {
                    break;
                }
            }

            return Result::success($accumulated);
        } else {
            return Result::success($this->source->complete());
        }
    }

    public function name(): string {
        return 'content';
    }

    public function isOptional(): bool {
        return false;
    }
}

class DataExtractionStage implements PipelineStage {
    public function __construct(
        private DataExtractor $extractor,
    ) {}

    public function execute(PipelineContext $context): Result {
        $content = $context->get('content');

        if ($content === null || $content === '') {
            return Result::failure('No content to extract from');
        }

        return $this->extractor->extract($content);
    }

    public function name(): string {
        return 'extracted_data';
    }

    public function isOptional(): bool {
        return false;
    }
}

class DataParsingStage implements PipelineStage {
    public function __construct(
        private DataParser $parser,
    ) {}

    public function execute(PipelineContext $context): Result {
        $extractedData = $context->get('extracted_data');

        return $this->parser->parse($extractedData);
    }

    public function name(): string {
        return 'parsed_data';
    }

    public function isOptional(): bool {
        return false;
    }
}

class DeserializationStage implements PipelineStage {
    public function __construct(
        private Deserializer $deserializer,
        private bool $skipDeserialization = false,
    ) {}

    public function execute(PipelineContext $context): Result {
        if ($this->skipDeserialization) {
            // Return raw parsed data
            return Result::success($context->get('parsed_data'));
        }

        $parsedData = $context->get('parsed_data');
        $responseModel = $context->get('responseModel');

        return $this->deserializer->deserialize(
            data: $parsedData,
            type: $responseModel->class,
        );
    }

    public function name(): string {
        return 'object';
    }

    public function isOptional(): bool {
        return false;
    }
}

class ValidationStage implements PipelineStage {
    public function __construct(
        private Validator $validator,
    ) {}

    public function execute(PipelineContext $context): Result {
        $object = $context->get('object');
        $responseModel = $context->get('responseModel');

        return $this->validator->validate($object, $responseModel);
    }

    public function name(): string {
        return 'validation';
    }

    public function isOptional(): bool {
        return false;
    }
}

class TransformationStage implements PipelineStage {
    public function __construct(
        private Transformer $transformer,
    ) {}

    public function execute(PipelineContext $context): Result {
        $object = $context->get('object');
        $responseModel = $context->get('responseModel');

        return $this->transformer->transform($object, $responseModel);
    }

    public function name(): string {
        return 'transformed';
    }

    public function isOptional(): bool {
        return true; // Transformation is optional
    }
}
```

**Pipeline Builder with Presets:**

```php
namespace Cognesy\Instructor\Pipeline;

class PipelineBuilder {
    private EventDispatcher $events;

    public function __construct(EventDispatcher $events) {
        $this->events = $events;
    }

    /** Build default pipeline (V1 compatible) */
    public function buildDefault(
        SchemaFactory $schemaFactory,
        LlmSource $source,
        Deserializer $deserializer,
        Validator $validator,
        Transformer $transformer,
    ): StructuredOutputPipeline {
        $pipeline = new StructuredOutputPipeline($this->events);

        return $pipeline
            ->addStage(new SchemaGenerationStage(
                new JsonSchemaProvider(),
                $schemaFactory,
            ))
            ->addStage(new ContentExtractionStage($source))
            ->addStage(new DataExtractionStage(new JsonExtractor()))
            ->addStage(new DataParsingStage(new JsonParser()))
            ->addStage(new DeserializationStage($deserializer))
            ->addStage(new ValidationStage($validator))
            ->addStage(new TransformationStage($transformer));
    }

    /** Build custom pipeline */
    public function buildCustom(): StructuredOutputPipeline {
        return new StructuredOutputPipeline($this->events);
    }

    /** Build YAML pipeline */
    public function buildYamlPipeline(
        SchemaFactory $schemaFactory,
        ContentSource $source,
        Deserializer $deserializer,
        Validator $validator,
    ): StructuredOutputPipeline {
        $pipeline = new StructuredOutputPipeline($this->events);

        return $pipeline
            ->addStage(new SchemaGenerationStage(
                new YamlSchemaProvider(),
                $schemaFactory,
            ))
            ->addStage(new ContentExtractionStage($source))
            ->addStage(new DataExtractionStage(new YamlExtractor()))
            ->addStage(new DataParsingStage(new YamlParser()))
            ->addStage(new DeserializationStage($deserializer))
            ->addStage(new ValidationStage($validator));
    }

    /** Build CLI extraction pipeline (no schema generation) */
    public function buildCliPipeline(
        CliSource $source,
        Deserializer $deserializer,
    ): StructuredOutputPipeline {
        $pipeline = new StructuredOutputPipeline($this->events);

        return $pipeline
            ->addStage(new ContentExtractionStage($source))
            ->addStage(new DataExtractionStage(new JsonExtractor()))
            ->addStage(new DataParsingStage(new JsonParser()))
            ->addStage(new DeserializationStage($deserializer));
    }
}
```

**Usage:**

```php
// Default pipeline (V1 compatible)
$output = StructuredOutput::create()
    ->using(User::class)
    ->execute();

// Custom pipeline - raw data mode (inspired by Prism)
$output = StructuredOutput::create()
    ->using(User::class)
    ->rawMode() // Skip deserialization
    ->execute();
// Returns array instead of User object

// Custom pipeline - add middleware
$output = StructuredOutput::create()
    ->using(User::class)
    ->beforeStage('validation', function($context) {
        // Custom pre-validation logic
    })
    ->afterStage('deserialization', function($context) {
        // Custom post-deserialization logic
    })
    ->execute();

// Fully custom pipeline
$pipeline = PipelineBuilder::create($events)
    ->buildCustom()
    ->addStage(new CustomSchemaStage(...))
    ->addStage(new CustomExtractionStage(...))
    ->addStage(new CustomDeserializationStage(...));

$output = StructuredOutput::create()
    ->using(User::class)
    ->withPipeline($pipeline)
    ->execute();
```

---

### A5: Event-Driven Extension System (Inspired by Symfony-AI)

**Event System:**

```php
namespace Cognesy\Instructor\Events;

interface Event {
    public function timestamp(): float;
}

interface EventSubscriber {
    /** @return array<string, string> Event name => method name */
    public static function subscribedEvents(): array;
}

class EventDispatcher {
    /** @var array<string, array<callable>> */
    private array $listeners = [];

    public function dispatch(Event $event): void {
        $eventClass = get_class($event);

        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $listener) {
            $listener($event);
        }
    }

    public function addListener(string $eventClass, callable $listener): void {
        $this->listeners[$eventClass][] = $listener;
    }

    public function addSubscriber(EventSubscriber $subscriber): void {
        foreach ($subscriber::subscribedEvents() as $event => $method) {
            $this->addListener($event, [$subscriber, $method]);
        }
    }
}

/** Pipeline events */

readonly class PipelineStarted implements Event {
    public function __construct(
        public PipelineContext $context,
        public float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public function timestamp(): float {
        return $this->timestamp;
    }
}

readonly class StageStarting implements Event {
    public function __construct(
        public PipelineStage $stage,
        public PipelineContext $context,
        public float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public function timestamp(): float {
        return $this->timestamp;
    }
}

readonly class StageCompleted implements Event {
    public function __construct(
        public PipelineStage $stage,
        public PipelineContext $context,
        public Result $result,
        public float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public function timestamp(): float {
        return $this->timestamp;
    }
}

readonly class PipelineCompleted implements Event {
    public function __construct(
        public PipelineContext $context,
        public float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public function timestamp(): float {
        return $this->timestamp;
    }
}

readonly class PipelineFailed implements Event {
    public function __construct(
        public PipelineStage $stage,
        public Result $result,
        public float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public function timestamp(): float {
        return $this->timestamp;
    }
}

/** Retry events */

readonly class RetryAttempting implements Event {
    public function __construct(
        public int $attemptNumber,
        public string $errorMessage,
        public float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public function timestamp(): float {
        return $this->timestamp;
    }
}

readonly class RetryExhausted implements Event {
    public function __construct(
        public int $maxAttempts,
        public array $errors,
        public float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public function timestamp(): float {
        return $this->timestamp;
    }
}

/** Validation events */

readonly class ValidationFailed implements Event {
    public function __construct(
        public object $object,
        public ValidationResult $result,
        public float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public function timestamp(): float {
        return $this->timestamp;
    }
}

readonly class ValidationPassed implements Event {
    public function __construct(
        public object $object,
        public float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public function timestamp(): float {
        return $this->timestamp;
    }
}
```

**Example Subscribers:**

```php
namespace Cognesy\Instructor\Events\Subscribers;

class LoggingSubscriber implements EventSubscriber {
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public static function subscribedEvents(): array {
        return [
            PipelineStarted::class => 'onPipelineStarted',
            StageCompleted::class => 'onStageCompleted',
            PipelineFailed::class => 'onPipelineFailed',
            RetryAttempting::class => 'onRetryAttempting',
        ];
    }

    public function onPipelineStarted(PipelineStarted $event): void {
        $this->logger->debug('Pipeline started');
    }

    public function onStageCompleted(StageCompleted $event): void {
        $this->logger->debug('Stage completed', [
            'stage' => $event->stage->name(),
            'duration' => microtime(true) - $event->timestamp,
        ]);
    }

    public function onPipelineFailed(PipelineFailed $event): void {
        $this->logger->error('Pipeline failed', [
            'stage' => $event->stage->name(),
            'error' => $event->result->error(),
        ]);
    }

    public function onRetryAttempting(RetryAttempting $event): void {
        $this->logger->warning('Retrying', [
            'attempt' => $event->attemptNumber,
            'error' => $event->errorMessage,
        ]);
    }
}

class MetricsSubscriber implements EventSubscriber {
    public function __construct(
        private MetricsCollector $metrics,
    ) {}

    public static function subscribedEvents(): array {
        return [
            PipelineCompleted::class => 'onPipelineCompleted',
            ValidationFailed::class => 'onValidationFailed',
            RetryExhausted::class => 'onRetryExhausted',
        ];
    }

    public function onPipelineCompleted(PipelineCompleted $event): void {
        $this->metrics->increment('pipeline.completed');
    }

    public function onValidationFailed(ValidationFailed $event): void {
        $this->metrics->increment('validation.failed');
    }

    public function onRetryExhausted(RetryExhausted $event): void {
        $this->metrics->increment('retry.exhausted');
    }
}

class CachingSubscriber implements EventSubscriber {
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public static function subscribedEvents(): array {
        return [
            StageCompleted::class => 'onStageCompleted',
        ];
    }

    public function onStageCompleted(StageCompleted $event): void {
        // Cache schema generation results
        if ($event->stage->name() === 'schema') {
            $cacheKey = $this->makeCacheKey($event->context);
            $this->cache->set($cacheKey, $event->result->unwrap(), 3600);
        }
    }

    private function makeCacheKey(PipelineContext $context): string {
        $responseModel = $context->get('responseModel');
        return 'schema:' . $responseModel->class;
    }
}
```

**Usage:**

```php
// Add event subscribers
$events = new EventDispatcher();
$events->addSubscriber(new LoggingSubscriber($logger));
$events->addSubscriber(new MetricsSubscriber($metrics));
$events->addSubscriber(new CachingSubscriber($cache));

$output = StructuredOutput::create()
    ->using(User::class)
    ->withEvents($events)
    ->execute();

// Listen to specific events
$events->addListener(ValidationFailed::class, function($event) {
    // Custom logic on validation failure
    mail('admin@example.com', 'Validation failed', $event->result->error());
});

// Create custom subscriber
class CustomSubscriber implements EventSubscriber {
    public static function subscribedEvents(): array {
        return [
            PipelineStarted::class => 'onStart',
            PipelineCompleted::class => 'onComplete',
        ];
    }

    public function onStart(PipelineStarted $event): void {
        // Custom start logic
    }

    public function onComplete(PipelineCompleted $event): void {
        // Custom completion logic
    }
}

$events->addSubscriber(new CustomSubscriber());
```

---

### A6: Enhanced Retry Policy with Error Feedback (Inspired by NeuronAI)

**Improved Retry Policy Interface:**

```php
namespace Cognesy\Instructor\Retry;

interface RetryPolicy {
    /** Determine if retry should occur */
    public function shouldRetry(
        StructuredOutputExecution $execution,
        ValidationResult $validationResult,
    ): bool;

    /** Record failure and update execution state */
    public function recordFailure(
        StructuredOutputExecution $execution,
        ValidationResult $validationResult,
        string $responseContent,
    ): StructuredOutputExecution;

    /** Prepare retry attempt with error feedback */
    public function prepareRetry(
        StructuredOutputExecution $execution,
    ): StructuredOutputExecution;

    /** Format error message for LLM feedback */
    public function formatErrorFeedback(
        ValidationResult $validationResult,
    ): string;

    /** Finalize or throw exception */
    public function finalizeOrThrow(
        StructuredOutputExecution $execution,
        ValidationResult $validationResult,
    ): mixed;
}

/**
 * Error feedback retry policy (inspired by NeuronAI)
 */
class ErrorFeedbackRetryPolicy implements RetryPolicy {
    public function __construct(
        private int $maxRetries = 3,
        private ErrorMessageFormatter $formatter = null,
    ) {
        $this->formatter ??= new DefaultErrorMessageFormatter();
    }

    public function shouldRetry(
        StructuredOutputExecution $execution,
        ValidationResult $validationResult,
    ): bool {
        if ($validationResult->isValid()) {
            return false;
        }

        return $execution->attemptNumber() < $this->maxRetries;
    }

    public function recordFailure(
        StructuredOutputExecution $execution,
        ValidationResult $validationResult,
        string $responseContent,
    ): StructuredOutputExecution {
        return $execution->recordFailedAttempt(
            error: $validationResult->error(),
            responseContent: $responseContent,
        );
    }

    public function prepareRetry(
        StructuredOutputExecution $execution,
    ): StructuredOutputExecution {
        $lastError = $execution->lastError();

        // Add error feedback as user message
        $errorFeedback = $this->formatter->format($lastError);

        return $execution->addErrorFeedbackMessage($errorFeedback);
    }

    public function formatErrorFeedback(
        ValidationResult $validationResult,
    ): string {
        return $this->formatter->format($validationResult->error());
    }

    public function finalizeOrThrow(
        StructuredOutputExecution $execution,
        ValidationResult $validationResult,
    ): mixed {
        if ($validationResult->isValid()) {
            return $execution->result();
        }

        throw new StructuredOutputException(
            message: 'Max retries exceeded',
            errors: $execution->allErrors(),
        );
    }
}

/**
 * Error message formatters
 */

interface ErrorMessageFormatter {
    public function format(string $error): string;
}

class DefaultErrorMessageFormatter implements ErrorMessageFormatter {
    public function format(string $error): string {
        return <<<TEXT
There was a problem with your previous response:

{$error}

Please generate the correct structured output.
TEXT;
    }
}

class DetailedErrorMessageFormatter implements ErrorMessageFormatter {
    public function format(string $error): string {
        return <<<TEXT
Your previous response had validation errors:

{$error}

Please review the schema and correct the following:
1. Ensure all required fields are present
2. Verify data types match the schema
3. Check that values meet any constraints (min/max, patterns, etc.)

Generate the corrected structured output now.
TEXT;
    }
}

class ProviderSpecificErrorFormatter implements ErrorMessageFormatter {
    public function __construct(
        private string $provider,
    ) {}

    public function format(string $error): string {
        return match($this->provider) {
            'openai' => $this->formatForOpenAI($error),
            'anthropic' => $this->formatForAnthropic($error),
            'google' => $this->formatForGoogle($error),
            default => (new DefaultErrorMessageFormatter())->format($error),
        };
    }

    private function formatForOpenAI(string $error): string {
        return <<<TEXT
ERROR: Previous response validation failed.

{$error}

ACTION REQUIRED: Generate valid JSON matching the provided schema.
TEXT;
    }

    private function formatForAnthropic(string $error): string {
        return <<<TEXT
<error>
Your previous response had validation issues:

{$error}
</error>

<task>
Please review the schema and generate a corrected response.
</task>
TEXT;
    }

    private function formatForGoogle(string $error): string {
        return <<<TEXT
**Validation Error**

{$error}

**Action**: Provide corrected output matching the schema.
TEXT;
    }
}
```

**Usage:**

```php
// Default retry with error feedback
$output = StructuredOutput::create()
    ->using(User::class)
    ->withRetryPolicy(new ErrorFeedbackRetryPolicy(maxRetries: 3))
    ->execute();

// Detailed error messages
$output = StructuredOutput::create()
    ->using(User::class)
    ->withRetryPolicy(new ErrorFeedbackRetryPolicy(
        maxRetries: 5,
        formatter: new DetailedErrorMessageFormatter(),
    ))
    ->execute();

// Provider-specific error formatting
$output = StructuredOutput::create()
    ->using(User::class)
    ->via('anthropic')
    ->withRetryPolicy(new ErrorFeedbackRetryPolicy(
        maxRetries: 3,
        formatter: new ProviderSpecificErrorFormatter('anthropic'),
    ))
    ->execute();

// Custom error formatter
class CustomErrorFormatter implements ErrorMessageFormatter {
    public function format(string $error): string {
        return "CRITICAL ERROR:\n{$error}\n\nRetry with corrections.";
    }
}

$output = StructuredOutput::create()
    ->using(User::class)
    ->withRetryPolicy(new ErrorFeedbackRetryPolicy(
        formatter: new CustomErrorFormatter(),
    ))
    ->execute();
```

---

## Part 4: Streaming Support for New Architecture

### Challenge: Multi-Format Streaming

Not all formats support streaming equally:

| Format | Streaming | Reason |
|--------|-----------|--------|
| JSON | ✅ Yes | Partial parsing possible |
| YAML | ❌ No | Indentation-sensitive |
| XML | ⚠️ Limited | Needs complete tags |
| TOML | ❌ No | Section-based |

**Solution: Format-Aware Streaming Strategy**

```php
namespace Cognesy\Instructor\Streaming;

interface StreamingStrategy {
    /** Whether this strategy supports streaming */
    public function supportsStreaming(): bool;

    /** Extract partial data from accumulated content */
    public function extractPartial(string $accumulated): Result;

    /** Detect if content is complete enough to parse */
    public function isComplete(string $accumulated): bool;
}

class JsonStreamingStrategy implements StreamingStrategy {
    public function supportsStreaming(): bool {
        return true;
    }

    public function extractPartial(string $accumulated): Result {
        // Use PartialJsonStrategy
        return (new PartialJsonStrategy())->extract($accumulated);
    }

    public function isComplete(string $accumulated): bool {
        // Check for balanced braces
        $depth = 0;
        foreach (str_split($accumulated) as $char) {
            if ($char === '{') $depth++;
            if ($char === '}') $depth--;
        }
        return $depth === 0;
    }
}

class YamlStreamingStrategy implements StreamingStrategy {
    public function supportsStreaming(): bool {
        return false; // YAML doesn't stream well
    }

    public function extractPartial(string $accumulated): Result {
        return Result::failure('YAML does not support partial parsing');
    }

    public function isComplete(string $accumulated): bool {
        // Must wait for complete document
        return true; // Assume complete when asked
    }
}

class XmlStreamingStrategy implements StreamingStrategy {
    public function supportsStreaming(): bool {
        return true; // Limited support
    }

    public function extractPartial(string $accumulated): Result {
        // Try to extract complete root element
        if (preg_match('/<(\w+)>(.*)<\/\1>/s', $accumulated, $matches)) {
            return Result::success($matches[0]);
        }

        return Result::failure('Incomplete XML');
    }

    public function isComplete(string $accumulated): bool {
        // Check for matching root tags
        if (preg_match('/<(\w+)>/', $accumulated, $open)) {
            $closeTag = "</{$open[1]}>";
            return str_contains($accumulated, $closeTag);
        }
        return false;
    }
}
```

**Streaming Pipeline with Format Awareness:**

```php
namespace Cognesy\Instructor\Streaming;

class StreamingPipeline {
    public function __construct(
        private StructuredOutputPipeline $pipeline,
        private StreamingStrategy $strategy,
        private EventDispatcher $events,
    ) {}

    /** Stream partials as they become available */
    public function partials(ContentSource $source): \Generator {
        if (!$source->isStreamable()) {
            throw new \InvalidArgumentException('Source does not support streaming');
        }

        if (!$this->strategy->supportsStreaming()) {
            throw new \InvalidArgumentException('Format does not support streaming');
        }

        $accumulated = '';

        while (!$source->isExhausted()) {
            $chunk = $source->nextChunk();

            if ($chunk === null) {
                break;
            }

            $accumulated .= $chunk->content;

            // Try to extract partial
            $partialResult = $this->strategy->extractPartial($accumulated);

            if ($partialResult->isSuccess()) {
                // Create partial context
                $context = new PipelineContext();
                $context->set('extracted_data', $partialResult->unwrap());
                $context->set('responseModel', $this->responseModel);

                // Run through parsing/deserialization stages
                $result = $this->runPartialPipeline($context);

                if ($result->isSuccess()) {
                    $this->events->dispatch(new PartialEmitted($result->unwrap()));
                    yield $result->unwrap();
                }
            }

            if ($chunk->isFinal) {
                break;
            }
        }

        // Final value
        if ($this->strategy->isComplete($accumulated)) {
            $context = new PipelineContext();
            $context->set('content', $accumulated);
            $context->set('responseModel', $this->responseModel);

            $result = $this->pipeline->execute($context);

            if ($result->isSuccess()) {
                $this->events->dispatch(new FinalValueEmitted($result->unwrap()));
                return $result->unwrap();
            }
        }
    }

    private function runPartialPipeline(PipelineContext $context): Result {
        // Skip schema generation and content extraction for partials
        // Only run: parsing → deserialization → (skip validation)

        $partialPipeline = new StructuredOutputPipeline($this->events);
        $partialPipeline
            ->addStage(new DataParsingStage(new JsonParser()))
            ->addStage(new DeserializationStage($this->deserializer))
            // Skip validation for partials
        ;

        return $partialPipeline->execute($context);
    }
}
```

**Usage:**

```php
// Stream JSON from LLM (V1 compatible)
$stream = StructuredOutput::create()
    ->using(User::class)
    ->stream();

foreach ($stream->partials() as $partial) {
    // $partial is a User object with progressively more fields
    echo "Partial: {$partial->name}\n";
}

$final = $stream->finalValue();

// Stream JSON from CLI
$stream = StructuredOutput::create()
    ->using(User::class)
    ->fromSource(new CliSource('my-agent'))
    ->stream();

foreach ($stream->partials() as $partial) {
    // Real-time updates from CLI agent
}

// YAML doesn't support streaming
try {
    $stream = StructuredOutput::create()
        ->using(User::class)
        ->withDataFormat('yaml')
        ->stream();
} catch (\InvalidArgumentException $e) {
    echo "YAML does not support streaming\n";

    // Fall back to non-streaming
    $output = StructuredOutput::create()
        ->using(User::class)
        ->withDataFormat('yaml')
        ->execute();
}

// XML limited streaming
$stream = StructuredOutput::create()
    ->using(User::class)
    ->withDataFormat('xml')
    ->stream();

// Only emits when complete elements are available
foreach ($stream->partials() as $partial) {
    echo "Complete element: " . print_r($partial, true);
}
```

---

## Part 5: Migration Strategy from V1 to V2

### M1: Backward Compatibility Layer

**Principle:** V2 must work as drop-in replacement for V1 with ZERO breaking changes.

**Strategy:**

```php
namespace Cognesy\Instructor;

// V1 public API (unchanged)
class StructuredOutput {
    /**
     * V1 compatible factory method
     * Internally uses V2 architecture but maintains V1 behavior
     */
    public static function create(): PendingStructuredOutput {
        // Build V2 pipeline with V1 defaults
        $events = new EventDispatcher();

        $pipeline = PipelineBuilder::create($events)
            ->buildDefault(
                schemaFactory: new SchemaFactory(),
                source: new LlmSource(/* ... */),
                deserializer: new SymfonyDeserializer(),
                validator: new CompositeValidator(),
                transformer: new ResponseTransformer(),
            );

        return new PendingStructuredOutput(
            pipeline: $pipeline,
            events: $events,
            // V1 compatibility mode
            compatibilityMode: CompatibilityMode::V1,
        );
    }

    /**
     * V2 factory method with full capabilities
     */
    public static function v2(): PendingStructuredOutputV2 {
        return new PendingStructuredOutputV2();
    }
}

enum CompatibilityMode {
    case V1; // Strict V1 behavior
    case V2; // Full V2 capabilities
}

/**
 * V1 compatible pending request (unchanged public API)
 */
class PendingStructuredOutput {
    // All V1 methods work exactly as before
    public function using(string|object $class): self { /* ... */ }
    public function withPrompt(string $prompt): self { /* ... */ }
    public function withMessages(array $messages): self { /* ... */ }
    public function stream(): StructuredOutputStream { /* ... */ }
    public function execute(): mixed { /* ... */ }

    // Internally delegates to V2 pipeline
}

/**
 * V2 extended pending request with new capabilities
 */
class PendingStructuredOutputV2 extends PendingStructuredOutput {
    // V2-specific methods
    public function withSchemaFormat(SchemaFormatProvider $format): self { /* ... */ }
    public function fromSource(ContentSource $source): self { /* ... */ }
    public function withDataFormat(string $format): self { /* ... */ }
    public function withExtractor(DataExtractor $extractor): self { /* ... */ }
    public function rawMode(): self { /* ... */ }
    public function withPipeline(StructuredOutputPipeline $pipeline): self { /* ... */ }
    public function beforeStage(string $stage, callable $hook): self { /* ... */ }
    public function afterStage(string $stage, callable $hook): self { /* ... */ }
}
```

**Usage:**

```php
// V1 code works unchanged
$output = StructuredOutput::create()
    ->using(User::class)
    ->withPrompt('Extract user info')
    ->execute();

// Opt-in to V2 features
$output = StructuredOutput::v2()
    ->using(User::class)
    ->withSchemaFormat(new YamlSchemaProvider())
    ->fromSource(new CliSource('agent'))
    ->execute();

// Mix V1 and V2
$output = StructuredOutput::create()
    ->using(User::class)
    ->withPrompt('Extract user')
    ->rawMode() // V2 feature
    ->execute();
// Returns array (V2) but uses V1 LLM source
```

---

### M2: Gradual Migration Path

**Phase 1: V1 with V2 internals (release 2.0)**
- Internal architecture completely refactored to V2
- Public API remains V1 compatible
- No breaking changes
- Users upgrade seamlessly

**Phase 2: V2 features opt-in (release 2.1)**
- `StructuredOutput::v2()` available
- New features documented
- Migration guide published
- V1 API remains default

**Phase 3: V2 default (release 2.5)**
- `StructuredOutput::create()` uses V2 API
- V1 API available via `StructuredOutput::v1()`
- Deprecation notices for V1-only patterns

**Phase 4: V1 removed (release 3.0)**
- V1 API removed
- V2 is only API
- Major version bump indicates breaking change

---

### M3: Feature Flags for Testing

**Allow per-feature opt-in during migration:**

```php
namespace Cognesy\Instructor\Config;

class FeatureFlags {
    private static array $flags = [];

    public static function enable(string $feature): void {
        self::$flags[$feature] = true;
    }

    public static function disable(string $feature): void {
        self::$flags[$feature] = false;
    }

    public static function isEnabled(string $feature): bool {
        return self::$flags[$feature] ?? false;
    }
}

// Features
const FEATURE_MULTI_FORMAT_SCHEMA = 'multi_format_schema';
const FEATURE_CUSTOM_SOURCES = 'custom_sources';
const FEATURE_RAW_MODE = 'raw_mode';
const FEATURE_EVENT_SYSTEM = 'event_system';
const FEATURE_PLUGGABLE_EXTRACTORS = 'pluggable_extractors';

// Usage
FeatureFlags::enable(FEATURE_RAW_MODE);

if (FeatureFlags::isEnabled(FEATURE_RAW_MODE)) {
    // Use raw mode
} else {
    // Fall back to V1 behavior
}
```

---

## Part 6: Implementation Roadmap

### Phase 1: Foundation (Weeks 1-2)

**Goals:**
- Create interface hierarchy
- Implement event system
- Build pipeline infrastructure

**Deliverables:**
- `SchemaFormatProvider` interface + JsonSchemaProvider
- `ContentSource` interface + LlmSource
- `DataExtractor` interface + JsonExtractor
- `PipelineStage` interface + basic stages
- `EventDispatcher` + core events

**No breaking changes:** All wrapped in V2 internal implementation

---

### Phase 2: Format Support (Weeks 3-4)

**Goals:**
- Add YAML support
- Add XML support
- Implement format-agnostic pipeline

**Deliverables:**
- `YamlSchemaProvider` + `YamlExtractor` + `YamlParser`
- `XmlSchemaProvider` + `XmlExtractor` + `XmlParser`
- `OpenApiProvider`
- Format-aware streaming strategies

**Testing:** Extensive format conversion tests

---

### Phase 3: Source Abstraction (Weeks 5-6)

**Goals:**
- Implement alternative content sources
- Make pipeline source-agnostic

**Deliverables:**
- `CliSource` implementation
- `FileSource` implementation
- `StreamSource` implementation
- Documentation for custom sources

**Testing:** CLI integration tests, file parsing tests

---

### Phase 4: Enhanced Retry & Validation (Weeks 7-8)

**Goals:**
- Implement error feedback retry
- Add comprehensive validation attributes

**Deliverables:**
- `ErrorFeedbackRetryPolicy`
- `ErrorMessageFormatter` + provider-specific formatters
- Comprehensive `#[With]` attributes (aligned with JSON Schema)
- Validation event system

**Testing:** Retry loop tests, validation tests

---

### Phase 5: Streaming Refactor (Weeks 9-10)

**Goals:**
- Make streaming format-agnostic
- Support streaming from arbitrary sources

**Deliverables:**
- `StreamingStrategy` interface
- Format-specific strategies (JSON, XML)
- Streaming from CLI sources
- Partial validation support

**Testing:** Streaming integration tests

---

### Phase 6: V2 Public API (Weeks 11-12)

**Goals:**
- Design V2 public API
- Create migration guide
- Write documentation

**Deliverables:**
- `PendingStructuredOutputV2` class
- `StructuredOutput::v2()` factory
- Comprehensive migration guide
- Updated documentation

**Testing:** V1 compatibility tests, V2 feature tests

---

### Phase 7: Performance & Optimization (Weeks 13-14)

**Goals:**
- Optimize hot paths
- Add caching
- Performance benchmarks

**Deliverables:**
- Schema caching
- Response caching
- Performance test suite
- Benchmarks vs V1

**Metrics:**
- Latency reduction
- Memory usage
- Throughput

---

### Phase 8: Release & Migration Support (Week 15+)

**Goals:**
- Release 2.0
- Support community migration
- Gather feedback

**Deliverables:**
- Release 2.0.0 (V1 API, V2 internals)
- Migration documentation
- Example projects
- Video tutorials

---

## Part 7: Success Metrics

### Technical Metrics

**Modularity:**
- ✅ Pipeline stages independently testable
- ✅ Zero coupling between format providers
- ✅ Custom stages can be added without core changes

**Flexibility:**
- ✅ Support 4+ schema formats (JSON Schema, YAML, XML, OpenAPI)
- ✅ Support 4+ content sources (LLM, CLI, File, Stream)
- ✅ Support 3+ data formats (JSON, YAML, XML)

**Performance:**
- ✅ Schema generation ≤ V1 latency
- ✅ Deserialization ≤ V1 latency
- ✅ Streaming throughput ≥ V1

**Compatibility:**
- ✅ 100% V1 tests pass on V2
- ✅ Zero breaking changes in 2.0 release
- ✅ Migration path exists for all V1 patterns

### Developer Experience Metrics

**Ease of Extension:**
- ✅ Custom format provider in < 50 lines
- ✅ Custom content source in < 100 lines
- ✅ Custom pipeline stage in < 50 lines

**Documentation:**
- ✅ Migration guide covers all use cases
- ✅ V2 feature cookbook with 10+ examples
- ✅ Architecture documentation with diagrams

**Community Adoption:**
- ✅ 50% of users migrate to V2 within 6 months
- ✅ 5+ community-contributed format providers
- ✅ Positive feedback on modularity

---

## Part 8: Risk Assessment & Mitigation

### R1: Increased Complexity

**Risk:** V2 architecture more complex than V1
**Likelihood:** High
**Impact:** Medium

**Mitigation:**
- Comprehensive documentation
- Example implementations for every extension point
- Sane defaults that hide complexity
- Gradual migration path

---

### R2: Performance Regression

**Risk:** Additional abstraction layers slow execution
**Likelihood:** Medium
**Impact:** High

**Mitigation:**
- Extensive performance benchmarking
- Caching at strategic points
- Lazy initialization
- Pipeline stage optimization

---

### R3: Breaking Changes Creep

**Risk:** V2 features leak into V1 API
**Likelihood:** Medium
**Impact:** Critical

**Mitigation:**
- Strict compatibility test suite
- Feature flags for new behaviors
- Separate V1/V2 entry points
- Community beta testing

---

### R4: Migration Friction

**Risk:** Users resist migration due to complexity
**Likelihood:** Medium
**Impact:** Medium

**Mitigation:**
- V1 API works forever with V2 internals
- V2 opt-in only
- Clear value proposition documentation
- Migration tools/scripts

---

### R5: Format Support Gaps

**Risk:** Non-JSON formats have rough edges
**Likelihood:** High
**Impact:** Medium

**Mitigation:**
- Prioritize JSON as tier-1 format
- Clear documentation on format limitations
- Community contributions for other formats
- Escape hatch to custom formats

---

## Conclusion

InstructorPHP V2 represents an evolution toward ultimate modularity and flexibility while preserving the sophisticated features that made V1 powerful. By learning from Prism's simplicity, NeuronAI's error feedback, and Symfony-AI's event-driven architecture, we can create a system that:

1. **Decouples** - Every pipeline stage is swappable
2. **Extends** - Third parties can add formats, sources, strategies
3. **Adapts** - Works with LLMs, CLIs, files, any content source
4. **Maintains** - Backward compatible, zero breaking changes in 2.0
5. **Streams** - Partial updates across supported formats

The key insight is that **structured output extraction is format-agnostic, source-agnostic, and destination-agnostic**. V2 embraces this by making InstructorPHP a general-purpose structured data extraction library, not just an LLM tool.

**Next Steps:**
1. Validate this proposal with stakeholders
2. Create prototype of core interfaces
3. Performance test abstractions
4. Begin Phase 1 implementation
