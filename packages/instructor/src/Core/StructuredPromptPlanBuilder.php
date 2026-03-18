<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Data\StructuredPromptPlan;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Extras\Example\Example;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Arrays;
use Cognesy\Xprompt\Prompt;
use InvalidArgumentException;

final class StructuredPromptPlanBuilder
{
    public function build(StructuredOutputExecution $execution): StructuredPromptPlan
    {
        $request = $execution->request();
        $responseModel = $execution->responseModel();
        $this->assertSupportedOutputMode($execution->outputMode());

        if ($this->isRequestEmpty($request)) {
            throw new InvalidArgumentException('Request cannot be empty - you have to provide content for processing.');
        }

        assert($responseModel instanceof ResponseModel, 'Response model cannot be null.');

        return new StructuredPromptPlan(
            liveSystemPrompt: $this->renderSystemPrompt(
                execution: $execution,
                system: $request->system(),
                task: $request->prompt(),
                examples: $request->examples(),
                messages: $request->messages(),
                responseModel: $responseModel,
            ),
            liveConversation: $this->conversationMessages($request->messages()),
            retryMessages: $this->makeRetryMessages($execution),
            cachedSystemPrompt: $this->renderCachedSystemPrompt(
                execution: $execution,
                cachedContext: $request->cachedContext(),
                responseModel: $responseModel,
            ),
            cachedConversation: $this->conversationMessages($request->cachedContext()->messages()),
        );
    }

    private function renderCachedSystemPrompt(
        StructuredOutputExecution $execution,
        CachedContext $cachedContext,
        ResponseModel $responseModel,
    ): string {
        if ($cachedContext->isEmpty()) {
            return '';
        }

        $headMessages = $cachedContext->messages()->headWithRoles(['system', 'developer']);
        if (
            $cachedContext->system() === ''
            && $cachedContext->prompt() === ''
            && empty($cachedContext->examples())
            && $headMessages->isEmpty()
        ) {
            return '';
        }

        return $this->renderSystemPrompt(
            execution: $execution,
            system: $cachedContext->system(),
            task: $cachedContext->prompt(),
            examples: $cachedContext->examples(),
            messages: $cachedContext->messages(),
            responseModel: $responseModel,
        );
    }

    private function renderSystemPrompt(
        StructuredOutputExecution $execution,
        string $system,
        string $task,
        array $examples,
        Messages $messages,
        ResponseModel $responseModel,
    ): string {
        $systemPromptClass = $execution->config()->modePromptClass($execution->outputMode());
        $context = [
            'system' => $this->mergeInstructionText(
                $system,
                $this->instructionMessagesToMarkdown($messages->headWithRoles(['system', 'developer'])),
            ),
            'task' => $task,
            'examples_markdown' => $this->examplesToMarkdown($examples),
            'json_schema' => $this->encodeJson($responseModel->jsonSchema() ?? []),
            'schema_name' => $responseModel->schemaName(),
            'schema_description' => $responseModel->schemaDescription(),
            'tool_name' => $responseModel->toolName(),
            'tool_description' => $responseModel->toolDescription(),
        ];

        return trim($this->renderPromptClass($systemPromptClass, $context));
    }

    private function renderPromptClass(string $promptClass, array $context): string
    {
        if ($promptClass === '') {
            throw new InvalidArgumentException('Structured prompt materializer requires a prompt class for the selected output mode.');
        }

        if (!class_exists($promptClass)) {
            throw new InvalidArgumentException("Prompt class does not exist: {$promptClass}");
        }

        if (!is_a($promptClass, Prompt::class, true)) {
            throw new InvalidArgumentException("Prompt class must extend " . Prompt::class . ": {$promptClass}");
        }

        return $promptClass::with(...$context)->render();
    }

    private function instructionMessagesToMarkdown(Messages $messages): string
    {
        $sections = [];

        foreach ($messages as $message) {
            $sections[] = match ($message->role()->value) {
                'developer' => "## Developer Instructions\n\n" . trim($message->toString()),
                'system' => "## System Instructions\n\n" . trim($message->toString()),
                default => trim($message->toString()),
            };
        }

        return implode("\n\n", array_values(array_filter($sections, static fn(string $section): bool => $section !== '')));
    }

    private function mergeInstructionText(string ...$parts): string
    {
        return implode("\n\n", array_values(array_filter(
            array_map(static fn(string $part): string => trim($part), $parts),
            static fn(string $part): bool => $part !== '',
        )));
    }

    private function examplesToMarkdown(array $examples): string
    {
        $rendered = [];

        foreach ($examples as $example) {
            $normalized = $this->normalizeExample($example);
            if ($normalized === null) {
                continue;
            }
            $rendered[] = trim($normalized->toString());
        }

        return implode("\n\n", $rendered);
    }

    private function normalizeExample(mixed $example): ?Example
    {
        return match (true) {
            $example instanceof Example => $example,
            is_array($example) => Example::fromArray($example),
            is_string($example) && $example !== '' => Example::fromJson($example),
            default => null,
        };
    }

    private function conversationMessages(Messages $messages): Messages
    {
        return $messages->tailAfterRoles(['developer', 'system']);
    }

    private function makeRetryMessages(StructuredOutputExecution $execution): Messages
    {
        if ($execution->attempts()->isEmpty()) {
            return Messages::empty();
        }

        $messages = Messages::empty();
        foreach ($execution->attempts() as $attempt) {
            $messages = $messages->appendMessages($this->retryMessagesForAttempt($execution, $attempt));
        }

        return $messages;
    }

    private function retryMessagesForAttempt(
        StructuredOutputExecution $execution,
        StructuredOutputAttempt $attempt,
    ): Messages {
        $messages = Messages::empty();
        $response = $attempt->inferenceResponse();

        if ($response !== null && $response->content() !== '') {
            $messages = $messages->appendMessage(Message::asAssistant($response->content()));
        }

        $retryPromptClass = $execution->config()->retryPromptClass();
        $retryFeedback = $this->renderPromptClass($retryPromptClass, [
            'errors' => Arrays::flattenToString($attempt->errors(), '; '),
        ]);

        if ($retryFeedback !== '') {
            $messages = $messages->appendMessage(Message::asUser(trim($retryFeedback)));
        }

        return $messages;
    }

    private function encodeJson(array $jsonSchema): string
    {
        $encoded = json_encode($jsonSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded !== false ? $encoded : '{}';
    }

    private function isRequestEmpty(StructuredOutputRequest $request): bool
    {
        return match (true) {
            !$request->messages()->isEmpty() => false,
            $request->prompt() !== '' => false,
            $request->system() !== '' => false,
            !empty($request->examples()) => false,
            default => true,
        };
    }

    private function assertSupportedOutputMode(OutputMode $outputMode): void
    {
        if ($outputMode->isIn([OutputMode::Text, OutputMode::Unrestricted])) {
            throw new InvalidArgumentException("Structured prompt materializer does not support output mode: {$outputMode->value}");
        }
    }
}
