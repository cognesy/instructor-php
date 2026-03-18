<?php declare(strict_types=1);

use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Enums\OutputMode;

it('builder can set prompt class references directly', function () {
    $config = (new StructuredOutputConfigBuilder())
        ->withModePromptClass(OutputMode::Json, 'App\\Prompts\\JsonPrompt')
        ->withRetryPromptClass('App\\Prompts\\RetryPrompt')
        ->withDeserializationErrorPromptClass('App\\Prompts\\RepairPrompt')
        ->create();

    expect($config->modePromptClass(OutputMode::Json))->toBe('App\\Prompts\\JsonPrompt')
        ->and($config->retryPromptClass())->toBe('App\\Prompts\\RetryPrompt')
        ->and($config->deserializationErrorPromptClass())->toBe('App\\Prompts\\RepairPrompt');
});

it('builder merges prompt class overrides with defaults from explicit config', function () {
    $defaults = StructuredOutputConfig::fromArray([
        'modePromptClasses' => [
            OutputMode::Tools->value => 'App\\Prompts\\DefaultToolsPrompt',
        ],
        'retryPromptClass' => 'App\\Prompts\\DefaultRetryPrompt',
        'deserializationErrorPromptClass' => 'App\\Prompts\\DefaultRepairPrompt',
    ]);

    $config = (new StructuredOutputConfigBuilder())
        ->withConfig($defaults)
        ->withModePromptClass(OutputMode::Json, 'App\\Prompts\\JsonPrompt')
        ->create();

    expect($config->modePromptClass(OutputMode::Tools))->toBe('App\\Prompts\\DefaultToolsPrompt')
        ->and($config->modePromptClass(OutputMode::Json))->toBe('App\\Prompts\\JsonPrompt')
        ->and($config->retryPromptClass())->toBe('App\\Prompts\\DefaultRetryPrompt')
        ->and($config->deserializationErrorPromptClass())->toBe('App\\Prompts\\DefaultRepairPrompt');
});
