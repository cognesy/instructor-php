<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;

it('can set prompt class references directly', function () {
    $config = (new StructuredOutputConfig())
        ->withModePromptClass(OutputMode::Json, 'App\\Prompts\\JsonPrompt')
        ->withRetryPromptClass('App\\Prompts\\RetryPrompt')
        ->withDeserializationErrorPromptClass('App\\Prompts\\RepairPrompt');

    expect($config->modePromptClass(OutputMode::Json))->toBe('App\\Prompts\\JsonPrompt')
        ->and($config->retryPromptClass())->toBe('App\\Prompts\\RetryPrompt')
        ->and($config->deserializationErrorPromptClass())->toBe('App\\Prompts\\RepairPrompt');
});

it('merges prompt class overrides with defaults from an explicit config', function () {
    $defaults = StructuredOutputConfig::fromArray([
        'modePromptClasses' => [
            OutputMode::Tools->value => 'App\\Prompts\\DefaultToolsPrompt',
        ],
        'retryPromptClass' => 'App\\Prompts\\DefaultRetryPrompt',
        'deserializationErrorPromptClass' => 'App\\Prompts\\DefaultRepairPrompt',
    ]);

    $config = $defaults->withModePromptClass(OutputMode::Json, 'App\\Prompts\\JsonPrompt');

    expect($config->modePromptClass(OutputMode::Tools))->toBe('App\\Prompts\\DefaultToolsPrompt')
        ->and($config->modePromptClass(OutputMode::Json))->toBe('App\\Prompts\\JsonPrompt')
        ->and($config->retryPromptClass())->toBe('App\\Prompts\\DefaultRetryPrompt')
        ->and($config->deserializationErrorPromptClass())->toBe('App\\Prompts\\DefaultRepairPrompt');
});

/**
 * withModePromptClass() merges a single mode into the existing map; withModePromptClasses()
 * replaces the map wholesale. Both are reachable from the same object, so the difference is
 * pinned here rather than left to the reader.
 */
it('replaces the whole map through withModePromptClasses but merges through withModePromptClass', function () {
    $defaults = new StructuredOutputConfig();

    $merged = $defaults->withModePromptClass(OutputMode::Json, 'App\\Prompts\\JsonPrompt');
    $replaced = $defaults->withModePromptClasses([
        OutputMode::Json->value => 'App\\Prompts\\JsonPrompt',
    ]);

    expect($merged->modePromptClass(OutputMode::Tools))->toBe($defaults->modePromptClass(OutputMode::Tools))
        ->and($merged->modePromptClass(OutputMode::Tools))->not->toBe('')
        ->and($replaced->modePromptClasses())->toBe([OutputMode::Json->value => 'App\\Prompts\\JsonPrompt'])
        ->and($replaced->modePromptClass(OutputMode::Tools))->toBe('');
});
