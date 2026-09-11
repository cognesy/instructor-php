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
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\ToolCall;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Xprompt\Prompt;

final class StructuredPromptMaterializerCustomPrompt extends Prompt
{
    public function body(mixed ...$ctx): string
    {
        return 'CUSTOM PROMPT CLASS: ' . ($ctx['json_schema'] ?? '');
    }
}

final class StructuredPromptMaterializerCustomRetryPrompt extends Prompt
{
    public function body(mixed ...$ctx): string
    {
        return 'CUSTOM RETRY: ' . ($ctx['errors'] ?? '');
    }
}

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
            ->toMessages(makeStructuredPromptMaterializerExecution(request: $request));

        $system = array_values(array_filter($out->all(), static fn(Message $message): bool => $message->isSystem()));
        $users = array_values(array_filter($out->all(), static fn(Message $message): bool => $message->isUser()));

        expect($system)->toHaveCount(1)
            ->and($system[0]->content()->toString())->toContain('## Examples')
            ->and($system[0]->content()->toString())->toContain('Jane is 31.')
            ->and($system[0]->content()->toString())->toContain('"type": "string"')
            ->and($system[0]->content()->toString())->not->toContain('<|json_schema|>')
            ->and($users)->toHaveCount(1)
            ->and($users[0]->content()->toString())->toBe('Extract the user profile from the text.');
    });

    it('preserves the live prompt when cached prompt also exists', function () {
        $request = new StructuredOutputRequest(
            messages: Messages::fromString('Extract the user profile from the text.'),
            requestedSchema: ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            prompt: 'LIVE TASK',
            cachedContext: new CachedContext(prompt: 'CACHED TASK'),
        );

        $out = (new StructuredPromptRequestMaterializer())
            ->toMessages(makeStructuredPromptMaterializerExecution(request: $request));

        $systemMessage = array_values(array_filter(
            $out->all(),
            static fn(Message $message): bool => $message->isSystem(),
        ))[0] ?? null;
        $systemText = $systemMessage?->content()->toString() ?? '';

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
            inferenceResponse: new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('{"name": 1}')),
            errors: ['Field `name` must be a string.'],
        );

        $out = (new StructuredPromptRequestMaterializer())->toMessages($execution);

        $userMessages = array_values(array_filter($out->all(), static fn(Message $message): bool => $message->isUser()));
        $assistantMessages = array_values(array_filter($out->all(), static fn(Message $message): bool => $message->isAssistant()));

        expect($assistantMessages)->toHaveCount(1)
            ->and($assistantMessages[0]->content()->toString())->toBe('{"name": 1}')
            ->and($userMessages)->toHaveCount(2)
            ->and($userMessages[1]->content()->toString())->toContain('Validation Errors')
            ->and($userMessages[1]->content()->toString())->toContain('Field `name` must be a string.');
    });

    it('pairs a rejected structured-output tool call with an error result', function () {
        $assistant = new Message(
            role: 'assistant',
            parts: new ContentParts(ContentPart::toolCall(new ToolCall(
                name: 'UserProfile',
                arguments: ['name' => 1],
                id: 'call_validation',
            ))),
        );
        $execution = makeStructuredPromptMaterializerExecution()->withFailedAttempt(
            inferenceResponse: new InferenceResponse(message: $assistant),
            errors: ['Field `name` must be a string.'],
        );

        $messages = (new StructuredPromptRequestMaterializer())->toMessages($execution)->all();
        $assistantMessages = array_values(array_filter(
            $messages,
            static fn(Message $message): bool => $message->hasToolCalls(),
        ));
        $toolMessages = array_values(array_filter(
            $messages,
            static fn(Message $message): bool => $message->hasToolResult(),
        ));
        $retryUsers = array_values(array_filter(
            $messages,
            static fn(Message $message): bool => $message->isUser()
                && str_contains($message->content()->toString(), 'Validation Errors'),
        ));

        expect($assistantMessages)->toHaveCount(1)
            ->and($assistantMessages[0]->toolCalls()->first()?->idString())->toBe('call_validation')
            ->and($toolMessages)->toHaveCount(1)
            ->and($toolMessages[0]->toolResult()?->callIdString())->toBe('call_validation')
            ->and($toolMessages[0]->toolResult()?->toolName())->toBe('UserProfile')
            ->and($toolMessages[0]->toolResult()?->isError())->toBeTrue()
            ->and($toolMessages[0]->toolResult()?->content())->toContain('Field `name` must be a string.')
            ->and($retryUsers)->toBe([]);
    });

    it('uses the configured retry prompt class', function () {
        $config = (new StructuredOutputConfig(outputMode: OutputMode::Json))
            ->withRetryPromptClass(StructuredPromptMaterializerCustomRetryPrompt::class);
        $execution = makeStructuredPromptMaterializerExecution(config: $config)->withFailedAttempt(
            inferenceResponse: new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('{"name": 1}')),
            errors: ['Field `name` must be a string.'],
        );

        $out = (new StructuredPromptRequestMaterializer())->toMessages($execution);
        $userMessages = array_values(array_filter(
            $out->all(),
            static fn(Message $message): bool => $message->isUser(),
        ));

        expect($userMessages)->toHaveCount(2)
            ->and($userMessages[1]->content()->toString())->toContain('CUSTOM RETRY:')
            ->and($userMessages[1]->content()->toString())->toContain('Field `name` must be a string.');
    });

    it('uses a configured prompt class as the supported customization seam', function () {
        $config = (new StructuredOutputConfig(outputMode: OutputMode::Json))
            ->withModePromptClass(OutputMode::Json, StructuredPromptMaterializerCustomPrompt::class);

        $out = (new StructuredPromptRequestMaterializer())
            ->toMessages(makeStructuredPromptMaterializerExecution(config: $config));

        expect($out->first()?->content()->toString())->toContain('CUSTOM PROMPT CLASS:')
            ->and($out->first()?->content()->toString())->toContain('"type": "object"');
    });
});
