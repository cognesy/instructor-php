<?php declare(strict_types=1);

namespace Cognesy\Instructor\Config;

use Cognesy\Config\Dsn;
use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Throwable;

final readonly class StructuredOutputConfig
{
    public const CONFIG_GROUP = 'structured';

    public static function group(): string {
        return self::CONFIG_GROUP;
    }

    private OutputMode $outputMode;
    private bool $useObjectReferences;
    private int $maxRetries;
    private string $retryPrompt;
    private array $modePrompts;
    private string $schemaName;
    private string $schemaDescription;
    private string $toolName;
    private string $toolDescription;
    private string $outputClass;
    private bool $defaultToStdClass;
    private string $deserializationErrorPrompt;
    private bool $throwOnTransformationFailure;
    private array $chatStructure;
    public string $responseIterator;

    public function __construct(
        ?OutputMode $outputMode = null,
        ?string $outputClass = null,
        ?bool $useObjectReferences = null,
        ?int $maxRetries = null,
        ?string $schemaName = null,
        ?string $schemaDescription = null,
        ?string $toolName = null,
        ?string $toolDescription = null,
        ?array $modePrompts = null,
        ?string $retryPrompt = null,
        ?array $chatStructure = null,
        ?bool $defaultToStdClass = null,
        ?string $deserializationErrorPrompt = null,
        ?bool $throwOnTransformationFailure = null,
        ?string $responseIterator = null,
    ) {
        $this->responseIterator = $responseIterator ?? 'modular'; // 'partials', 'legacy', 'modular'
        $this->outputMode = $outputMode ?: OutputMode::Tools;
        $this->useObjectReferences = $useObjectReferences ?? false;
        $this->maxRetries = $maxRetries ?? 0;
        if ($this->maxRetries < 0) {
            throw new ConfigurationException("maxRetries cannot be negative, got: {$this->maxRetries}");
        }
        $this->retryPrompt = $retryPrompt ?? "JSON generated incorrectly, fix following errors:\n";
        $this->modePrompts = $modePrompts ?? [
            OutputMode::MdJson->value => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",
            OutputMode::Json->value => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
            OutputMode::JsonSchema->value => "Response must follow provided JSON Schema. Respond correctly with strict JSON object.\n",
            OutputMode::Tools->value => "Extract correct and accurate data from the input using provided tools.\n",
        ];
        $this->schemaName = $schemaName ?? 'default_schema';
        $this->schemaDescription = $schemaDescription ?? '';
        $this->toolName = $toolName ?? 'extracted_data';
        $this->toolDescription = $toolDescription ?? 'Function call based on user instructions.';
        $this->chatStructure = $chatStructure ?? [
            // potentially cached - predefined sections used to construct the script
            'system',
            'pre-cached',
            'pre-cached-prompt', 'cached-prompt', 'post-cached-prompt',
            'pre-cached-examples', 'cached-examples', 'post-cached-examples',
            'cached-messages',
            'post-cached',
            // never cached
            'pre-prompt', 'prompt', 'post-prompt',
            'pre-examples', 'examples', 'post-examples',
            'pre-messages', 'messages', 'post-messages',
            'pre-retries', 'retries', 'post-retries',
        ];
        $this->outputClass = $outputClass ?? 'Cognesy\Dynamic\Structure';
        $this->defaultToStdClass = $defaultToStdClass ?? false;
        $this->deserializationErrorPrompt = $deserializationErrorPrompt ?? "Failed to serialize response:\n<|json|>\n\nSerializer error:\n<|error|>\n\nExpected schema:\n<|jsonSchema|>\n";
        $this->throwOnTransformationFailure = $throwOnTransformationFailure ?? false;
    }

    public function toArray(): array {
        return [
            'outputMode' => $this->outputMode->value,
            'useObjectReferences' => $this->useObjectReferences,
            'maxRetries' => $this->maxRetries,
            'retryPrompt' => $this->retryPrompt,
            'modePrompts' => $this->modePrompts,
            'toolName' => $this->toolName,
            'toolDescription' => $this->toolDescription,
            'chatStructure' => $this->chatStructure,
            'schemaName' => $this->schemaName,
            'schemaDescription' => $this->schemaDescription,
            'outputClass' => $this->outputClass,
            'defaultToStdClass' => $this->defaultToStdClass,
            'deserializationErrorPrompt' => $this->deserializationErrorPrompt,
            'throwOnTransformationFailure' => $this->throwOnTransformationFailure,
            'responseIterator' => $this->responseIterator,
        ];
    }

    public static function fromArray(array $config): self {
        try {
            // Ensure 'outputMode' is set to a valid OutputMode enum value
            $config['outputMode'] = match (true) {
                !isset($config['outputMode']) => OutputMode::Tools,
                is_string($config['outputMode']) => OutputMode::fromText($config['outputMode']),
                $config['outputMode'] instanceof OutputMode => $config['outputMode'],
                default => OutputMode::Tools,
            };
            $instance = new self(...$config);
        } catch (Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new ConfigurationException(
                message: "Failed to create StructuredOutputConfig from array:\n$data\nError: {$e->getMessage()}",
                previous: $e,
            );
        }
        return $instance;
    }

    public static function fromDsn(string $dsn): self {
        $data = Dsn::fromString($dsn)->toArray();
        unset($data['preset']);
        return self::fromArray($data);
    }

    public function withOverrides(array $values): self {
        $config = array_merge($this->toArray(), $values);
        return self::fromArray($config);
    }

    // ACCESSORS ////////////////////////////////////////////////////

    public function outputMode(): OutputMode {
        return $this->outputMode;
    }

    public function prompt(OutputMode $mode): string {
        return $this->modePrompts[$mode->value] ?? '';
    }

    public function modePrompts(): array {
        return $this->modePrompts;
    }

    public function retryPrompt(): string {
        return $this->retryPrompt;
    }

    public function chatStructure(): array {
        return $this->chatStructure;
    }

    public function schemaName(): string {
        return $this->schemaName;
    }

    public function schemaDescription(): string {
        return $this->schemaDescription;
    }

    public function toolName(): string {
        return $this->toolName;
    }

    public function toolDescription(): string {
        return $this->toolDescription;
    }

    public function useObjectReferences(): bool {
        return $this->useObjectReferences;
    }

    public function maxRetries(): int {
        return $this->maxRetries;
    }

    public function outputClass(): string {
        return $this->outputClass;
    }

    public function deserializationErrorPrompt(): string {
        return $this->deserializationErrorPrompt;
    }

    public function defaultToStdClass(): bool {
        return $this->defaultToStdClass;
    }

    public function throwOnTransformationFailure(): bool {
        return $this->throwOnTransformationFailure;
    }

    public function responseIterator(): string {
        return $this->responseIterator;
    }

    // MUTATORS /////////////////////////////////////////////////////

    public function withOutputMode(?OutputMode $outputMode): static {
        return $this->with(outputMode: $outputMode);
    }

    public function withMaxRetries(int $maxRetries): static {
        return $this->with(maxRetries: $maxRetries);
    }

    public function withSchemaName(string $schemaName): static {
        return $this->with(schemaName: $schemaName);
    }

    public function withToolName(string $toolName): static {
        return $this->with(toolName: $toolName);
    }

    public function withToolDescription(string $toolDescription): static {
        return $this->with(toolDescription: $toolDescription);
    }

    public function withUseObjectReferences(bool $useObjectReferences): static {
        return $this->with(useObjectReferences: $useObjectReferences);
    }

    public function withRetryPrompt(string $retryPrompt): static {
        return $this->with(retryPrompt: $retryPrompt);
    }

    public function withModePrompt(OutputMode $mode, string $prompt): static {
        return $this->withModePrompts(array_merge($this->modePrompts, [
            $mode->value => $prompt,
        ]));
    }

    public function withModePrompts(array $modePrompts): static {
        return $this->with(modePrompts: $modePrompts);
    }

    public function withChatStructure(array $chatStructure): static {
        return $this->with(chatStructure: $chatStructure);
    }

    public function withDefaultOutputClass(string $defaultOutputClass): static {
        return $this->with(outputClass: $defaultOutputClass);
    }

    public function withResponseIterator(string $responseIterator): static {
        return $this->with(responseIterator: $responseIterator);
    }

    // INTERNAL ////////////////////////////////////////////////////

    public function with(
        ?OutputMode $outputMode = null,
        ?string $outputClass = null,
        ?bool $useObjectReferences = null,
        ?int $maxRetries = null,
        ?string $schemaName = null,
        ?string $schemaDescription = null,
        ?string $toolName = null,
        ?string $toolDescription = null,
        ?array $modePrompts = null,
        ?string $retryPrompt = null,
        ?array $chatStructure = null,
        ?bool $defaultToStdClass = null,
        ?string $deserializationErrorPrompt = null,
        ?bool $throwOnTransformationFailure = null,
        ?string $responseIterator = null,
    ): self {
        return new self(
            outputMode: $outputMode ?? $this->outputMode,
            outputClass: $outputClass ?? $this->outputClass,
            useObjectReferences: $useObjectReferences ?? $this->useObjectReferences,
            maxRetries: $maxRetries ?? $this->maxRetries,
            schemaName: $schemaName ?? $this->schemaName,
            schemaDescription: $schemaDescription ?? $this->schemaDescription,
            toolName: $toolName ?? $this->toolName,
            toolDescription: $toolDescription ?? $this->toolDescription,
            modePrompts: $modePrompts ?? $this->modePrompts,
            retryPrompt: $retryPrompt ?? $this->retryPrompt,
            chatStructure: $chatStructure ?? $this->chatStructure,
            defaultToStdClass: $defaultToStdClass ?? $this->defaultToStdClass,
            deserializationErrorPrompt: $deserializationErrorPrompt ?? $this->deserializationErrorPrompt,
            throwOnTransformationFailure: $throwOnTransformationFailure ?? $this->throwOnTransformationFailure,
            responseIterator: $responseIterator ?? $this->responseIterator,
        );
    }
}