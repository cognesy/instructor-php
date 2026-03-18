<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\RequestMaterializer;
use Cognesy\Instructor\Core\StructuredPromptRequestMaterializer;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

describe('CanMaterializeRequest::toInferenceRequest', function () {
    function makeInferenceRequestExecution(
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

    it('legacy request materializer returns flattened inference requests without cached inference context', function () {
        $execution = makeInferenceRequestExecution(
            request: new StructuredOutputRequest(
                messages: Messages::fromString('Live conversation.'),
                requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                prompt: 'LIVE TASK',
                cachedContext: new CachedContext(prompt: 'CACHED TASK'),
            ),
        );

        $request = (new RequestMaterializer())->toInferenceRequest($execution);

        expect($request)->toBeInstanceOf(InferenceRequest::class)
            ->and($request->messages()->isEmpty())->toBeFalse()
            ->and($request->cachedContext()?->isEmpty())->toBeTrue();
    });

    it('structured prompt request materializer returns separated live and cached inference request sections', function () {
        $execution = makeInferenceRequestExecution(
            request: new StructuredOutputRequest(
                messages: Messages::fromArray([
                    ['role' => 'system', 'content' => 'Live head instructions.'],
                    ['role' => 'user', 'content' => 'Live conversation.'],
                ]),
                requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                system: 'Live system.',
                prompt: 'LIVE TASK',
                model: 'gpt-4o-mini',
                options: ['temperature' => 0.2],
                cachedContext: new CachedContext(
                    messages: Messages::fromArray([
                        ['role' => 'system', 'content' => 'Cached head instructions.'],
                        ['role' => 'user', 'content' => 'Cached conversation.'],
                    ]),
                    system: 'Cached system.',
                    prompt: 'CACHED TASK',
                ),
            ),
        );

        $request = (new StructuredPromptRequestMaterializer())->toInferenceRequest($execution);

        expect($request)->toBeInstanceOf(InferenceRequest::class)
            ->and($request->messages()->count())->toBe(2)
            ->and($request->messages()->first()?->role()->value)->toBe('system')
            ->and($request->messages()->first()?->toString())->toContain('LIVE TASK')
            ->and($request->messages()->first()?->toString())->not->toContain('CACHED TASK')
            ->and($request->messages()->last()?->toString())->toBe('Live conversation.')
            ->and($request->cachedContext())->not->toBeNull()
            ->and($request->cachedContext()?->messages()->count())->toBe(2)
            ->and($request->cachedContext()?->messages()->first()?->role()->value)->toBe('system')
            ->and($request->cachedContext()?->messages()->first()?->toString())->toContain('CACHED TASK')
            ->and($request->cachedContext()?->messages()->first()?->toString())->not->toContain('LIVE TASK')
            ->and($request->cachedContext()?->messages()->last()?->toString())->toBe('Cached conversation.')
            ->and($request->model())->toBe('gpt-4o-mini')
            ->and($request->options())->toBe(['temperature' => 0.2]);
    });
});
