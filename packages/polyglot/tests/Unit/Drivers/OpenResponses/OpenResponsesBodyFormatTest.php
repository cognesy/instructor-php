<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Unit\Drivers\OpenResponses;

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesBodyFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesMessageFormat;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use PHPUnit\Framework\TestCase;

class OpenResponsesBodyFormatTest extends TestCase
{
    private OpenResponsesBodyFormat $bodyFormat;
    private LLMConfig $config;

    protected function setUp(): void
    {
        $this->config = new LLMConfig(
            driver: 'openresponses',
            apiUrl: 'https://api.example.com',
            apiKey: 'test-key',
            endpoint: '/v1/responses',
            model: 'gpt-4o',
            maxTokens: 1000,
        );

        $this->bodyFormat = new OpenResponsesBodyFormat(
            $this->config,
            new OpenResponsesMessageFormat(),
        );
    }

    public function test_basic_request_body(): void
    {
        $request = new InferenceRequest(
            messages: [
                ['role' => 'user', 'content' => 'Hello!'],
            ],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertEquals('gpt-4o', $body['model']);
        $this->assertEquals(1000, $body['max_output_tokens']);
        $this->assertArrayHasKey('input', $body);
        $this->assertArrayNotHasKey('messages', $body);
    }

    public function test_extracts_system_instructions(): void
    {
        $request = new InferenceRequest(
            messages: [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello!'],
            ],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertEquals('You are a helpful assistant.', $body['instructions']);

        // System messages should not be in input
        $inputMessages = $body['input'];
        foreach ($inputMessages as $msg) {
            if (isset($msg['role'])) {
                $this->assertNotEquals('system', $msg['role']);
            }
        }
    }

    public function test_merges_multiple_system_messages(): void
    {
        $request = new InferenceRequest(
            messages: [
                ['role' => 'system', 'content' => 'First instruction.'],
                ['role' => 'system', 'content' => 'Second instruction.'],
                ['role' => 'user', 'content' => 'Hello!'],
            ],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertStringContainsString('First instruction.', $body['instructions']);
        $this->assertStringContainsString('Second instruction.', $body['instructions']);
    }

    public function test_handles_developer_role_as_instructions(): void
    {
        $request = new InferenceRequest(
            messages: [
                ['role' => 'developer', 'content' => 'Developer instruction.'],
                ['role' => 'user', 'content' => 'Hello!'],
            ],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertEquals('Developer instruction.', $body['instructions']);
    }

    public function test_does_not_include_max_tokens_key(): void
    {
        $request = new InferenceRequest(
            messages: [
                ['role' => 'user', 'content' => 'Hello!'],
            ],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertArrayNotHasKey('max_tokens', $body);
        $this->assertArrayNotHasKey('max_completion_tokens', $body);
        $this->assertArrayHasKey('max_output_tokens', $body);
    }

    public function test_includes_tools(): void
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'description' => 'Get weather information',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'What is the weather?']],
            tools: $tools,
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertArrayHasKey('tools', $body);
        $this->assertEquals('get_weather', $body['tools'][0]['function']['name']);
    }

    public function test_includes_tool_choice(): void
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'parameters' => [],
                ],
            ],
        ];

        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'What is the weather?']],
            tools: $tools,
            toolChoice: ['function' => ['name' => 'get_weather']],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertArrayHasKey('tool_choice', $body);
        $this->assertEquals('function', $body['tool_choice']['type']);
        $this->assertEquals('get_weather', $body['tool_choice']['function']['name']);
    }

    public function test_json_schema_response_format(): void
    {
        $responseFormat = new ResponseFormat(
            type: 'json_schema',
            schema: [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
            name: 'Person',
            strict: true,
        );

        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'Give me a person']],
            responseFormat: $responseFormat,
            mode: OutputMode::JsonSchema,
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertArrayHasKey('text', $body);
        $this->assertArrayHasKey('format', $body['text']);
        $this->assertEquals('json_schema', $body['text']['format']['type']);
        $this->assertEquals('Person', $body['text']['format']['name']);
        $this->assertTrue($body['text']['format']['strict']);
    }

    public function test_json_object_response_format(): void
    {
        $responseFormat = new ResponseFormat(
            type: 'json_object',
        );

        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'Give me JSON']],
            responseFormat: $responseFormat,
            mode: OutputMode::Json,
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertArrayHasKey('text', $body);
        $this->assertEquals('json_object', $body['text']['format']['type']);
    }

    public function test_includes_temperature_and_top_p(): void
    {
        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'Hello!']],
            options: [
                'temperature' => 0.7,
                'top_p' => 0.9,
            ],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertEquals(0.7, $body['temperature']);
        $this->assertEquals(0.9, $body['top_p']);
    }

    public function test_includes_stream_option(): void
    {
        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'Hello!']],
            options: ['stream' => true],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertTrue($body['stream']);
    }

    public function test_includes_previous_response_id(): void
    {
        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'Continue...']],
            options: ['previous_response_id' => 'resp_abc123'],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertEquals('resp_abc123', $body['previous_response_id']);
    }

    public function test_prefers_max_output_tokens_option_over_config(): void
    {
        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'Hello!']],
            options: ['max_output_tokens' => 222],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertEquals(222, $body['max_output_tokens']);
    }

    public function test_accepts_max_tokens_option_for_responses_api(): void
    {
        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'Hello!']],
            options: ['max_tokens' => 333],
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertEquals(333, $body['max_output_tokens']);
        $this->assertArrayNotHasKey('max_tokens', $body);
    }

    public function test_cached_context_system_messages_become_instructions(): void
    {
        $cachedContext = new CachedContext(messages: [
            ['role' => 'system', 'content' => 'Cached system instruction'],
            ['role' => 'user', 'content' => 'Cached user'],
        ]);

        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'New user']],
            cachedContext: $cachedContext,
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $this->assertStringContainsString('Cached system instruction', $body['instructions']);
        $roles = array_map(fn(array $item) => $item['role'] ?? '', $body['input']);
        $this->assertNotContains('system', $roles);
        $this->assertCount(2, $body['input']);
    }

    public function test_cached_context_tool_flow_maps_to_function_call_items(): void
    {
        $cachedContext = new CachedContext(messages: [
            [
                'role' => 'assistant',
                'content' => '',
                '_metadata' => [
                    'tool_calls' => [
                        [
                            'id' => 'call_cached',
                            'function' => [
                                'name' => 'get_weather',
                                'arguments' => '{"city":"Paris"}',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'role' => 'tool',
                'content' => '{"temperature":72}',
                '_metadata' => [
                    'tool_call_id' => 'call_cached',
                ],
            ],
        ]);

        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'Continue']],
            cachedContext: $cachedContext,
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $types = array_map(fn(array $item) => $item['type'] ?? '', $body['input']);
        $this->assertContains('function_call', $types);
        $this->assertContains('function_call_output', $types);
    }

    public function test_removes_disallowed_schema_entries(): void
    {
        $responseFormat = new ResponseFormat(
            type: 'json_schema',
            schema: [
                'type' => 'object',
                'x-title' => 'Should be removed',
                'x-php-class' => 'App\\Person',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'x-title' => 'Also removed',
                    ],
                ],
            ],
            name: 'Person',
        );

        $request = new InferenceRequest(
            messages: [['role' => 'user', 'content' => 'Test']],
            responseFormat: $responseFormat,
            mode: OutputMode::JsonSchema,
        );

        $body = $this->bodyFormat->toRequestBody($request);

        $schema = $body['text']['format']['schema'];
        $this->assertArrayNotHasKey('x-title', $schema);
        $this->assertArrayNotHasKey('x-php-class', $schema);
        $this->assertArrayNotHasKey('x-title', $schema['properties']['name']);
    }
}
