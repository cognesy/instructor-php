<?php

use Cognesy\Http\Drivers\Mock\MockHttpResponse;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;

it('parses Anthropic response into normalized InferenceResponse', function () {
    $adapter = new AnthropicResponseAdapter(new AnthropicUsageFormat());
    $response = MockHttpResponse::json([
        'content' => [[ 'type' => 'text', 'text' => 'Hi!' ]],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 5, 'output_tokens' => 2],
    ]);

    $res = $adapter->fromResponse($response);
    expect($res->content())->toBe('Hi!');
    expect($res->usage()->input())->toBe(5);
    expect($res->usage()->output())->toBe(2);
});
