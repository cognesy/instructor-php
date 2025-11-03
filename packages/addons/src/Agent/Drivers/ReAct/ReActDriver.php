<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Drivers\ReAct;

use Cognesy\Addons\Agent\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\Agent\Contracts\CanUseTools;
use Cognesy\Addons\Agent\Data\AgentExecution;
use Cognesy\Addons\Agent\Data\AgentState;
use Cognesy\Addons\Agent\Data\AgentStep;
use Cognesy\Addons\Agent\Drivers\ReAct\Actions\MakeReActPrompt;
use Cognesy\Addons\Agent\Drivers\ReAct\Actions\MakeToolCalls;
use Cognesy\Addons\Agent\Drivers\ReAct\Data\DecisionWithDetails;
use Cognesy\Addons\Agent\Drivers\ReAct\Data\ReActDecision;
use Cognesy\Addons\Agent\Drivers\ReAct\Utils\ReActFormatter;
use Cognesy\Addons\Agent\Drivers\ReAct\Utils\ReActValidator;
use Cognesy\Addons\Agent\Enums\AgentStepType;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
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

    #[\Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep {
        $messages = $state->messages();
        $system = $this->buildSystemPrompt($tools);
        $extraction = Result::try(fn() => $this->extractDecision($messages, $system));
        if ($extraction->isFailure()) {
            return $this->buildExtractionFailureStep($extraction->error(), $messages);
        }

        $bundle = $extraction->unwrap();
        $decision = $bundle->decision();

        // Basic validation: decision type + tool exists
        $validator = new ReActValidator();
        $validation = $validator->validateBasicDecision($decision, $tools->names());
        if ($validation->isInvalid()) {
            return $this->buildValidationFailureStep($validation, $messages);
        }

        // Extract tool calls independent of execution
        $toolCalls = (new MakeToolCalls($tools, $validator))($decision);

        $inferenceResponse = $bundle->response();
        $usage = $inferenceResponse->usage();

        if (!$decision->isCall()) {
            return $this->buildFinalAnswerStep($decision, $usage, $inferenceResponse, $messages);
        }

        // Execute tool calls and assemble follow-ups
        $executions = $executor->useTools($toolCalls, $state);
        $outputMessages = $this->makeFollowUps($decision, $executions);
        return new AgentStep(
            inputMessages: $messages,
            outputMessages: $outputMessages,
            usage: $usage,
            toolCalls: $toolCalls,
            toolExecutions: $executions,
            inferenceResponse: $inferenceResponse,
            stepType: AgentStepType::ToolExecution,
        );
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function buildSystemPrompt(Tools $tools): string {
        return (new MakeReActPrompt($tools))();
    }

    private function extractDecision(Messages $messages, string $system): DecisionWithDetails {
        $pending = $this->extractDecisionWithUsage(
            messages: $messages,
            system: $system,
            decisionModel: ReActDecision::class,
        );
        /** @var ReActDecision $decision */
        $decision = $pending->get();
        return new DecisionWithDetails(
            decision: $decision,
            response: $pending->response(),
        );
    }

    /**
     * Extracts a ReAct decision via StructuredOutput and returns usage data.
     *
     * @param class-string|array|object $decisionModel
     */
    private function extractDecisionWithUsage(Messages $messages, string $system, string|array|object $decisionModel): PendingStructuredOutput {
        $structured = (new StructuredOutput())
            ->withSystem($system)
            ->withMessages($messages)
            ->withResponseModel($decisionModel)
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

    /** Builds a failure step when decision fails validation. */
    private function buildValidationFailureStep(ValidationResult $validation, Messages $context): AgentStep {
        $formatter = new ReActFormatter();
        $error = new \RuntimeException($validation->getErrorMessage());
        $messagesErr = $formatter->decisionExtractionErrorMessages($error);
        $exec = new AgentExecution(
            new ToolCall('decision_validation', []),
            Result::failure($error),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $executions = (new ToolExecutions())->withAddedExecution($exec);
        return new AgentStep(
            inputMessages: $context,
            outputMessages: $messagesErr,
            usage: null,
            toolCalls: null,
            toolExecutions: $executions,
            inferenceResponse: null,
            stepType: AgentStepType::Error,
        );
    }

    /** Builds a failure step when decision extraction fails. */
    private function buildExtractionFailureStep(\Throwable $e, Messages $context): AgentStep {
        $formatter = new ReActFormatter();
        $messagesErr = $formatter->decisionExtractionErrorMessages($e);
        $exec = new AgentExecution(
            new ToolCall('decision_extraction', []),
            Result::failure($e),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $executions = (new ToolExecutions())->withAddedExecution($exec);
        return new AgentStep(
            inputMessages: $context,
            outputMessages: $messagesErr,
            usage: null,
            toolCalls: null,
            toolExecutions: $executions,
            inferenceResponse: null,
            stepType: AgentStepType::Error,
        );
    }

    /** Builds a step for a final_answer decision (optionally finalizes via Inference). */
    private function buildFinalAnswerStep(
        ReActDecision $decision,
        ?Usage $usage,
        ?InferenceResponse $inferenceResponse,
        Messages $messages,
    ): AgentStep {
        $finalText = $decision->answer();
        if ($this->finalViaInference) {
            $pending = $this->finalizeAnswerViaInference($messages);
            $inferenceResponse = $pending->response();
            $finalText = $inferenceResponse->content();
            $usage = $inferenceResponse->usage();
        }
        return new AgentStep(
            inputMessages: $messages,
            outputMessages: Messages::empty()->appendMessage(Message::asAssistant($finalText)),
            usage: $usage,
            toolCalls: null,
            toolExecutions: null,
            inferenceResponse: $inferenceResponse,
            stepType: AgentStepType::FinalResponse,
        );
    }

    /** Creates follow-up messages including assistant Thought/Action and observations. */
    private function makeFollowUps(ReActDecision $decision, ToolExecutions $executions): Messages {
        $formatter = new ReActFormatter();
        $messages = Messages::empty()->appendMessage($formatter->assistantThoughtActionMessage($decision));
        foreach ($executions->all() as $execution) {
            $messages = $messages->appendMessage($formatter->observationMessage($execution));
        }
        return $messages;
    }

    /** Generates a plain-text final answer via Inference and returns PendingInference. */
    private function finalizeAnswerViaInference(Messages $messages): PendingInference {
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
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        return $inference->create();
    }
}
