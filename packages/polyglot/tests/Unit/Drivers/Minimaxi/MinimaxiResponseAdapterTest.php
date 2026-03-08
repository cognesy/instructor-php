<?php declare(strict_types=1);

use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Polyglot\Inference\Drivers\Minimaxi\MinimaxiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;

it('parses MiniMaxi response with choices into normalized InferenceResponse', function (): void {
    $adapter = new MinimaxiResponseAdapter(new OpenAIUsageFormat());
    $response = MockHttpResponseFactory::json([
        'choices' => [[
            'message' => ['content' => 'Paris'],
            'finish_reason' => 'stop',
        ]],
        'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2],
        'base_resp' => ['status_code' => 0, 'status_msg' => ''],
    ]);

    $result = $adapter->fromResponse($response);

    expect($result->content())->toBe('Paris');
    expect($result->usage()->input())->toBe(3);
    expect($result->usage()->output())->toBe(2);
});

it('throws provider error when MiniMaxi returns base_resp failure in 200 body', function (): void {
    $adapter = new MinimaxiResponseAdapter(new OpenAIUsageFormat());
    $response = MockHttpResponseFactory::json([
        'id' => 'resp_123',
        'choices' => null,
        'base_resp' => [
            'status_code' => 1008,
            'status_msg' => 'insufficient balance',
        ],
    ]);

    expect(fn() => $adapter->fromResponse($response))
        ->toThrow(RuntimeException::class, 'MiniMaxi API error 1008: insufficient balance');
});
