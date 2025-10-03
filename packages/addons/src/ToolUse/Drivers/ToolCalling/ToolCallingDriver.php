<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Drivers\ToolCalling;

use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Enums\ToolUseStepType;
use Cognesy\Addons\ToolUse\ToolExecutor;
use Cognesy\Http\HttpClient;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;

/**
 * ToolCallingDriver is responsible for managing the interaction between a
 * language model (LLM) and various tools. It defines the workflow for
 * generating a response based on input messages, selecting and invoking tools,
 * handling tool execution results, and crafting follow-up messages.
 */
class ToolCallingDriver implements CanUseTools
{
    private LLMProvider $llm;
    private ?HttpClient $httpClient = null;
    private string|array $toolChoice;
    private string $model;
    private array $responseFormat;
    private OutputMode $mode;
    private array $options;
    private bool $parallelToolCalls = false;
    private ToolExecutionFormatter $formatter;

    public function __construct(
        ?LLMProvider $llm = null,
        ?HttpClient  $httpClient = null,
        string|array $toolChoice = 'auto',
        array        $responseFormat = [],
        string       $model = '',
        array        $options = [],
        OutputMode   $mode = OutputMode::Tools,
    ) {
        $this->llm = $llm ?? LLMProvider::new();
        $this->httpClient = $httpClient;
        $this->toolChoice = $toolChoice;
        $this->model = $model;
        $this->responseFormat = $responseFormat;
        $this->mode = $mode;
        $this->options = $options;
        $this->formatter = new ToolExecutionFormatter();
    }

    /**
     * Executes tool usage within a given context and returns the result as a ToolUseStep.
     *
     * @param ToolUseState $state The context containing messages, tools, and other related information required for tool usage.
     * @return ToolUseStep Returns an instance of ToolUseStep containing the response, executed tools, follow-up messages, and additional usage data.
     */
    #[\Override]
    public function useTools(ToolUseState $state, Tools $tools, ToolExecutor $executor) : ToolUseStep {
        $pending = $this->buildPendingInference($state->messages(), $tools);
        $response = $pending->response();
        $executions = $executor->useTools($response->toolCalls(), $state);
        $messages = $this->formatter->makeExecutionMessages($executions);
        return $this->buildStepFromResponse(
            response: $response,
            executions: $executions,
            followUps: $messages,
            context: $state->messages(),
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    /** Builds a PendingInference configured for tool-calling. */
    private function buildPendingInference(
        Messages $messages,
        Tools $tools
    ) : PendingInference {
        $toolChoice = is_array($this->toolChoice)
            ? ($this->toolChoice['type'] ?? 'auto')
            : $this->toolChoice;
        assert(is_string($toolChoice));

        $inference = (new Inference)
            ->withLLMProvider($this->llm)
            ->withMessages($messages->toArray())
            ->withModel($this->model)
            ->withTools($tools->toToolSchema())
            ->withToolChoice($toolChoice)
            ->withResponseFormat($this->responseFormat)
            ->withOptions(array_merge($this->options, ['parallel_tool_calls' => $this->parallelToolCalls]))
            ->withOutputMode($this->mode);
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        return $inference->create();
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
