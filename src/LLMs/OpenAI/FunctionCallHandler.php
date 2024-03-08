<?php

namespace Cognesy\Instructor\LLMs\OpenAI;

use Cognesy\Instructor\Core\EventDispatcher;
use Cognesy\Instructor\Events\LLM\RequestSent;
use Cognesy\Instructor\Events\LLM\ResponseReceived;
use Cognesy\Instructor\LLMs\FunctionCall;
use Cognesy\Instructor\LLMs\LLMResponse;
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
     */
    public function handle() : LLMResponse {
        $this->eventDispatcher->dispatch(new RequestSent($this->request));
        $response = $this->client->chat()->create($this->request);
        $this->eventDispatcher->dispatch(new ResponseReceived($response->toArray()));
        // which function has been called - if parallel tools on
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
        return new LLMResponse($toolCalls, $finishReason, $response->toArray());
    }
}