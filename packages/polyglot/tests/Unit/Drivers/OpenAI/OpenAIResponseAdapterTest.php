<?php

use Cognesy\Http\Drivers\Mock\MockHttpResponse;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;

it('parses OpenAI response into normalized InferenceResponse', function () {
    $adapter = new OpenAIResponseAdapter(new OpenAIUsageFormat());
    $response = MockHttpResponse::json([
        'choices' => [[
            'message' => ['content' => 'Hello!'],
            'finish_reason' => 'stop'
        ]],
        'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2],
    ]);

    $res = $adapter->fromResponse($response);
    expect($res->content())->toBe('Hello!');
    expect($res->usage()->input())->toBe(3);
    expect($res->usage()->output())->toBe(2);
});
