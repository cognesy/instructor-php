<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIRequestAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\InferenceResponseFactory;

it('OpenAI golden adapter: complex request + streaming response assembly', function () {
    $config = new LLMConfig(
        apiUrl: 'https://api.openai.com/v1',
        apiKey: 'KEY',
        endpoint: '/chat/completions',
        model: 'gpt-4o-mini',
        driver: 'openai',
    );
    $reqAdapter = new OpenAIRequestAdapter($config, new OpenAIBodyFormat($config, new OpenAIMessageFormat()));
    $resAdapter = new OpenAIResponseAdapter(new OpenAIUsageFormat());

    $tools = [[
        'type' => 'function',
        'function' => [
            'name' => 'search',
            'parameters' => [ 'type' => 'object', 'properties' => ['q' => ['type' => 'string']], 'required' => ['q'] ],
        ]
    ]];

    $ireq = new InferenceRequest(
        messages: Messages::fromAny([['role' => 'system', 'content' => 'You are helpful.'], ['role' => 'user', 'content' => 'Hi']]),
        model: 'gpt-4o-mini',
        tools: $tools,
        toolChoice: 'auto',
        responseFormat: ResponseFormat::fromData(['type' => 'json_object']),
        options: ['stream' => true],
    );

    $http = $reqAdapter->toHttpRequest($ireq);
    expect($http->url())->toBe('https://api.openai.com/v1/chat/completions');
    $body = json_decode($http->body()->toString(), true);
    expect($body['model'])->toBe('gpt-4o-mini');
    expect($body['tools'][0]['function']['name'])->toBe('search');
    expect(($body['response_format']['type'] ?? ''))->toBe('json_object');
    expect($http->isStreamed())->toBeTrue();

    // Streaming chunks
    $chunks = [
        json_encode(['choices' => [['delta' => ['content' => 'Hel']]]]),
        json_encode(['choices' => [['delta' => ['content' => 'lo']]]]),
        json_encode(['choices' => [[ 'delta' => [ 'tool_calls' => [[ 'id' => 'c1', 'function' => [ 'name' => 'search', 'arguments' => '{"q":"Hello"}' ] ]] ] ]]]),
    ];
    $partials = [];
    foreach ($chunks as $e) {
        $p = $resAdapter->fromStreamResponse($e);
        if ($p) { $partials[] = $p; }
    }
    $list = PartialInferenceResponseList::of(...$partials);
    $final = InferenceResponseFactory::fromPartialResponses($list);
    expect(str_starts_with($final->content(), 'Hello'))->toBeTrue();
    $tool = $final->toolCalls()->first();
    expect($tool->name())->toBe('search');
    expect($tool->value('q'))->toBe('Hello');
});
