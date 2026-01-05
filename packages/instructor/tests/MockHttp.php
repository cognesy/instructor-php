<?php declare(strict_types=1);

namespace Cognesy\Instructor\Tests;

use Cognesy\Config\Env;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;

class MockHttp
{
    static public function get(array $args, ?string $provider = null) : HttpClient {
        $driver = new MockHttpDriver();

        // Auto-detect provider from config if not specified
        if ($provider === null) {
            $provider = self::detectProvider();
        }

        // If there's only one response, make it unlimited (for test reuse)
        // If there are multiple responses, make them sequential (for retry testing)
        $isSequential = count($args) > 1;

        foreach ($args as $json) {
            $responseBody = json_encode(self::mockResponse($json, $provider));
            $expectation = $driver->expect();

            if ($isSequential) {
                $expectation->times(1);  // Sequential: each response used once
            }

            $expectation->reply(HttpResponse::sync(
                statusCode: 200,
                headers: ['content-type' => 'application/json'],
                body: $responseBody,
            ));
        }

        return new HttpClient(driver: $driver);
    }

    static private function detectProvider(): string {
        // Load config to check default preset
        $configPath = __DIR__ . '/Fixtures/Setup/config/llm.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            $preset = $config['defaultPreset'] ?? 'openai';

            // Map preset to provider type
            if (isset($config['presets'][$preset]['providerType'])) {
                return $config['presets'][$preset]['providerType'];
            }
        }

        return 'openai'; // Default fallback
    }

    static private function mockResponse(string $json, string $provider): array {
        return match($provider) {
            'anthropic' => self::mockAnthropicResponse($json),
            default => self::mockOpenAIResponse($json),
        };
    }

    static private function mockAnthropicResponse(string $json): array {
        $data = json_decode($json, true);

        // Ensure data is decoded properly
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            // If JSON decode fails, use the string as-is
            $data = $json;
        }

        return [
            "id" => "msg_01XFDUDYJgAACzvnptvVoYEL",
            "type" => "message",
            "role" => "assistant",
            "model" => "claude-3-haiku-20240307",
            "content" => [
                [
                    "type" => "tool_use",
                    "id" => "toolu_01T1x1fJ34qAmk2tNTrN7Up6",
                    "name" => "extracted_data",
                    "input" => is_array($data) ? $data : json_decode($data, true),
                ]
            ],
            "stop_reason" => "end_turn",
            "stop_sequence" => null,
            "usage" => [
                "input_tokens" => 95,
                "output_tokens" => 9,
            ],
        ];
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
