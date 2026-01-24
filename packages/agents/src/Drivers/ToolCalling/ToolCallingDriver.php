<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ToolCalling;

use Cognesy\Agents\Agent\Collections\ToolExecutions;
use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\Agent\Contracts\CanExecuteToolCalls;
use Cognesy\Agents\Agent\Contracts\CanUseTools;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\Enums\AgentStepType;
use Cognesy\Http\HttpClient;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Json\Json;

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
    private ?InferenceRetryPolicy $retryPolicy;
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
        ?InferenceRetryPolicy $retryPolicy = null,
    ) {
        $this->llm = $llm ?? LLMProvider::new();
        $this->httpClient = $httpClient;
        $this->toolChoice = $toolChoice;
        $this->model = $model;
        $this->responseFormat = $responseFormat;
        $this->mode = $mode;
        $this->options = $options;
        $this->retryPolicy = $retryPolicy;
        $this->formatter = new ToolExecutionFormatter();
    }

    public function withLLMProvider(LLMProvider $llm): self {
        $clone = clone $this;
        $clone->llm = $llm;
        return $clone;
    }

    public function getLlmProvider(): LLMProvider {
        return $this->llm;
    }

    /**
     * Executes tool usage within a given context and returns the result as a AgentStep.
     *
     * @param AgentState $state The context containing messages, tools, and other related information required for tool usage.
     * @return AgentStep Returns an instance of AgentStep containing the response, executed tools, follow-up messages, and additional usage data.
     */
    #[\Override]
    public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor) : AgentStep {
        $response = $this->getToolCallResponse($state, $tools);
        $toolCalls = $this->getToolsToCall($response);
        $executions = $executor->useTools($toolCalls, $state);
        $messages = $this->formatter->makeExecutionMessages($executions);
        $context = $state->messagesForInference();
        return $this->buildStepFromResponse(
            response: $response,
            executions: $executions,
            followUps: $messages,
            context: $context,
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function getToolCallResponse(AgentState $state, Tools $tools) : InferenceResponse {
        $cache = $this->resolveCachedContext($state, $tools);
        return $this->buildPendingInference($state->messagesForInference(), $tools, $cache)->response();
    }

    private function getToolsToCall(InferenceResponse $response): ToolCalls {
        return $response->toolCalls();
    }

    /** Builds a PendingInference configured for tool-calling. */
    private function buildPendingInference(
        Messages $messages,
        Tools $tools,
        ?CachedContext $cache = null,
    ) : PendingInference {
        $toolChoice = is_array($this->toolChoice)
            ? ($this->toolChoice['type'] ?? 'auto')
            : $this->toolChoice;
        assert(is_string($toolChoice));

        $cache = $cache?->isEmpty() === true ? null : $cache;
        $cachedTools = $cache?->tools() ?? [];
        $hasCachedTools = $cachedTools !== [];
        $hasTools = !$tools->isEmpty();
        $toolSchemas = ($hasTools && !$hasCachedTools) ? $tools->toToolSchema() : [];
        $resolvedToolChoice = $this->resolveToolChoice($toolChoice, $cache);

        // Only include parallel_tool_calls when tools are specified (API requirement)
        $options = $this->options;
        if ($hasTools) {
            $options = array_merge($options, ['parallel_tool_calls' => $this->parallelToolCalls]);
        }

        $inference = (new Inference)
            ->withLLMProvider($this->llm)
            ->withMessages($messages->toArray())
            ->withModel($this->model)
            ->withTools($toolSchemas)
            ->withToolChoice($hasTools ? $resolvedToolChoice : '')
            ->withResponseFormat($this->responseFormat)
            ->withOptions($options)
            ->withOutputMode($this->mode);
        if ($this->retryPolicy !== null) {
            $inference = $inference->withRetryPolicy($this->retryPolicy);
        }
        if ($cache !== null) {
            $inference = $inference->withCachedContext(
                messages: $cache->messages()->toArray(),
                tools: $cachedTools,
                toolChoice: $cache->toolChoice(),
                responseFormat: $this->responseFormatToArray($cache->responseFormat()),
            );
        }
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        return $inference->create();
    }

    /** Builds the final AgentStep from inference response and executions. */
    private function buildStepFromResponse(
        InferenceResponse $response,
        ToolExecutions $executions,
        Messages $followUps,
        Messages $context,
    ) : AgentStep {
        $outputMessages = $this->appendResponseContent($followUps, $response);

        return new AgentStep(
            inputMessages: $context,
            outputMessages: $outputMessages,
            usage: $response->usage(),
            toolCalls: $response->toolCalls(),
            toolExecutions: $executions,
            inferenceResponse: $response,
            stepType: $this->inferStepType($response, $executions)
        );
    }

    private function appendResponseContent(Messages $messages, InferenceResponse $response) : Messages {
        $content = $response->content();
        if ($content === '') {
            return $messages;
        }
        if ($this->isToolArgsLeak($content, $response->toolCalls())) {
            return $messages;
        }
        return $messages->appendMessage(Message::asAssistant($content));
    }

    private function isToolArgsLeak(string $content, ToolCalls $toolCalls) : bool {
        if ($toolCalls->hasNone()) {
            return false;
        }

        $contentArgs = $this->parseContentArgs($content);
        if ($contentArgs === null) {
            return false;
        }

        foreach ($toolCalls->each() as $toolCall) {
            if ($toolCall->args() == $contentArgs) {
                return true;
            }
        }

        return false;
    }

    private function parseContentArgs(string $content) : ?array {
        $json = Json::fromString($content);
        if ($json->isEmpty()) {
            return null;
        }
        return $json->toArray();
    }

    private function inferStepType(InferenceResponse $response, ToolExecutions $executions) : AgentStepType {
        return match (true) {
            $executions->hasErrors() => AgentStepType::Error,
            $response->hasToolCalls() => AgentStepType::ToolExecution,
            default => AgentStepType::FinalResponse,
        };
    }

    private function resolveCachedContext(AgentState $state, Tools $tools): ?CachedContext {
        $cache = $state->cache();
        $cachedTools = $cache->tools();
        if ($cachedTools === [] && !$tools->isEmpty()) {
            $cachedTools = $tools->toToolSchema();
        }

        $resolved = new CachedContext(
            messages: $cache->messages()->toArray(),
            tools: $cachedTools,
            toolChoice: $cache->toolChoice(),
            responseFormat: $this->responseFormatToArray($cache->responseFormat()),
        );

        return $resolved->isEmpty() ? null : $resolved;
    }

    private function resolveToolChoice(string $toolChoice, ?CachedContext $cache): string {
        if ($cache === null) {
            return $toolChoice;
        }

        $cachedChoice = $cache->toolChoice();
        if ($cachedChoice === [] || $cachedChoice === '') {
            return $toolChoice;
        }

        return $toolChoice === 'auto' ? '' : $toolChoice;
    }

    private function responseFormatToArray(ResponseFormat $format): array {
        return $format->isEmpty()
            ? []
            : [
                'type' => $format->type(),
                'schema' => $format->schema(),
                'name' => $format->schemaName(),
                'strict' => $format->strict(),
            ];
    }
}
