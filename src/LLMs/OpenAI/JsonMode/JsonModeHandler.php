<?php
namespace Cognesy\Instructor\LLMs\OpenAI\JsonMode;

use Cognesy\Instructor\Core\Data\ResponseModel;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\ResponseReceivedFromLLM;
use Cognesy\Instructor\LLMs\Data\FunctionCall;
use Cognesy\Instructor\LLMs\Data\LLMResponse;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Result;
use Exception;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;

class JsonModeHandler
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
        $functionCalls = $this->getFunctionCalls($response);
        if (empty($functionCalls)) {
            return Result::failure(new RequestToLLMFailed($this->request, ['No tool calls found in the response']));
        }
        // handle finishReason other than 'stop'
        return Result::success(new LLMResponse(
                functionCalls: $functionCalls,
                finishReason: ($response->choices[0]->finishReason ?? null),
                rawData: $response->toArray(),
                isComplete: true)
        );
    }

    private function getFunctionCalls(CreateResponse $response) : array {
        if (!($content = $response->choices[0]->message->content)) {
            return [];
        }
        $jsonData = Json::extract($content);
        $toolCalls = [];
        $toolCalls[] = new FunctionCall(
            toolCallId: '', // ???
            functionName: $this->responseModel->functionName,
            functionArguments: $jsonData
        );
        return $toolCalls;
    }
}
