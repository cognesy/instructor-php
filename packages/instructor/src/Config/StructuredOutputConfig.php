<?php

namespace Cognesy\Instructor\Config;

use Cognesy\Instructor\Data\Traits;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

final class StructuredOutputConfig
{
    use Traits\StructuredOutputConfig\HandlesAccessors;
    use Traits\StructuredOutputConfig\HandlesMutators;

    public const CONFIG_GROUP = 'structured';

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
    private string $defaultOutputClass = 'Cognesy\Instructor\Extras\Structure\Structure';
    private bool $defaultToAnonymousClass = false;
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
        ?bool       $useObjectReferences = false,
        ?int        $maxRetries = -1,
        ?string     $retryPrompt = '',
        ?array      $modePrompts = [],
        ?string     $schemaName = '',
        ?string     $schemaDescription = '',
        ?string     $toolName = '',
        ?string     $toolDescription = '',
        ?array      $chatStructure = [],
        ?string     $defaultOutputClass = '',
        ?bool       $defaultToAnonymousClass = false,
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
        $this->defaultOutputClass = $defaultOutputClass ?: $this->defaultOutputClass;
        $this->defaultToAnonymousClass = $defaultToAnonymousClass ?? $this->defaultToAnonymousClass;
        $this->deserializationErrorPrompt = $deserializationErrorPrompt ?: $this->deserializationErrorPrompt;
        $this->throwOnTransformationFailure = $throwOnTransformationFailure ?? $this->throwOnTransformationFailure;
    }

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    public function toArray() : array {
        return [
            'defaultOutputMode' => $this->outputMode->value,
            'useObjectReferences' => $this->useObjectReferences,
            'maxRetries' => $this->maxRetries,
            'retryPrompt' => $this->retryPrompt,
            'modePrompts' => $this->modePrompts,
            'jsonPrompt' => $this->modePrompts[OutputMode::Json->value] ?? '',
            'jsonSchemaPrompt' => $this->modePrompts[OutputMode::JsonSchema->value] ?? '',
            'mdJsonPrompt' => $this->modePrompts[OutputMode::MdJson->value] ?? '',
            'toolsPrompt' => $this->modePrompts[OutputMode::Tools->value] ?? '',
            'defaultToolName' => $this->toolName,
            'defaultToolDescription' => $this->toolDescription,
            'chatStructure' => $this->chatStructure,
            'defaultSchemaName' => $this->schemaName,
            'defaultSchemaDescription' => $this->schemaDescription,
            'defaultOutputClass' => $this->defaultOutputClass,
            'defaultToAnonymousClass' => $this->defaultToAnonymousClass,
            'deserializationErrorPrompt' => $this->deserializationErrorPrompt,
        ];
    }

    public static function fromArray(array $config): self {
        return new self(
            outputMode: OutputMode::fromText(($config['defaultOutputMode'] ?? '')),
            useObjectReferences: $config['useObjectReferences'] ?? false,
            maxRetries: $config['maxRetries'] ?? -1,
            retryPrompt: $config['retryPrompt'] ?? '',
            modePrompts: [
                OutputMode::Json->value => $config['jsonPrompt'] ?? '',
                OutputMode::JsonSchema->value => $config['jsonSchemaPrompt'] ?? '',
                OutputMode::MdJson->value => $config['mdJsonPrompt'] ?? '',
                OutputMode::Tools->value => $config['toolsPrompt'] ?? ''
            ],
            schemaName: $config['defaultSchemaName'] ?? '',
            schemaDescription: $config['defaultSchemaDescription'] ?? '',
            toolName: $config['defaultToolName'] ?? '',
            toolDescription: $config['defaultToolDescription'] ?? '',
            chatStructure: $config['chatStructure'] ?? [],
            defaultOutputClass: $config['defaultOutputClass'] ?? '',
            defaultToAnonymousClass: $config['defaultToAnonymousClass'] ?? false,
            deserializationErrorPrompt: $config['deserializationErrorPrompt'] ?? '',
            throwOnTransformationFailure: $config['throwOnTransformationFailure'] ?? false,
        );
    }

    public function withOverrides(StructuredOutputConfig $overrides) : self {
        return new self(
            outputMode: $overrides->outputMode ?? $this->outputMode,
            useObjectReferences: $overrides->useObjectReferences ?? $this->useObjectReferences,
            maxRetries: $overrides->maxRetries >= 0 ? $overrides->maxRetries : $this->maxRetries,
            retryPrompt: $overrides->retryPrompt ?: $this->retryPrompt,
            modePrompts: array_merge($this->modePrompts, $overrides->modePrompts),
            schemaName: $overrides->schemaName ?: $this->schemaName,
            schemaDescription: $overrides->schemaDescription ?: $this->schemaDescription,
            toolName: $overrides->toolName ?: $this->toolName,
            toolDescription: $overrides->toolDescription ?: $this->toolDescription,
            chatStructure: array_merge($this->chatStructure, $overrides->chatStructure),
            defaultOutputClass: $overrides->defaultOutputClass ?: $this->defaultOutputClass,
            defaultToAnonymousClass: $overrides->defaultToAnonymousClass ?? $this->defaultToAnonymousClass,
            deserializationErrorPrompt: $overrides->deserializationErrorPrompt ?: $this->deserializationErrorPrompt,
            throwOnTransformationFailure: $overrides->throwOnTransformationFailure ?? $this->throwOnTransformationFailure,
        );
    }

    public function clone() : self {
        return new self(
            outputMode: $this->outputMode,
            useObjectReferences: $this->useObjectReferences,
            maxRetries: $this->maxRetries,
            retryPrompt: $this->retryPrompt,
            modePrompts: $this->modePrompts,
            schemaName: $this->schemaName,
            schemaDescription: $this->schemaDescription,
            toolName: $this->toolName,
            toolDescription: $this->toolDescription,
            chatStructure: $this->chatStructure,
            defaultOutputClass: $this->defaultOutputClass,
            defaultToAnonymousClass: $this->defaultToAnonymousClass,
            deserializationErrorPrompt: $this->deserializationErrorPrompt,
            throwOnTransformationFailure: $this->throwOnTransformationFailure,
        );
    }
}