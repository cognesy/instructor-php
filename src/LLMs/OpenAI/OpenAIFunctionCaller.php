<?php

namespace Cognesy\Instructor\LLMs\OpenAI;

use Cognesy\Instructor\Contracts\CanCallFunction;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\LLMs\LLMResponse;
use Cognesy\Instructor\Utils\Result;
use OpenAI;
use OpenAI\Client;

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
    ) : Result {
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
            true => (new StreamedFunctionCallHandler($this->eventDispatcher, $this->client, $request))->handle(),
            default => (new FunctionCallHandler($this->eventDispatcher, $this->client, $request))->handle()
        };
    }
}
