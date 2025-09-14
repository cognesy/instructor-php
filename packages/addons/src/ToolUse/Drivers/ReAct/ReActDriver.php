<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Enums\StepType;
use Cognesy\Addons\ToolUse\Tools;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;

final class ReActDriver implements CanUseTools
{
    private LLMProvider $llm;
    private ?HttpClient $httpClient = null;
    private string $model;
    private array $options;
    private bool $finalViaInference;
    private ?string $finalModel;
    private array $finalOptions;
    private int $maxRetries;
    private OutputMode $mode;

    public function __construct(
        ?LLMProvider $llm = null,
        ?HttpClient $httpClient = null,
        string $model = '',
        array $options = [],
        bool $finalViaInference = false,
        ?string $finalModel = null,
        array $finalOptions = [],
        int $maxRetries = 2,
        OutputMode $mode = OutputMode::Json,
    ) {
        $this->llm = $llm ?? LLMProvider::new();
        $this->httpClient = $httpClient;
        $this->model = $model;
        $this->options = $options;
        $this->finalViaInference = $finalViaInference;
        $this->finalModel = $finalModel;
        $this->finalOptions = $finalOptions;
        $this->maxRetries = $maxRetries;
        $this->mode = $mode;
    }

    public function useTools(ToolUseState $state, Tools $tools) : ToolUseStep {
        $messages = $state->messages();
        $system = ReActPrompt::buildSystemPrompt($tools);

        $extraction = Result::try(fn () => $this->extractDecisionWithUsage($messages, $system));
        if ($extraction->isFailure()) {
            return $this->buildExtractionFailureStep($extraction->error());
        }

        /** @var PendingStructuredOutput $pendingDecision */
        $pendingDecision = $extraction->unwrap();
        /**
         * NOTE/TODO (design choice):
         * Currently we call `$pendingDecision->get()` directly. If StructuredOutput fails
         * to deserialize/validate the model (e.g., malformed or non-JSON content), it throws
         * a ValidationException after configured retries. That exception is allowed to bubble
         * up to the caller. An alternative behavior would be to treat such extraction failures
         * as part of the ReAct control flow, convert them into a ToolExecution-style observation,
         * and continue (or stop) deterministically. We already have `buildExtractionFailureStep()`
         * for failures occurring during `extractDecisionWithUsage()`, but failures raised by
         * `$pendingDecision->get()` happen later in the flow. If we decide to change semantics,
         * we can wrap the line below in a try/catch and map the exception to
         * `buildExtractionFailureStep($e)` for better resilience and observability.
         * For now, we preserve strict failure semantics to surface invalid decision extraction
         * to the caller explicitly.
         */
        /** @var ReActDecision $decision */
        $decision = $pendingDecision->get();
        $inferenceResponse = $pendingDecision->response();
        $usage = $inferenceResponse->usage();

        return match (true) {
            $decision->isCall() => $this->buildToolCallStep($decision, $usage, $inferenceResponse, $state, $tools),
            default => $this->buildFinalAnswerStep($decision, $usage, $inferenceResponse, $state, $messages),
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    /**
     * Extracts a ReAct decision via StructuredOutput and returns usage data.
     */
    private function extractDecisionWithUsage(Messages $messages, string $system) : PendingStructuredOutput {
        $structured = (new StructuredOutput())
            ->withSystem($system)
            ->withMessages($messages)
            ->withResponseClass(ReActDecision::class)
            ->withOutputMode($this->mode)
            ->withModel($this->model)
            ->withOptions($this->options)
            ->withMaxRetries($this->maxRetries)
            ->withLLMProvider($this->llm);
        if ($this->httpClient !== null) {
            $structured = $structured->withHttpClient($this->httpClient);
        }
        return $structured->create();
    }

    /** Builds a failure step when decision extraction fails. */
    private function buildExtractionFailureStep(\Throwable $e) : ToolUseStep {
        $formatter = new ReActFormatter();
        $messagesErr = $formatter->decisionExtractionErrorMessages($e);
        $exec = new ToolExecution(
            new ToolCall('decision_extraction', []),
            Result::failure($e),
            new \DateTimeImmutable(),
            new \DateTimeImmutable()
        );
        $executions = (new ToolExecutions())->add($exec);
        return new ToolUseStep(
            response: '',
            toolCalls: null,
            toolExecutions: $executions,
            messages: $messagesErr,
            usage: null,
            inferenceResponse: null,
            stepType: StepType::Error,
        );
    }

    /** Builds a step for a call_tool decision (executes the tool and formats follow-ups). */
    private function buildToolCallStep(
        ReActDecision $decision,
        ?Usage $usage,
        ?InferenceResponse $inferenceResponse,
        ToolUseState $state,
        Tools $tools
    ) : ToolUseStep {
        $call = new ToolCall($decision->tool() ?? '', $decision->args());
        $execution = $tools->useTool($call, $state);
        $executions = (new ToolExecutions())->add($execution);

        $formatter = new ReActFormatter();
        $followUps = Messages::empty()
            ->appendMessage($formatter->assistantThoughtActionMessage($decision))
            ->appendMessage($formatter->observationMessage($execution));

        return new ToolUseStep(
            response: '',
            toolCalls: new ToolCalls([$call]),
            toolExecutions: $executions,
            messages: $followUps,
            usage: $usage,
            inferenceResponse: $inferenceResponse,
            stepType: StepType::ToolExecution,
        );
    }

    /** Builds a step for a final_answer decision (optionally finalizes via Inference). */
    private function buildFinalAnswerStep(
        ReActDecision $decision,
        ?Usage $usage,
        ?InferenceResponse $inferenceResponse,
        ToolUseState $state,
        Messages $messages
    ) : ToolUseStep {
        $finalText = $decision->answer();
        if ($this->finalViaInference) {
            $pending = $this->finalizeAnswerViaInference($messages);
            $inferenceResponse = $pending->response();
            $finalText = $inferenceResponse->content();
            $usage = $inferenceResponse->usage();
        }
        return new ToolUseStep(
            response: $finalText,
            toolCalls: null,
            toolExecutions: null,
            messages: Messages::empty(),
            usage: $usage,
            inferenceResponse: $inferenceResponse,
            stepType: StepType::FinalResponse,
        );
    }

    /** Generates a plain-text final answer via Inference and returns PendingInference. */
    private function finalizeAnswerViaInference(Messages $messages) : PendingInference {
        $finalMessages = Messages::fromArray([
            ['role' => 'system', 'content' => 'Return only the final answer as plain text.'],
            ...$messages->toArray(),
        ]);
        $inference = (new Inference)
            ->withLLMProvider($this->llm)
            ->withMessages($finalMessages->toArray())
            ->withModel($this->finalModel ?: $this->model)
            ->withOptions($this->finalOptions ?: $this->options)
            ->withOutputMode(OutputMode::Text);
        if ($this->httpClient !== null) { $inference = $inference->withHttpClient($this->httpClient); }
        return $inference->create();
    }
}
