<?php declare(strict_types=1);

namespace Cognesy\Instructor\Config;

use Cognesy\Config\Dsn;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use InvalidArgumentException;
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
    private array $modePromptClasses;
    private string $retryPromptClass;
    private string $schemaName;
    private string $schemaDescription;
    private string $toolName;
    private string $toolDescription;
    private string $outputClass;
    private bool $defaultToStdClass;
    private string $deserializationErrorPromptClass;
    private bool $throwOnTransformationFailure;
    private array $chatStructure;
    private ResponseCachePolicy $responseCachePolicy;
    private int $streamMaterializationInterval;

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
        ?array $modePromptClasses = null,
        ?string $retryPrompt = null,
        ?string $retryPromptClass = null,
        ?array $chatStructure = null,
        ?bool $defaultToStdClass = null,
        ?string $deserializationErrorPromptClass = null,
        ?bool $throwOnTransformationFailure = null,
        ?ResponseCachePolicy $responseCachePolicy = null,
        ?int $streamMaterializationInterval = null,
    ) {
        $this->outputMode = $outputMode ?: OutputMode::Tools;
        $this->useObjectReferences = $useObjectReferences ?? false;
        $this->maxRetries = $maxRetries ?? 0;
        if ($this->maxRetries < 0) {
            throw new InvalidArgumentException("maxRetries cannot be negative, got: {$this->maxRetries}");
        }
        $this->retryPrompt = $retryPrompt ?? "JSON generated incorrectly, fix following errors:\n";
        $this->modePrompts = $modePrompts ?? [
            OutputMode::MdJson->value => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",
            OutputMode::Json->value => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
            OutputMode::JsonSchema->value => "Response must follow provided JSON Schema. Respond correctly with strict JSON object.\n",
            OutputMode::Tools->value => "Extract correct and accurate data from the input using provided tools.\n",
        ];
        $this->modePromptClasses = $modePromptClasses ?? [
            OutputMode::MdJson->value => 'Cognesy\\Instructor\\Prompts\\StructuredOutput\\MdJsonSystemPrompt',
            OutputMode::Json->value => 'Cognesy\\Instructor\\Prompts\\StructuredOutput\\JsonSystemPrompt',
            OutputMode::JsonSchema->value => 'Cognesy\\Instructor\\Prompts\\StructuredOutput\\JsonSchemaSystemPrompt',
            OutputMode::Tools->value => 'Cognesy\\Instructor\\Prompts\\StructuredOutput\\ToolsSystemPrompt',
        ];
        $this->retryPromptClass = $retryPromptClass
            ?? 'Cognesy\\Instructor\\Prompts\\StructuredOutput\\RetryFeedbackPrompt';
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
        $this->deserializationErrorPromptClass = $deserializationErrorPromptClass
            ?? 'Cognesy\\Instructor\\Prompts\\StructuredOutput\\DeserializationRepairPrompt';
        $this->throwOnTransformationFailure = $throwOnTransformationFailure ?? false;
        $this->responseCachePolicy = $responseCachePolicy ?? ResponseCachePolicy::None;
        $this->streamMaterializationInterval = max(1, $streamMaterializationInterval ?? 1);
    }

    public function toArray(): array {
        return [
            'outputMode' => $this->outputMode->value,
            'useObjectReferences' => $this->useObjectReferences,
            'maxRetries' => $this->maxRetries,
            'retryPrompt' => $this->retryPrompt,
            'modePrompts' => $this->modePrompts,
            'modePromptClasses' => $this->modePromptClasses,
            'retryPromptClass' => $this->retryPromptClass,
            'toolName' => $this->toolName,
            'toolDescription' => $this->toolDescription,
            'chatStructure' => $this->chatStructure,
            'schemaName' => $this->schemaName,
            'schemaDescription' => $this->schemaDescription,
            'outputClass' => $this->outputClass,
            'defaultToStdClass' => $this->defaultToStdClass,
            'deserializationErrorPromptClass' => $this->deserializationErrorPromptClass,
            'throwOnTransformationFailure' => $this->throwOnTransformationFailure,
            'responseCachePolicy' => $this->responseCachePolicy->value,
            'streamMaterializationInterval' => $this->streamMaterializationInterval,
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
            $config['responseCachePolicy'] = match (true) {
                !isset($config['responseCachePolicy']) => ResponseCachePolicy::None,
                is_string($config['responseCachePolicy']) => ResponseCachePolicy::from($config['responseCachePolicy']),
                $config['responseCachePolicy'] instanceof ResponseCachePolicy => $config['responseCachePolicy'],
                default => ResponseCachePolicy::None,
            };
            $instance = new self(...$config);
        } catch (Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new InvalidArgumentException(
                message: "Failed to create StructuredOutputConfig from array:\n$data\nError: {$e->getMessage()}",
                previous: $e,
            );
        }
        return $instance;
    }

    public static function fromDsn(string $dsn): self {
        $data = Dsn::fromString($dsn)->toArray();
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

    public function modePromptClass(OutputMode $mode): string {
        return $this->modePromptClasses[$mode->value] ?? '';
    }

    public function modePromptClasses(): array {
        return $this->modePromptClasses;
    }

    public function retryPrompt(): string {
        return $this->retryPrompt;
    }

    public function retryPromptClass(): string {
        return $this->retryPromptClass;
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

    /**
     * Maximum number of retries after the first attempt.
     * Total attempts allowed = maxRetries + 1.
     */
    public function maxRetries(): int {
        return $this->maxRetries;
    }

    public function outputClass(): string {
        return $this->outputClass;
    }

    public function deserializationErrorPromptClass(): string {
        return $this->deserializationErrorPromptClass;
    }

    public function defaultToStdClass(): bool {
        return $this->defaultToStdClass;
    }

    public function throwOnTransformationFailure(): bool {
        return $this->throwOnTransformationFailure;
    }

    public function responseCachePolicy(): ResponseCachePolicy {
        return $this->responseCachePolicy;
    }

    /**
     * How many streaming deltas to accumulate before materializing
     * (parsing JSON, deserializing, emitting partial). Higher values
     * reduce CPU cost at the expense of partial-update granularity.
     * Default: 1 (materialize on every delta).
     */
    public function streamMaterializationInterval(): int {
        return $this->streamMaterializationInterval;
    }

    // MUTATORS /////////////////////////////////////////////////////

    public function withOutputMode(?OutputMode $outputMode): static {
        return $this->with(outputMode: $outputMode);
    }

    public function withMaxRetries(int $maxRetries): static {
        return $this->with(maxRetries: $maxRetries);
    }

    public function withResponseCachePolicy(ResponseCachePolicy $responseCachePolicy): static {
        return $this->with(responseCachePolicy: $responseCachePolicy);
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

    public function withModePromptClass(OutputMode $mode, string $promptClass): static {
        return $this->withModePromptClasses(array_merge($this->modePromptClasses, [
            $mode->value => $promptClass,
        ]));
    }

    public function withModePromptClasses(array $modePromptClasses): static {
        return $this->with(modePromptClasses: $modePromptClasses);
    }

    public function withRetryPromptClass(string $retryPromptClass): static {
        return $this->with(retryPromptClass: $retryPromptClass);
    }

    public function withChatStructure(array $chatStructure): static {
        return $this->with(chatStructure: $chatStructure);
    }

    public function withDefaultOutputClass(string $defaultOutputClass): static {
        return $this->with(outputClass: $defaultOutputClass);
    }

    public function withDeserializationErrorPromptClass(string $deserializationErrorPromptClass): static {
        return $this->with(deserializationErrorPromptClass: $deserializationErrorPromptClass);
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
        ?array $modePromptClasses = null,
        ?string $retryPrompt = null,
        ?string $retryPromptClass = null,
        ?array $chatStructure = null,
        ?bool $defaultToStdClass = null,
        ?string $deserializationErrorPromptClass = null,
        ?bool $throwOnTransformationFailure = null,
        ?ResponseCachePolicy $responseCachePolicy = null,
        ?int $streamMaterializationInterval = null,
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
            modePromptClasses: $modePromptClasses ?? $this->modePromptClasses,
            retryPrompt: $retryPrompt ?? $this->retryPrompt,
            retryPromptClass: $retryPromptClass ?? $this->retryPromptClass,
            chatStructure: $chatStructure ?? $this->chatStructure,
            defaultToStdClass: $defaultToStdClass ?? $this->defaultToStdClass,
            deserializationErrorPromptClass: $deserializationErrorPromptClass ?? $this->deserializationErrorPromptClass,
            throwOnTransformationFailure: $throwOnTransformationFailure ?? $this->throwOnTransformationFailure,
            responseCachePolicy: $responseCachePolicy ?? $this->responseCachePolicy,
            streamMaterializationInterval: $streamMaterializationInterval ?? $this->streamMaterializationInterval,
        );
    }
}
