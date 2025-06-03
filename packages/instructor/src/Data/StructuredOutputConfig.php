<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\LLM\Enums\OutputMode;

final class StructuredOutputConfig
{
    use Traits\StructuredOutputConfig\HandlesAccessors;
    use Traits\StructuredOutputConfig\HandlesMutators;

    private OutputMode $outputMode = OutputMode::Tools;
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
    private string $toolName = 'extracted_data';
    private string $toolDescription = 'Function call based on user instructions.';
    private string $defaultOutputClass = 'Cognesy\Instructor\Extras\Structure\Structure';

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
        OutputMode $outputMode = null,
        bool       $useObjectReferences = false,
        int        $maxRetries = -1,
        string     $retryPrompt = '',
        array      $modePrompts = [],
        string     $schemaName = '',
        string     $toolName = '',
        string     $toolDescription = '',
        array      $chatStructure = [],
        string     $defaultOutputClass = '',
    ) {
        $this->outputMode = $outputMode ?: $this->outputMode;
        $this->useObjectReferences = $useObjectReferences ?? $this->useObjectReferences;
        $this->maxRetries = ($maxRetries >= 0) ? $maxRetries : $this->maxRetries;
        $this->retryPrompt = $retryPrompt ?: $this->retryPrompt;
        $this->modePrompts = $modePrompts ?: $this->modePrompts;
        $this->schemaName = $schemaName ?: $this->schemaName;
        $this->toolName = $toolName ?: $this->toolName;
        $this->toolDescription = $toolDescription ?: $this->toolDescription;
        $this->chatStructure = $chatStructure ?: $this->chatStructure;
        $this->defaultOutputClass = $defaultOutputClass ?: $this->defaultOutputClass;
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
        ];
    }
}