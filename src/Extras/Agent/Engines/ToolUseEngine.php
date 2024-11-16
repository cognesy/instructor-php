<?php

namespace Cognesy\Instructor\Extras\Agent\Engines;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCall;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Features\LLM\Enums\LLMFinishReason;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Utils\Json\Json;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;
use Throwable;

class ToolUseEngine {
    private Inference $inference;
    private array $tools;
    private int $maxDepth;
    private array $options;
    private bool $parallelToolCalls;
    private string|array $toolChoice;
    /** @var LLMResponse[] */
    private array $responses = [];
    private Throwable $error;
    private Messages $messages;
    private Usage $usage;
    private int $maxTokens;

    public function __construct(
        Inference    $inference,
        array        $tools,
        int          $maxSteps = 3,
        int          $maxTokens = 8192,
        array        $options = [],
        string|array $toolChoice = 'auto',
        bool         $parallelToolCalls = false
    ) {
        $this->inference = $inference;
        $this->tools = $tools;
        $this->maxDepth = $maxSteps;
        $this->parallelToolCalls = $parallelToolCalls;
        $this->toolChoice = $toolChoice;
        $this->options = $options;
        $this->maxTokens = $maxTokens;

        $this->messages = new Messages();
        $this->usage = new Usage();
    }

    public function response() : LLMResponse {
        return $this->respondWithTools($this->messages);
    }

    /**
     * @return LLMResponse[]
     */
    public function responses() : array {
        return $this->responses;
    }

    public function error() : Throwable {
        return $this->error;
    }

    public function usage() : Usage {
        return $this->usage;
    }

    // INTERNAL //////////////////////////////////////////////

    protected function respondWithTools(Messages $chat) : LLMResponse {
        $response = $this->inferResponse($chat->toArray(), $this->tools, $this->toolChoice);
        $this->usage->accumulate($response->usage());
        $this->responses[] = $response;
        $depth = 0;
        while ($this->continueProcessing($response)) {
            if ($depth++ >= $this->maxDepth) {
                break;
            }
            $responseMessages = $this->makeToolsResponseMessages($response);
            $chat->appendMessages($responseMessages);
            $response = $this->inferResponse($chat->toArray(), $this->tools, $this->toolChoice);
            $this->usage->accumulate($response->usage());
            $this->responses[] = $response;
        }
        return $response;
    }

    protected function continueProcessing(LLMResponse $response) : bool {
        return ($this->usage->total() < $this->maxTokens) &&
            ($response->hasToolCalls() || LLMFinishReason::ToolCalls->equals($response->finishReason()));
    }

    protected function inferResponse(string|array $messages, array $tools, array|string $toolChoice) : LLMResponse {
        return $this->inference
            ->create(
                messages: $messages,
                tools: $tools,
                toolChoice: $toolChoice,
                options: array_merge(
                    $this->options,
                    ['parallel_tool_calls' => $this->parallelToolCalls]
                ),
                mode: Mode::Tools,
            )->response();
    }

    protected function makeToolsResponseMessages(LLMResponse $response) : Messages {
        $messages = new Messages();
        $toolCalls = $response->toolCalls();
        foreach ($toolCalls->all() as $toolCall) {
            $result = $this->makeResult($toolCall);
            $messages->appendMessage($this->makeToolResponseMessage($toolCall));
            $messages->appendMessage($this->makeToolResultMessage($toolCall, $result));
            if (!$this->parallelToolCalls) {
                break;
            }
        }
        return $messages;
    }

    protected function makeResult(ToolCall $toolCall) : mixed {
        $function = $toolCall->name();
        $args = $toolCall->args();
        try {
            $result = $function(...$args);
        } catch (Throwable $e) {
            $this->error = $e;
            throw $e;
        }
        return $result;
    }

    protected function makeToolResponseMessage(ToolCall $toolCall) : Message {
        return new Message(
            role: 'assistant',
            metadata: [
                'tool_calls' => [$toolCall->toToolCallArray()]
            ]
        );
    }

    protected function makeToolResultMessage(ToolCall $toolCall, mixed $result) : Message {
        return new Message(
            role: 'tool',
            content: match(true) {
                is_string($result) => $result,
                is_array($result) => Json::encode($result),
                is_object($result) => Json::encode($result),
                default => (string) $result,
            },
            metadata: [
                'tool_call_id' => $toolCall->id(),
                'tool_name' => $toolCall->name(),
                'result' => $result
            ]
        );
    }
}

//    public function withInference(Inference $inference) : self {
//        $this->inference = $inference;
//        return $this;
//    }
//
//    public function withTools(array $tools) : self {
//        $this->tools = $tools;
//        return $this;
//    }
//
//    public function withToolChoice(string|array $toolChoice) : self {
//        $this->toolChoice = $toolChoice;
//        return $this;
//    }
//
//    public function withOptions(array $options) : self {
//        $this->options = $options;
//        return $this;
//    }
//
//    public function withMaxDepth(int $maxDepth) : self {
//        $this->maxDepth = $maxDepth;
//        return $this;
//    }
//
//    public function withParallelCalls(bool $parallelCalls) : self {
//        $this->parallelToolCalls = $parallelCalls;
//        return $this;
//    }
//
//    public function withMessages(string|array $messages) : self {
//        $messages = match(true) {
//            is_string($messages) => [['role' => 'user', 'content' => $messages]],
//            is_array($messages) => $messages,
//            default => []
//        };
//        $this->messages = Messages::fromArray($messages);
//        return $this;
//    }
