<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;

class MockHttp
{
    static public function get(array $args) : HttpClient {
        $driver = new MockHttpDriver();

        // If there's only one response, make it unlimited (for test reuse)
        // If there are multiple responses, make them sequential (for retry testing)
        $isSequential = count($args) > 1;

        foreach ($args as $json) {
            $responseBody = json_encode(self::mockOpenAIResponse($json));
            $expectation = $driver->expect();

            if ($isSequential) {
                $expectation->times(1);  // Sequential: each response used once
            }

            $expectation->reply(new HttpResponse(
                statusCode: 200,
                body: $responseBody,
                headers: ['content-type' => 'application/json'],
                isStreamed: false,
                stream: null
            ));
        }

        return new HttpClient(driver: $driver);
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
