<?php

namespace Cognesy\Instructor\LLMs\OpenAI;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Core\EventDispatcher;
use Cognesy\Instructor\Events\LLM\ChunkReceived;
use Cognesy\Instructor\Events\LLM\PartialJsonReceived;
use Cognesy\Instructor\Events\LLM\RequestSent;
use Cognesy\Instructor\Events\LLM\StreamedResponseFinished;
use Cognesy\Instructor\Events\LLM\StreamedResponseReceived;
use Cognesy\Instructor\LLMs\FunctionCall;
use Cognesy\Instructor\LLMs\LLMResponse;
use OpenAI;
use OpenAI\Client;
use OpenAI\Responses\StreamResponse;

class OpenAIFunctionCaller implements CanCallFunction
{
    private EventDispatcher $eventDispatcher;
    private Client $client;

    public function __construct(
        EventDispatcher $eventDispatcher,
        string $apiKey = '',
        string $baseUri = '',
        string $organization = '',
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $_apiKey = $apiKey ?: getenv('OPENAI_API_KEY');
        $_baseUri = $baseUri ?: getenv('OPENAI_BASE_URI');
        $_organization = $organization ?: getenv('OPENAI_ORGANIZATION');
        $this->client = OpenAI::factory()
            ->withApiKey($_apiKey)
            ->withOrganization($_organization)
            ->withBaseUri($_baseUri)
            ->make();
    }

    /**
     * Handle LLM function call
     */
    public function callFunction(
        array $messages,
        string $functionName,
        array $functionSchema,
        string $model = 'gpt-4-0125-preview',
        array $options = [],
    ) : LLMResponse {
        $request = array_merge([
            'model' => $model,
            'messages' => $messages,
            'tools' => [$functionSchema],
            'tool_choice' => [
                'type' => 'function',
                'function' => ['name' => $functionName]
            ]
        ], $options);

        return match($options['stream'] ?? false) {
            true => $this->handleStreamedChatCall($request),
            default => $this->handleChatCall($request)
        };
    }

    /**
     * Handle chat call
     */
    private function handleChatCall(array $request) : LLMResponse {
        $this->eventDispatcher->dispatch(new RequestSent($request));
        $response = $this->client->chat()->create($request);
        $this->eventDispatcher->dispatch(new StreamedResponseReceived($response->toArray()));

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

    /**
     * Handle streamed chat call
     */
    private function handleStreamedChatCall(array $request) : LLMResponse {
        // TODO: This function is not finished

        $this->eventDispatcher->dispatch(new RequestSent($request));
        $responseJson = '';
        $lastResponse = null;
        $toolCalls = [];

        $stream = $this->client->chat()->createStreamed($request);
        foreach($stream as $response){
            $this->eventDispatcher->dispatch(new StreamedResponseReceived($response->toArray()));
            $responseJson .= $this->processPartialResponse($response);
            $this->eventDispatcher->dispatch(new PartialJsonReceived($responseJson));
            $lastResponse = $response;
        }
        $this->eventDispatcher->dispatch(new StreamedResponseFinished($lastResponse->toArray()));

        // handle finishReason other than 'stop'
        $finishReason = $response->choices[0]->finishReason ?? null;

        return new LLMResponse($toolCalls, $finishReason, $response->toArray());
    }

    private function processPartialResponse(StreamResponse $response) : string {
        // TODO: This function is not finished

        // which function has been called - if parallel tools on
        if (isset($response->choices[0]->delta->toolCalls[0]->function->name)) {
            $this->selectedFunctions[] = $response->choices[0]->delta->toolCalls[0]->function->name ?? '';
        }

        $jsonChunk = $response->choices[0]->delta->toolCalls[0]->function->arguments ?? '';
        if ($jsonChunk) {
            $this->eventDispatcher->dispatch(new ChunkReceived($jsonChunk));
        }
        return $jsonChunk;
    }
}
