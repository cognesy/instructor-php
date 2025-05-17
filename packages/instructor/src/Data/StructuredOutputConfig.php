<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Settings;

class StructuredOutputConfig
{
    private string $retryPrompt = "JSON generated incorrectly, fix following errors:\n";
    private array $modePrompts = [
        OutputMode::MdJson->value => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",
        OutputMode::Json->value => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
        OutputMode::JsonSchema->value => "Response must follow provided JSON Schema. Respond correctly with strict JSON object.\n",
        OutputMode::Tools->value => "Extract correct and accurate data from the input using provided tools.\n",
    ];
    private bool $useObjectReferences;
    private string $toolName = 'extracted_data';
    private string $toolDescription = 'Function call based on user instructions.';

    private array $chatStructure = [
        // potentially cached - predefined sections used to construct the script
        'system',
        'pre-cached',
        'pre-cached-prompt', 'cached-prompt', 'post-cached-prompt',
        'pre-cached-examples', 'cached-examples', 'post-cached-examples',
        'pre-cached-input', 'cached-input', 'post-cached-input',
        'cached-messages',
        'post-cached',
        // never cached
        'pre-prompt', 'prompt', 'post-prompt',
        'pre-examples', 'examples', 'post-examples',
        'pre-input', 'input', 'post-input',
        'messages',
        'pre-retries', 'retries', 'post-retries'
    ];

    public function __construct(
        string $retryPrompt = '',
        array $modePrompts = [],
        array $chatStructure = [],
        string $toolName = '',
        string $toolDescription = '',
        bool $useObjectReferences = false,
    ) {
        $this->retryPrompt = $retryPrompt ?: $this->retryPrompt;
        $this->modePrompts = $modePrompts ?: $this->modePrompts;
        $this->chatStructure = $chatStructure ?: $this->chatStructure;
        $this->toolName = $toolName ?: $this->toolName;
        $this->toolDescription = $toolDescription ?: $this->toolDescription;
        $this->useObjectReferences = $useObjectReferences;
    }

    public static function load() : static {
        return new static(
            retryPrompt: Settings::get('structured', 'defaultRetryPrompt'),
            modePrompts: [
                OutputMode::MdJson->value => Settings::get('structured', 'defaultMdJsonPrompt'),
                OutputMode::Json->value => Settings::get('structured', 'defaultJsonPrompt'),
                OutputMode::JsonSchema->value => Settings::get('structured', 'defaultJsonSchemaPrompt'),
                OutputMode::Tools->value => Settings::get('structured', 'defaultToolsPrompt'),
            ],
            chatStructure: Settings::get('structured', 'defaultChatStructure'),
            toolName: Settings::get('structured', 'defaultToolName'),
            toolDescription: Settings::get('structured', 'defaultToolDescription'),
            useObjectReferences: Settings::get('structured', 'useObjectReferences'),
        );
    }

    public function prompt(OutputMode $mode) : string {
        return $this->modePrompts[$mode->value] ?? '';
    }

    public function modePrompts() : array {
        return $this->modePrompts;
    }

    public function retryPrompt() : string {
        return $this->retryPrompt;
    }

    public function chatStructure() : array {
        return $this->chatStructure;
    }

    public function toolName() : string {
        return $this->toolName;
    }

    public function toolDescription() : string {
        return $this->toolDescription;
    }

    public function useObjectReferences() : bool {
        return $this->useObjectReferences;
    }
}