<?php

use Cognesy\Http\Drivers\Mock\MockHttpResponse;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiUsageFormat;

it('parses Gemini response into normalized InferenceResponse', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());
    $response = MockHttpResponse::json([
        'candidates' => [[
            'content' => ['parts' => [['text' => 'Hi!']]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['promptTokenCount' => 2, 'candidatesTokenCount' => 1],
    ]);

    $res = $adapter->fromResponse($response);
    expect($res->content())->toBe('Hi!');
    expect($res->usage()->input())->toBe(2);
    expect($res->usage()->output())->toBe(1);
});
