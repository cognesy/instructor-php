<?php declare(strict_types=1);

namespace Cognesy\Instructor\Config;

use Cognesy\Config\Dsn;
use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Instructor\Data\Traits;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Throwable;

final class StructuredOutputConfig
{
    use Traits\StructuredOutputConfig\HandlesAccessors;
    use Traits\StructuredOutputConfig\HandlesMutators;

    public const CONFIG_GROUP = 'structured';

    public static function group() : string {
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
        'pre-retries', 'retries', 'post-retries'
    ];

    public function __construct(
        ?OutputMode $outputMode = null,
        ?string     $outputClass = '',
        ?bool       $useObjectReferences = false,
        ?int        $maxRetries = -1,
        ?string     $schemaName = '',
        ?string     $schemaDescription = '',
        ?string     $toolName = '',
        ?string     $toolDescription = '',
        ?array      $modePrompts = [],
        ?string     $retryPrompt = '',
        ?array      $chatStructure = [],
        ?bool       $defaultToStdClass = false,
        ?string     $deserializationErrorPrompt = '',
        ?bool       $throwOnTransformationFailure = false,
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

    public function toArray() : array {
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

    public static function fromArray(array $config) : self {
        try {
            // Ensure 'outputMode' is set to a valid OutputMode enum value
            $config['outputMode'] = match(true) {
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
                previous: $e
            );
        }
        return $instance;
    }

    public static function fromDsn(string $dsn) : self {
        $data = Dsn::fromString($dsn)->toArray();
        unset($data['preset']);
        return self::fromArray($data);
    }

    public function withOverrides(array $values) : self {
        $config = array_merge($this->toArray(), $values);
        return self::fromArray($config);
    }

//    public function withOverrides(StructuredOutputConfig $overrides) : self {
//        return new self(
//            outputMode                  : $overrides->outputMode ?? $this->outputMode,
//            outputClass                 : $overrides->outputClass ?: $this->outputClass,
//            useObjectReferences         : $overrides->useObjectReferences ?? $this->useObjectReferences,
//            maxRetries                  : $overrides->maxRetries >= 0 ? $overrides->maxRetries : $this->maxRetries,
//            schemaName                  : $overrides->schemaName ?: $this->schemaName,
//            schemaDescription           : $overrides->schemaDescription ?: $this->schemaDescription,
//            toolName                    : $overrides->toolName ?: $this->toolName,
//            toolDescription             : $overrides->toolDescription ?: $this->toolDescription,
//            modePrompts                 : array_merge($this->modePrompts, $overrides->modePrompts),
//            retryPrompt                 : $overrides->retryPrompt ?: $this->retryPrompt,
//            chatStructure               : array_merge($this->chatStructure, $overrides->chatStructure),
//            defaultToStdClass     : $overrides->defaultToStdClass ?? $this->defaultToStdClass,
//            deserializationErrorPrompt  : $overrides->deserializationErrorPrompt ?: $this->deserializationErrorPrompt,
//            throwOnTransformationFailure: $overrides->throwOnTransformationFailure ?? $this->throwOnTransformationFailure,
//        );
//    }

    public function clone() : self {
        return new self(
            outputMode                  : $this->outputMode,
            outputClass                 : $this->outputClass,
            useObjectReferences         : $this->useObjectReferences,
            maxRetries                  : $this->maxRetries,
            schemaName                  : $this->schemaName,
            schemaDescription           : $this->schemaDescription,
            toolName                    : $this->toolName,
            toolDescription             : $this->toolDescription,
            modePrompts                 : $this->modePrompts,
            retryPrompt                 : $this->retryPrompt,
            chatStructure               : $this->chatStructure,
            defaultToStdClass     : $this->defaultToStdClass,
            deserializationErrorPrompt  : $this->deserializationErrorPrompt,
            throwOnTransformationFailure: $this->throwOnTransformationFailure,
        );
    }
}