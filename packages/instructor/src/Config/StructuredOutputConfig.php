<?php declare(strict_types=1);

namespace Cognesy\Instructor\Config;

use Cognesy\Config\Dsn;
use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Instructor\Data\Traits;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Throwable;

final class StructuredOutputConfig
{
    public const CONFIG_GROUP = 'structured';

    public static function group(): string {
        return self::CONFIG_GROUP;
    }

    private ?OutputMode $outputMode = OutputMode::Tools;
    private bool $useObjectReferences = false;
    private int $maxRetries = 0;
    private string $retryPrompt = "JSON generated incorrectly, fix following errors:\n";
    private array $modePrompts = [
        OutputMode::MdJson->value => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",
        OutputMode::Json->value => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
        OutputMode::JsonSchema->value => "Response must follow provided JSON Schema. Respond correctly with strict JSON object.\n",
        OutputMode::Tools->value => "Extract correct and accurate data from the input using provided tools.\n",
    ];
    private string $schemaName = 'default_schema';
    private string $schemaDescription = '';
    private string $toolName = 'extracted_data';
    private string $toolDescription = 'Function call based on user instructions.';
    private string $outputClass = 'Cognesy\Dynamic\Structure';
    private bool $defaultToStdClass = false;
    private string $deserializationErrorPrompt = "Failed to serialize response:\n<|json|>\n\nSerializer error:\n<|error|>\n\nExpected schema:\n<|jsonSchema|>\n";
    private bool $throwOnTransformationFailure = false;

    private array $chatStructure = [
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

    public function __construct(
        ?OutputMode $outputMode = null,
        ?string $outputClass = '',
        ?bool $useObjectReferences = false,
        ?int $maxRetries = -1,
        ?string $schemaName = '',
        ?string $schemaDescription = '',
        ?string $toolName = '',
        ?string $toolDescription = '',
        ?array $modePrompts = [],
        ?string $retryPrompt = '',
        ?array $chatStructure = [],
        ?bool $defaultToStdClass = false,
        ?string $deserializationErrorPrompt = '',
        ?bool $throwOnTransformationFailure = false,
    ) {
        $this->outputMode = $outputMode ?: $this->outputMode;
        $this->useObjectReferences = $useObjectReferences ?? $this->useObjectReferences;
        $this->maxRetries = ($maxRetries >= 0) ? $maxRetries : $this->maxRetries;
        $this->retryPrompt = $retryPrompt ?: $this->retryPrompt;
        $this->modePrompts = $modePrompts ?: $this->modePrompts;
        $this->schemaName = $schemaName ?: $this->schemaName;
        $this->schemaDescription = $schemaDescription ?: $this->schemaDescription;
        $this->toolName = $toolName ?: $this->toolName;
        $this->toolDescription = $toolDescription ?: $this->toolDescription;
        $this->chatStructure = $chatStructure ?: $this->chatStructure;
        $this->outputClass = $outputClass ?: $this->outputClass;
        $this->defaultToStdClass = $defaultToStdClass ?? $this->defaultToStdClass;
        $this->deserializationErrorPrompt = $deserializationErrorPrompt ?: $this->deserializationErrorPrompt;
        $this->throwOnTransformationFailure = $throwOnTransformationFailure ?? $this->throwOnTransformationFailure;
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

    public function clone(): self {
        return new self(
            outputMode: $this->outputMode,
            outputClass: $this->outputClass,
            useObjectReferences: $this->useObjectReferences,
            maxRetries: $this->maxRetries,
            schemaName: $this->schemaName,
            schemaDescription: $this->schemaDescription,
            toolName: $this->toolName,
            toolDescription: $this->toolDescription,
            modePrompts: $this->modePrompts,
            retryPrompt: $this->retryPrompt,
            chatStructure: $this->chatStructure,
            defaultToStdClass: $this->defaultToStdClass,
            deserializationErrorPrompt: $this->deserializationErrorPrompt,
            throwOnTransformationFailure: $this->throwOnTransformationFailure,
        );
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

    // MUTATORS /////////////////////////////////////////////////////

    public function withOutputMode(?OutputMode $outputMode): static {
        $this->outputMode = $outputMode;
        return $this;
    }

    public function withMaxRetries(int $maxRetries): static {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function withSchemaName(string $schemaName): static {
        $this->schemaName = $schemaName;
        return $this;
    }

    public function withToolName(string $toolName): static {
        $this->toolName = $toolName;
        return $this;
    }

    public function withToolDescription(string $toolDescription): static {
        $this->toolDescription = $toolDescription;
        return $this;
    }

    public function withUseObjectReferences(bool $useObjectReferences): static {
        $this->useObjectReferences = $useObjectReferences;
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt): static {
        $this->retryPrompt = $retryPrompt;
        return $this;
    }

    public function withModePrompt(OutputMode $mode, string $prompt): static {
        $this->modePrompts[$mode->value] = $prompt;
        return $this;
    }

    public function withModePrompts(array $modePrompts): static {
        $this->modePrompts = $modePrompts;
        return $this;
    }

    public function withChatStructure(array $chatStructure): static {
        $this->chatStructure = $chatStructure;
        return $this;
    }

    public function withDefaultOutputClass(string $defaultOutputClass): static {
        $this->outputClass = $defaultOutputClass;
        return $this;
    }
}