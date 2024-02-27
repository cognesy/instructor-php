<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Contracts\CanCallFunction;
use OpenAI;
use OpenAI\Client;
use OpenAI\Responses\Chat\CreateResponse;

class LLM implements CanCallFunction
{
    private Client $client;
    private CreateResponse $response;

    public function __construct(
        string $apiKey = '',
        string $baseUri = '',
        string $organization = '',
    ) {
        $_apiKey = $apiKey ?: getenv('OPENAI_API_KEY');
        $_baseUri = $baseUri ?: getenv('OPENAI_BASE_URI');
        $_organization = $organization ?: getenv('OPENAI_ORGANIZATION');
        $this->client = OpenAI::factory()
            ->withApiKey($_apiKey)
            ->withOrganization($_organization)
            ->withBaseUri($_baseUri)
            ->make();
    }

    public function callFunction(
        array $messages,
        string $functionName,
        array $functionSchema,
        string $model = 'gpt-4-0125-preview',
        array $options = []
    ) : string {
        $this->response = $this->client->chat()->create(array_merge([
            'model' => $model,
            'messages' => $messages,
            'tools' => [$functionSchema],
            'tool_choice' => [
                'type' => 'function',
                'function' => ['name' => $functionName]
            ]
        ], $options));
        return $this->data();
    }

    public function response() : array {
        return $this->response->toArray();
    }

    public function data() : string {
        return $this->response->choices[0]->message->toolCalls[0]->function->arguments ?? '';
    }
}
