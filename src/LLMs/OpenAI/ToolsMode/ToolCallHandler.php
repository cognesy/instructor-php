<?php

namespace Cognesy\Instructor\LLMs\OpenAI\ToolsMode;

use Cognesy\Instructor\Core\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\ResponseReceivedFromLLM;
use Cognesy\Instructor\LLMs\FunctionCall;
use Cognesy\Instructor\LLMs\LLMResponse;
use Cognesy\Instructor\Utils\Result;
use Exception;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;

class ToolCallHandler
{
    public function __construct(
        private EventDispatcher $eventDispatcher,
        private Client $client,
        private array $request,
        private ResponseModel $responseModel,
    ) {}

    /**
     * Handle chat call
     * @return Result<LLMResponse, mixed>
     */
    public function handle() : Result {
        try {
            $this->eventDispatcher->dispatch(new RequestSentToLLM($this->request));
            $response = $this->client->chat()->create($this->request);
            $this->eventDispatcher->dispatch(new ResponseReceivedFromLLM($response->toArray()));
        } catch (Exception $e) {
            $event = new RequestToLLMFailed($this->request, [$e->getMessage()]);
            $this->eventDispatcher->dispatch($event);
            return Result::failure($event);
        }
        // which functions have been selected - if parallel tools on
        $toolCalls = $this->getFunctionCalls($response);
        if (empty($toolCalls)) {
            return Result::failure(new RequestToLLMFailed($this->request, ['No tool calls found in the response']));
        }
        // handle finishReason other than 'stop'
        return Result::success(new LLMResponse(
            toolCalls: $toolCalls,
            finishReason: ($response->choices[0]->finishReason ?? null),
            rawData: $response->toArray(),
            isComplete: true)
        );
    }

    private function getFunctionCalls(CreateResponse $response) : array {
        if (!isset($response->choices[0]->message->toolCalls)) {
            return [];
        }
        $toolCalls = [];
        foreach ($response->choices[0]->message->toolCalls as $data) {
            $toolCalls[] = new FunctionCall(
                toolCallId: $data->id ?? '',
                functionName: $data->function->name ?? '',
                functionArguments: $data->function->arguments ?? ''
            );
        }
        return $toolCalls;
    }
}
