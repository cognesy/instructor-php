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

it('keeps legacy inline prompt fields separate from prompt class references', function () {
    $config = (new StructuredOutputConfig())
        ->withRetryPrompt('LEGACY_RETRY')
        ->withRetryPromptClass('App\\Prompts\\RetryPrompt')
        ->withDeserializationErrorPromptClass('App\\Prompts\\RepairPrompt')
        ->withModePromptClass(OutputMode::Json, 'App\\Prompts\\JsonPrompt');

    expect($config->retryPrompt())->toBe('LEGACY_RETRY')
        ->and($config->retryPromptClass())->toBe('App\\Prompts\\RetryPrompt')
        ->and($config->deserializationErrorPromptClass())->toBe('App\\Prompts\\RepairPrompt')
        ->and($config->modePromptClass(OutputMode::Json))->toBe('App\\Prompts\\JsonPrompt')
        ->and($config->prompt(OutputMode::Json))->toBeString();
});
