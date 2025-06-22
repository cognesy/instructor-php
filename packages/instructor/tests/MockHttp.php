<?php

namespace Cognesy\Instructor\Tests;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Http\PendingHttpResponse;
use Mockery;

class MockHttp
{
    static public function get(array $args) : HttpClient {
        $mockHttp = Mockery::mock(HttpClient::class);
        $mockResponse = Mockery::mock(HttpResponse::class);
        $mockPending = Mockery::mock(PendingHttpResponse::class);

        $list = [];
        foreach ($args as $arg) {
            $list[] = self::makeFunc($arg);
        }

        $mockHttp->shouldReceive('create')->andReturn($mockPending);
        $mockHttp->shouldReceive('with')->andReturn($mockResponse);
        $mockHttp->shouldReceive('withRequest')->andReturn($mockPending);
        $mockHttp->shouldReceive('get')->andReturn($mockResponse);
        $mockHttp->shouldReceive('withDebug')->andReturn($mockHttp);

        $mockPending->shouldReceive('get')->andReturn($mockResponse);

        $mockResponse->shouldReceive('statusCode')->andReturn(200);
        $mockResponse->shouldReceive('headers')->andReturn([]);
        $mockResponse->shouldReceive('body')->andReturnUsing(...$list);
        $mockResponse->shouldReceive('stream')->andReturn($mockResponse);

        return $mockHttp;
    }

    static private function makeFunc(string $json) {
        return fn() => json_encode(self::mockOpenAIResponse($json));
    }

    static private function mockOpenAIResponse(string $json) : array {
        return [
            "id" => "chatcmpl-AGH2w25Kx4hNnqUgcxqcgnqrzfIaD",
            "object" => "chat.completion",
            "created" => 1728442138,
            "model" => "gpt-4o-mini-2024-07-18",
            "choices" => [
                0 => [
                    "index" => 0,
                    "message" => [
                        "role" => "assistant",
                        "content" => null,
                        "tool_calls" => [
                            0 => [
                                "id" => "call_HGWji0nx7LQsRGGw1ckosq6S",
                                "type" => "function",
                                "function" => [
                                    "name" => "extracted_data",
                                    "arguments" => $json,
                                ]
                            ]
                        ],
                        "refusal" => null,
                    ],
                    "logprobs" => null,
                    "finish_reason" => "stop",
                ]
            ],
            "usage" => [
                "prompt_tokens" => 95,
                "completion_tokens" => 9,
                "total_tokens" => 104,
                "prompt_tokens_details" => [
                    "cached_tokens" => 0,
                ],
                "completion_tokens_details" => [
                    "reasoning_tokens" => 0,
                ],
            ],
            "system_fingerprint" => "fp_f85bea6784",
        ];
    }
}
