<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\StructuredPromptPlanBuilder;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

describe('StructuredPromptPlanBuilder', function () {
    function makeStructuredPromptExecution(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
    ): StructuredOutputExecution {
        return (new StructuredOutputExecutionBuilder(new EventDispatcher()))->createWith(
            request: $request ?? new StructuredOutputRequest(
                messages: Messages::fromString('Extract the user profile from the text.'),
                requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            ),
            config: $config ?? new StructuredOutputConfig(outputMode: OutputMode::Json),
        );
    }

    it('keeps cached and live system prompts separate while preserving live prompt', function () {
        $request = new StructuredOutputRequest(
            messages: Messages::fromArray([
                ['role' => 'system', 'content' => 'Honor company policy.'],
                ['role' => 'user', 'content' => 'Extract the user profile from the text.'],
            ]),
            requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            system: 'You are a precise extraction assistant.',
            prompt: 'LIVE TASK',
            cachedContext: new CachedContext(
                system: 'Cached policy.',
                prompt: 'CACHED TASK',
            ),
        );

        $plan = (new StructuredPromptPlanBuilder())->build(makeStructuredPromptExecution(request: $request));

        expect($plan->liveSystemPrompt())->toContain('LIVE TASK')
            ->and($plan->liveSystemPrompt())->toContain('Honor company policy.')
            ->and($plan->cachedSystemPrompt())->toContain('CACHED TASK')
            ->and($plan->cachedSystemPrompt())->toContain('Cached policy.')
            ->and($plan->liveConversation()->count())->toBe(1)
            ->and($plan->liveConversation()->first()?->toString())->toBe('Extract the user profile from the text.')
            ->and($plan->cachedConversation()->isEmpty())->toBeTrue();
    });

    it('renders examples into markdown inside the live system prompt', function () {
        $request = new StructuredOutputRequest(
            messages: Messages::fromString('Extract the user profile from the text.'),
            requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            examples: [
                Example::fromText('Jane is 31.', ['name' => 'Jane']),
            ],
        );

        $plan = (new StructuredPromptPlanBuilder())->build(makeStructuredPromptExecution(request: $request));

        expect($plan->liveSystemPrompt())->toContain('## Examples')
            ->and($plan->liveSystemPrompt())->toContain('EXAMPLE INPUT:')
            ->and($plan->liveSystemPrompt())->toContain('Jane is 31.')
            ->and($plan->liveSystemPrompt())->toContain('```json');
    });

    it('keeps retry turns in live messages only', function () {
        $execution = makeStructuredPromptExecution()->withFailedAttempt(
            inferenceResponse: new InferenceResponse(content: '{"name": 1}'),
            errors: ['Field `name` must be a string.'],
        );

        $plan = (new StructuredPromptPlanBuilder())->build($execution);

        expect($plan->retryMessages()->count())->toBe(2)
            ->and($plan->retryMessages()->first()?->role()->value)->toBe('assistant')
            ->and($plan->retryMessages()->last()?->role()->value)->toBe('user')
            ->and($plan->retryMessages()->last()?->toString())->toContain('Validation Errors')
            ->and($plan->cachedConversation()->isEmpty())->toBeTrue();
    });
});
