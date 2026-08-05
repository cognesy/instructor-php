<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;

it('exposes default prompt class references as fqns', function () {
    $config = new StructuredOutputConfig();

    expect($config->modePromptClass(OutputMode::Json))->toBe('Cognesy\\Instructor\\Prompts\\StructuredOutput\\JsonSystemPrompt')
        ->and($config->modePromptClass(OutputMode::MdJson))->toBe('Cognesy\\Instructor\\Prompts\\StructuredOutput\\MdJsonSystemPrompt')
        ->and($config->modePromptClass(OutputMode::JsonSchema))->toBe('Cognesy\\Instructor\\Prompts\\StructuredOutput\\JsonSchemaSystemPrompt')
        ->and($config->modePromptClass(OutputMode::Tools))->toBe('Cognesy\\Instructor\\Prompts\\StructuredOutput\\ToolsSystemPrompt')
        ->and($config->retryPromptClass())->toBe('Cognesy\\Instructor\\Prompts\\StructuredOutput\\RetryFeedbackPrompt')
        ->and($config->deserializationErrorPromptClass())->toBe('Cognesy\\Instructor\\Prompts\\StructuredOutput\\DeserializationRepairPrompt');
});

it('serializes and restores prompt class references from arrays', function () {
    $config = StructuredOutputConfig::fromArray([
        'modePromptClasses' => [
            OutputMode::Json->value => 'App\\Prompts\\JsonPrompt',
            OutputMode::Tools->value => 'App\\Prompts\\ToolsPrompt',
        ],
        'retryPromptClass' => 'App\\Prompts\\RetryPrompt',
        'deserializationErrorPromptClass' => 'App\\Prompts\\RepairPrompt',
    ]);

    expect($config->modePromptClass(OutputMode::Json))->toBe('App\\Prompts\\JsonPrompt')
        ->and($config->modePromptClass(OutputMode::Tools))->toBe('App\\Prompts\\ToolsPrompt')
        ->and($config->retryPromptClass())->toBe('App\\Prompts\\RetryPrompt')
        ->and($config->deserializationErrorPromptClass())->toBe('App\\Prompts\\RepairPrompt')
        ->and($config->toArray()['modePromptClasses'][OutputMode::Json->value])->toBe('App\\Prompts\\JsonPrompt')
        ->and($config->toArray()['retryPromptClass'])->toBe('App\\Prompts\\RetryPrompt')
        ->and($config->toArray()['deserializationErrorPromptClass'])->toBe('App\\Prompts\\RepairPrompt');
});

it('ignores removed legacy inline prompt keys in fromArray()', function () {
    $legacy = StructuredOutputConfig::fromArray([
        'retryPromptClass' => 'App\\Prompts\\RetryPrompt',
        // removed in 2.6 — must be ignored, not fatal on the named-argument spread
        'retryPrompt' => 'LEGACY_RETRY',
        'modePrompts' => [OutputMode::Json->value => 'LEGACY INLINE PROMPT'],
        'chatStructure' => ['system', 'prompt', 'messages'],
        'someKeyThatNeverExisted' => true,
    ]);

    $clean = StructuredOutputConfig::fromArray([
        'retryPromptClass' => 'App\\Prompts\\RetryPrompt',
    ]);

    expect($legacy->toArray())->toBe($clean->toArray())
        ->and($legacy->retryPromptClass())->toBe('App\\Prompts\\RetryPrompt')
        ->and($legacy->toArray())->not->toHaveKeys(['retryPrompt', 'modePrompts', 'chatStructure']);
});

it('ignores removed legacy inline prompt keys in withOverrides()', function () {
    $config = (new StructuredOutputConfig())->withOverrides([
        'maxRetries' => 3,
        'retryPrompt' => 'LEGACY_RETRY',
        'chatStructure' => ['messages'],
    ]);

    expect($config->maxRetries())->toBe(3);
});
