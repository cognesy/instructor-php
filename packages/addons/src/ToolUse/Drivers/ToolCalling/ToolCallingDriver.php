<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ToolCalling;

use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\PendingInference;

/**
 * ToolCallingDriver is responsible for managing the interaction between a
 * language model (LLM) and various tools. It defines the workflow for
 * generating a response based on input messages, selecting and invoking tools,
 * handling tool execution results, and crafting follow-up messages.
 */
class ToolCallingDriver implements CanUseTools
{
    private string|array $toolChoice;
    private string $model;
    private array $responseFormat;
    private OutputMode $mode;
    private array $options;
    private ?InferenceRetryPolicy $retryPolicy;
    private bool $parallelToolCalls = false;
    private ToolExecutionFormatter $formatter;
    private CanCreateInference $inference;

    public function __construct(
        CanCreateInference $inference,
        string|array $toolChoice = 'auto',
        array        $responseFormat = [],
        string       $model = '',
        array        $options = [],
        OutputMode   $mode = OutputMode::Tools,
        ?InferenceRetryPolicy $retryPolicy = null,
    ) {
        $this->inference = $inference;
        $this->toolChoice = $toolChoice;
        $this->model = $model;
        $this->responseFormat = $responseFormat;
        $this->mode = $mode;
        $this->options = $options;
        $this->retryPolicy = $retryPolicy;
        $this->formatter = new ToolExecutionFormatter();
    }

    /**
     * Executes tool usage within a given context and returns the result as a ToolUseStep.
     *
     * @param ToolUseState $state The context containing messages, tools, and other related information required for tool usage.
     * @return ToolUseStep Returns an instance of ToolUseStep containing the response, executed tools, follow-up messages, and additional usage data.
     */
    #[\Override]
    public function useTools(ToolUseState $state, Tools $tools, CanExecuteToolCalls $executor) : ToolUseStep {
        $response = $this->getToolCallResponse($state, $tools);
        $toolCalls = $this->getToolsToCall($response);
        $executions = $executor->useTools($toolCalls, $state);
        $messages = $this->formatter->makeExecutionMessages($executions);
        return $this->buildStepFromResponse(
            response: $response,
            executions: $executions,
            followUps: $messages,
            context: $state->messages(),
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getToolCallResponse(ToolUseState $state, Tools $tools) : InferenceResponse {
        return $this->buildPendingInference($state->messages(), $tools)->response();
    }

    private function getToolsToCall(InferenceResponse $response): ToolCalls {
        return $response->toolCalls();
    }

    /** Builds a PendingInference configured for tool-calling. */
    private function buildPendingInference(
        Messages $messages,
        Tools $tools
    ) : PendingInference {
        $toolChoice = is_array($this->toolChoice)
            ? ($this->toolChoice['type'] ?? 'auto')
            : $this->toolChoice;
        assert(is_string($toolChoice));

        $request = new InferenceRequest(
            messages: $messages,
            model: $this->model,
            tools: $tools->toToolSchema(),
            toolChoice: $toolChoice,
            responseFormat: $this->responseFormat,
            options: array_merge($this->options, ['parallel_tool_calls' => $this->parallelToolCalls]),
            mode: $this->mode,
            retryPolicy: $this->retryPolicy,
        );

        return $this->inference->create($request);
    }

    /** Builds the final ToolUseStep from inference response and executions. */
    private function buildStepFromResponse(
        InferenceResponse $response,
        ToolExecutions $executions,
        Messages $followUps,
        Messages $context,
    ) : ToolUseStep {
        $outputMessages = $followUps->appendMessage(
            Message::asAssistant($response->content()),
        );

        return new ToolUseStep(
            inputMessages: $context,
            outputMessages: $outputMessages,
            usage: $response->usage(),
            toolCalls: $response->toolCalls(),
            toolExecutions: $executions,
            inferenceResponse: $response,
            stepType: $this->inferStepType($response, $executions)
        );
    }

    private function inferStepType(InferenceResponse $response, ToolExecutions $executions) : ToolUseStepType {
        return match (true) {
            $executions->hasErrors() => ToolUseStepType::Error,
            $response->hasToolCalls() => ToolUseStepType::ToolExecution,
            default => ToolUseStepType::FinalResponse,
        };
    }
}
