<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Core\StructuredPromptRequestMaterializer;
use Cognesy\Instructor\Creation\StructuredOutputExecutionBuilder;
use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

describe('StructuredPromptRequestMaterializer', function () {
    function makeStructuredPromptMaterializerExecution(
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

    it('emits exactly one system message and keeps examples inside it', function () {
        $request = new StructuredOutputRequest(
            messages: Messages::fromString('Extract the user profile from the text.'),
            requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            examples: [
                Example::fromText('Jane is 31.', ['name' => 'Jane']),
            ],
        );

        $out = (new StructuredPromptRequestMaterializer())
            ->toMessages(makeStructuredPromptMaterializerExecution(request: $request))
            ->toArray();

        $system = array_values(array_filter($out, static fn(array $message): bool => ($message['role'] ?? '') === 'system'));
        $users = array_values(array_filter($out, static fn(array $message): bool => ($message['role'] ?? '') === 'user'));

        expect($system)->toHaveCount(1)
            ->and($system[0]['content'] ?? '')->toContain('## Examples')
            ->and($system[0]['content'] ?? '')->toContain('Jane is 31.')
            ->and($system[0]['content'] ?? '')->toContain('"type": "string"')
            ->and($system[0]['content'] ?? '')->not->toContain('<|json_schema|>')
            ->and($users)->toHaveCount(1)
            ->and($users[0]['content'] ?? '')->toBe('Extract the user profile from the text.');
    });

    it('preserves the live prompt when cached prompt also exists', function () {
        $request = new StructuredOutputRequest(
            messages: Messages::fromString('Extract the user profile from the text.'),
            requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            prompt: 'LIVE TASK',
            cachedContext: new CachedContext(prompt: 'CACHED TASK'),
        );

        $out = (new StructuredPromptRequestMaterializer())
            ->toMessages(makeStructuredPromptMaterializerExecution(request: $request))
            ->toArray();

        $systemMessage = array_values(array_filter($out, static fn(array $message): bool => ($message['role'] ?? '') === 'system'))[0] ?? [];
        $systemText = $systemMessage['content'] ?? '';

        expect($systemText)->toContain('LIVE TASK')
            ->and($systemText)->toContain('CACHED TASK');
    });

    it('does not leave arrowpipe placeholders in structured prompt messages', function () {
        $request = new StructuredOutputRequest(
            messages: Messages::fromString('Extract the user profile from the text.'),
            requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            prompt: 'LIVE TASK',
        );

        $out = (new StructuredPromptRequestMaterializer())
            ->toInferenceRequest(makeStructuredPromptMaterializerExecution(request: $request));

        expect($out->messages()->first()?->toString())->not->toContain('<|json_schema|>')
            ->and($out->messages()->first()?->toString())->toContain('"type": "object"');
    });

    it('renders retry feedback from the dedicated retry prompt class', function () {
        $execution = makeStructuredPromptMaterializerExecution()->withFailedAttempt(
            inferenceResponse: new InferenceResponse(content: '{"name": 1}'),
            errors: ['Field `name` must be a string.'],
        );

        $out = (new StructuredPromptRequestMaterializer())->toMessages($execution)->toArray();

        $userMessages = array_values(array_filter($out, static fn(array $message): bool => ($message['role'] ?? '') === 'user'));
        $assistantMessages = array_values(array_filter($out, static fn(array $message): bool => ($message['role'] ?? '') === 'assistant'));

        expect($assistantMessages)->toHaveCount(1)
            ->and($assistantMessages[0]['content'] ?? '')->toBe('{"name": 1}')
            ->and($userMessages)->toHaveCount(2)
            ->and($userMessages[1]['content'] ?? '')->toContain('Validation Errors')
            ->and($userMessages[1]['content'] ?? '')->toContain('Field `name` must be a string.');
    });
});
