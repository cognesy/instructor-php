<?php

namespace Cognesy\Instructor\LLMs\OpenAI;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Events\LLM\RequestToLLMFailed;
use Cognesy\Instructor\Events\LLM\RequestSentToLLM;
use Cognesy\Instructor\Events\LLM\ResponseReceivedFromLLM;
use Cognesy\Instructor\LLMs\FunctionCall;
use Cognesy\Instructor\LLMs\LLMResponse;
use Cognesy\Instructor\Utils\Result;
use Exception;
use OpenAI\Client;

class FunctionCallHandler
{
    public function __construct(
        private EventDispatcher $eventDispatcher,
        private Client $client,
        private array $request,
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
        $toolCalls = [];
        foreach ($response->choices[0]->message->toolCalls as $data) {
            $toolCalls[] = new FunctionCall(
                $data->id ?? '',
                $data->function->name ?? '',
                $data->function->arguments ?? ''
            );
        }
        // handle finishReason other than 'stop'
        $finishReason = $response->choices[0]->finishReason ?? null;
        return Result::success(new LLMResponse($toolCalls, $finishReason, $response->toArray(), true));
    }
}
