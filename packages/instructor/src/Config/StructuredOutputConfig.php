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
    private array $modePromptClasses;
    private string $retryPromptClass;
    private string $schemaName;
    private string $schemaDescription;
    private string $toolName;
    private string $toolDescription;
    private bool $defaultToStdClass;
    private string $deserializationErrorPromptClass;
    private bool $throwOnTransformationFailure;
    private ResponseCachePolicy $responseCachePolicy;
    private int $streamMaterializationInterval;

    public function __construct(
        ?OutputMode $outputMode = null,
        ?bool $useObjectReferences = null,
        ?int $maxRetries = null,
        ?string $schemaName = null,
        ?string $schemaDescription = null,
        ?string $toolName = null,
        ?string $toolDescription = null,
        ?array $modePromptClasses = null,
        ?string $retryPromptClass = null,
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
            'modePromptClasses' => $this->modePromptClasses,
            'retryPromptClass' => $this->retryPromptClass,
            'toolName' => $this->toolName,
            'toolDescription' => $this->toolDescription,
            'schemaName' => $this->schemaName,
            'schemaDescription' => $this->schemaDescription,
            'defaultToStdClass' => $this->defaultToStdClass,
            'deserializationErrorPromptClass' => $this->deserializationErrorPromptClass,
            'throwOnTransformationFailure' => $this->throwOnTransformationFailure,
            'responseCachePolicy' => $this->responseCachePolicy->value,
            'streamMaterializationInterval' => $this->streamMaterializationInterval,
        ];
    }

    /**
     * Names of __construct() parameters, memoized. `fromArray()` spreads its input as
     * named arguments, so any key that is not a constructor parameter would be a fatal
     * `unknown named parameter` error — including keys from configs persisted before a
     * setting was removed (`retryPrompt`, `modePrompts`, `chatStructure`, ...).
     *
     * @return list<string>
     */
    private static function constructorKeys(): array {
        /** @var list<string>|null $keys */
        static $keys = null;
        if ($keys === null) {
            $keys = array_map(
                static fn(\ReflectionParameter $p): string => $p->getName(),
                (new \ReflectionMethod(self::class, '__construct'))->getParameters(),
            );
        }
        return $keys;
    }

    public static function fromArray(array $config): StructuredOutputConfig {
        try {
            // Unknown/removed keys are ignored rather than fatal — see constructorKeys().
            $config = array_intersect_key($config, array_flip(self::constructorKeys()));
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

    public static function fromDsn(string $dsn): StructuredOutputConfig {
        $data = Dsn::fromString($dsn)->toArray();
        return self::fromArray($data);
    }

    public function withOverrides(array $values): StructuredOutputConfig {
        $config = array_merge($this->toArray(), $values);
        return self::fromArray($config);
    }

    // ACCESSORS ////////////////////////////////////////////////////

    public function outputMode(): OutputMode {
        return $this->outputMode;
    }

    public function modePromptClass(OutputMode $mode): string {
        return $this->modePromptClasses[$mode->value] ?? '';
    }

    public function modePromptClasses(): array {
        return $this->modePromptClasses;
    }

    public function retryPromptClass(): string {
        return $this->retryPromptClass;
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

    public function deserializationErrorPromptClass(): string {
        return $this->deserializationErrorPromptClass;
    }

    /** @deprecated 2.5 Use per-request intoStdClass(); remove in 3.0. */
    public function defaultToStdClass(): bool {
        return $this->defaultToStdClass;
    }

    /**
     * @deprecated 2.5 Transformation failures always fail the attempt. This
     *             compatibility value is retained for config round-trips only.
     */
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
     *
     * Default: 1 — adaptive throttling: materialization requires the
     * accumulated buffer to grow by ~1/32 of its current size (min 16
     * bytes) since the last materialization. Early partials stay
     * token-frequent while total parse+deserialize work remains O(n)
     * on long outputs. Explicit values > 1 switch to fixed delta-count
     * throttling (materialize every N deltas).
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

    public function withSchemaDescription(string $schemaDescription): static {
        return $this->with(schemaDescription: $schemaDescription);
    }

    public function withStreamMaterializationInterval(int $streamMaterializationInterval): static {
        return $this->with(streamMaterializationInterval: $streamMaterializationInterval);
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

    public function withDeserializationErrorPromptClass(string $deserializationErrorPromptClass): static {
        return $this->with(deserializationErrorPromptClass: $deserializationErrorPromptClass);
    }

    // INTERNAL ////////////////////////////////////////////////////

    public function with(
        ?OutputMode $outputMode = null,
        ?bool $useObjectReferences = null,
        ?int $maxRetries = null,
        ?string $schemaName = null,
        ?string $schemaDescription = null,
        ?string $toolName = null,
        ?string $toolDescription = null,
        ?array $modePromptClasses = null,
        ?string $retryPromptClass = null,
        ?bool $defaultToStdClass = null,
        ?string $deserializationErrorPromptClass = null,
        ?bool $throwOnTransformationFailure = null,
        ?ResponseCachePolicy $responseCachePolicy = null,
        ?int $streamMaterializationInterval = null,
    ): StructuredOutputConfig {
        return new self(
            outputMode: $outputMode ?? $this->outputMode,
            useObjectReferences: $useObjectReferences ?? $this->useObjectReferences,
            maxRetries: $maxRetries ?? $this->maxRetries,
            schemaName: $schemaName ?? $this->schemaName,
            schemaDescription: $schemaDescription ?? $this->schemaDescription,
            toolName: $toolName ?? $this->toolName,
            toolDescription: $toolDescription ?? $this->toolDescription,
            modePromptClasses: $modePromptClasses ?? $this->modePromptClasses,
            retryPromptClass: $retryPromptClass ?? $this->retryPromptClass,
            defaultToStdClass: $defaultToStdClass ?? $this->defaultToStdClass,
            deserializationErrorPromptClass: $deserializationErrorPromptClass ?? $this->deserializationErrorPromptClass,
            throwOnTransformationFailure: $throwOnTransformationFailure ?? $this->throwOnTransformationFailure,
            responseCachePolicy: $responseCachePolicy ?? $this->responseCachePolicy,
            streamMaterializationInterval: $streamMaterializationInterval ?? $this->streamMaterializationInterval,
        );
    }
}
