<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ReAct;

use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ReAct\Actions\MakeReActPrompt;
use Cognesy\Addons\ToolUse\Drivers\ReAct\Actions\MakeToolCalls;
use Cognesy\Addons\ToolUse\Drivers\ReAct\Data\DecisionWithDetails;
use Cognesy\Addons\ToolUse\Drivers\ReAct\Data\ReActDecision;
use Cognesy\Addons\ToolUse\Drivers\ReAct\Utils\ReActFormatter;
use Cognesy\Addons\ToolUse\Drivers\ReAct\Utils\ReActValidator;
use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\PendingStructuredOutput;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Result\Result;

final class ReActDriver implements CanUseTools
{
    private string $model;
    private array $options;
    private bool $finalViaInference;
    private ?string $finalModel;
    private array $finalOptions;
    private CanCreateInference $inference;
    private CanCreateStructuredOutput $structuredOutput;

    public function __construct(
        CanCreateInference $inference,
        CanCreateStructuredOutput $structuredOutput,
        string $model = '',
        array $options = [],
        bool $finalViaInference = false,
        ?string $finalModel = null,
        array $finalOptions = [],
    ) {
        $this->inference = $inference;
        $this->structuredOutput = $structuredOutput;
        $this->model = $model;
        $this->options = $options;
        $this->finalViaInference = $finalViaInference;
        $this->finalModel = $finalModel;
        $this->finalOptions = $finalOptions;
    }

    #[\Override]
    public function useTools(ToolUseState $state, Tools $tools, CanExecuteToolCalls $executor): ToolUseStep {
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
        return new ToolUseStep(
            inputMessages: $messages,
            outputMessages: $outputMessages,
            usage: $usage,
            toolCalls: $toolCalls,
            toolExecutions: $executions,
            inferenceResponse: $inferenceResponse,
            stepType: ToolUseStepType::ToolExecution,
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
        $request = new StructuredOutputRequest(
            messages: $messages,
            requestedSchema: $decisionModel,
            system: $system,
            model: $this->model,
            options: $this->options,
        );

        return $this->structuredOutput->create($request);
    }

    /** Builds a failure step when decision fails validation. */
    private function buildValidationFailureStep(ValidationResult $validation, Messages $context): ToolUseStep {
        $formatter = new ReActFormatter();
        $error = new \RuntimeException($validation->getErrorMessage());
        $messagesErr = $formatter->decisionExtractionErrorMessages($error);
        $exec = new ToolExecution(
            new ToolCall('decision_validation', []),
            Result::failure($error),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $executions = (new ToolExecutions())->withAddedExecution($exec);
        return new ToolUseStep(
            inputMessages: $context,
            outputMessages: $messagesErr,
            usage: null,
            toolCalls: null,
            toolExecutions: $executions,
            inferenceResponse: null,
            stepType: ToolUseStepType::Error,
        );
    }

    /** Builds a failure step when decision extraction fails. */
    private function buildExtractionFailureStep(\Throwable $e, Messages $context): ToolUseStep {
        $formatter = new ReActFormatter();
        $messagesErr = $formatter->decisionExtractionErrorMessages($e);
        $exec = new ToolExecution(
            new ToolCall('decision_extraction', []),
            Result::failure($e),
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        );
        $executions = (new ToolExecutions())->withAddedExecution($exec);
        return new ToolUseStep(
            inputMessages: $context,
            outputMessages: $messagesErr,
            usage: null,
            toolCalls: null,
            toolExecutions: $executions,
            inferenceResponse: null,
            stepType: ToolUseStepType::Error,
        );
    }

    /** Builds a step for a final_answer decision (optionally finalizes via Inference). */
    private function buildFinalAnswerStep(
        ReActDecision $decision,
        ?Usage $usage,
        ?InferenceResponse $inferenceResponse,
        Messages $messages,
    ): ToolUseStep {
        $finalText = $decision->answer();
        if ($this->finalViaInference) {
            $pending = $this->finalizeAnswerViaInference($messages);
            $inferenceResponse = $pending->response();
            $finalText = $inferenceResponse->content();
            $usage = $inferenceResponse->usage();
        }
        return new ToolUseStep(
            inputMessages: $messages,
            outputMessages: Messages::empty()->appendMessage(Message::asAssistant($finalText)),
            usage: $usage,
            toolCalls: null,
            toolExecutions: null,
            inferenceResponse: $inferenceResponse,
            stepType: ToolUseStepType::FinalResponse,
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

        $request = new InferenceRequest(
            messages: $finalMessages,
            model: $this->finalModel ?: $this->model,
            options: $this->finalOptions ?: $this->options,
            mode: OutputMode::Text,
        );

        return $this->inference->create($request);
    }
}
