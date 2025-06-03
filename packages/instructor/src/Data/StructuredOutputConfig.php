<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Config\Settings;

class StructuredOutputConfig
{
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

    public static function load() : static {
        return new static(
            outputMode: OutputMode::from(Settings::get('structured', 'defaultMode', '')),
            useObjectReferences: Settings::get('structured', 'useObjectReferences', null),
            maxRetries: Settings::get('structured', 'maxRetries', 0),
            retryPrompt: Settings::get('structured', 'defaultRetryPrompt', ''),
            modePrompts: [
                OutputMode::MdJson->value => Settings::get('structured', 'defaultMdJsonPrompt', ''),
                OutputMode::Json->value => Settings::get('structured', 'defaultJsonPrompt', ''),
                OutputMode::JsonSchema->value => Settings::get('structured', 'defaultJsonSchemaPrompt', ''),
                OutputMode::Tools->value => Settings::get('structured', 'defaultToolsPrompt', ''),
            ],
            schemaName: Settings::get('structured', 'defaultSchemaName', ''),
            toolName: Settings::get('structured', 'defaultToolName', ''),
            toolDescription: Settings::get('structured', 'defaultToolDescription', ''),
            chatStructure: Settings::get('structured', 'defaultChatStructure', []),
            defaultOutputClass: Settings::get('structured', 'defaultOutputClass', ''),
        );
    }

    // ACCESSORS ///////////////////////////////////////////////////////
    public static function default() : self {
        return new self();
    }

    public function outputMode() : OutputMode {
        return $this->outputMode;
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

    public function schemaName() : string {
        return $this->schemaName;
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

    public function maxRetries() : int {
        return $this->maxRetries;
    }

    public function defaultOutputClass() : string {
        return $this->defaultOutputClass;
    }

    // MUTATORS ///////////////////////////////////////////////////////

    public function withOutputMode(OutputMode $outputMode) : static
    {
        $this->outputMode = $outputMode;
        return $this;
    }

    public function withMaxRetries(int $maxRetries) : static
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function withSchemaName(string $schemaName) : static {
        $this->schemaName = $schemaName;
        return $this;
    }

    public function withToolName(string $toolName) : static
    {
        $this->toolName = $toolName;
        return $this;
    }

    public function withToolDescription(string $toolDescription) : static
    {
        $this->toolDescription = $toolDescription;
        return $this;
    }

    public function withUseObjectReferences(bool $useObjectReferences) : static
    {
        $this->useObjectReferences = $useObjectReferences;
        return $this;
    }

    public function withRetryPrompt(string $retryPrompt) : static
    {
        $this->retryPrompt = $retryPrompt;
        return $this;
    }

    public function withModePrompt(OutputMode $mode, string $prompt) : static
    {
        $this->modePrompts[$mode->value] = $prompt;
        return $this;
    }

    public function withModePrompts(array $modePrompts) : static
    {
        $this->modePrompts = $modePrompts;
        return $this;
    }

    public function withChatStructure(array $chatStructure) : static
    {
        $this->chatStructure = $chatStructure;
        return $this;
    }

    public function withDefaultOutputClass(string $defaultOutputClass) : static
    {
        $this->defaultOutputClass = $defaultOutputClass;
        return $this;
    }

    public function withOverrides(
        ?OutputMode    $outputMode = null,
        ?bool          $useObjectReferences = null,
        ?int           $maxRetries = null,
        ?string        $retryPrompt = null,
        ?string        $toolName = null,
        ?string        $toolDescription = null,
    ) : static {
        $this->outputMode = $outputMode ?? $this->outputMode;
        $this->useObjectReferences = $useObjectReferences ?? $this->useObjectReferences;
        $this->maxRetries = $maxRetries ?? $this->maxRetries;
        $this->toolName = $toolName ?? $this->toolName;
        $this->toolDescription = $toolDescription ?? $this->toolDescription;
        $this->retryPrompt = $retryPrompt ?? $this->retryPrompt;
        return $this;
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