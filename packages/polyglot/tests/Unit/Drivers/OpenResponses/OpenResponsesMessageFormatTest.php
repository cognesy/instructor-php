<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Unit\Drivers\OpenResponses;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesMessageFormat;
use PHPUnit\Framework\TestCase;

class OpenResponsesMessageFormatTest extends TestCase
{
    private OpenResponsesMessageFormat $messageFormat;

    protected function setUp(): void
    {
        $this->messageFormat = new OpenResponsesMessageFormat;
    }

    public function test_converts_user_message_to_message_item(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Hello!'],
        ];

        $result = $this->messageFormat->map(Messages::fromArray($messages));

        $this->assertCount(1, $result);
        $this->assertEquals('message', $result[0]['type']);
        $this->assertEquals('user', $result[0]['role']);
        $this->assertEquals('input_text', $result[0]['content'][0]['type']);
        $this->assertEquals('Hello!', $result[0]['content'][0]['text']);
    }

    public function test_converts_assistant_message_to_message_item(): void
    {
        $messages = [
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];

        $result = $this->messageFormat->map(Messages::fromArray($messages));

        $this->assertCount(1, $result);
        $this->assertEquals('message', $result[0]['type']);
        $this->assertEquals('assistant', $result[0]['role']);
        $this->assertEquals('output_text', $result[0]['content'][0]['type']);
        $this->assertEquals('Hi there!', $result[0]['content'][0]['text']);
    }

    public function test_converts_tool_call_to_function_call_items(): void
    {
        $messages = [
            [
                'role' => 'assistant',
                'parts' => [
                    [
                        'type' => 'tool_call',
                        'tool_call' => [
                            'id' => 'call_123',
                            'name' => 'get_weather',
                            'arguments' => '{"location": "NYC"}',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->messageFormat->map(Messages::fromArray($messages));

        $this->assertCount(1, $result);
        $this->assertEquals('function_call', $result[0]['type']);
        $this->assertEquals('call_123', $result[0]['call_id']);
        $this->assertEquals('get_weather', $result[0]['name']);
        $this->assertEquals('{"location": "NYC"}', $result[0]['arguments']);
    }

    public function test_converts_tool_call_with_content_to_multiple_items(): void
    {
        $messages = [
            [
                'role' => 'assistant',
                'parts' => [
                    ['type' => 'text', 'text' => 'Let me check the weather.'],
                    [
                        'type' => 'tool_call',
                        'tool_call' => [
                            'id' => 'call_123',
                            'name' => 'get_weather',
                            'arguments' => '{}',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->messageFormat->map(Messages::fromArray($messages));

        $this->assertCount(2, $result);
        // First item should be the message content
        $this->assertEquals('message', $result[0]['type']);
        $this->assertEquals('assistant', $result[0]['role']);
        // Second item should be the function call
        $this->assertEquals('function_call', $result[1]['type']);
    }

    public function test_converts_tool_result_to_function_call_output(): void
    {
        $messages = [
            [
                'role' => 'tool',
                'parts' => [
                    [
                        'type' => 'tool_result',
                        'tool_result' => [
                            'content' => '{"temperature": 72}',
                            'call_id' => 'call_123',
                            'tool_name' => 'get_weather',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->messageFormat->map(Messages::fromArray($messages));

        $this->assertCount(1, $result);
        $this->assertEquals('function_call_output', $result[0]['type']);
        $this->assertEquals('call_123', $result[0]['call_id']);
        $this->assertEquals('{"temperature": 72}', $result[0]['output']);
    }

    public function test_handles_multiple_tool_calls(): void
    {
        $messages = [
            [
                'role' => 'assistant',
                'parts' => [
                    [
                        'type' => 'tool_call',
                        'tool_call' => [
                            'id' => 'call_1',
                            'name' => 'get_weather',
                            'arguments' => '{"location": "NYC"}',
                        ],
                    ],
                    [
                        'type' => 'tool_call',
                        'tool_call' => [
                            'id' => 'call_2',
                            'name' => 'get_time',
                            'arguments' => '{"timezone": "EST"}',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->messageFormat->map(Messages::fromArray($messages));

        $this->assertCount(2, $result);
        $this->assertEquals('function_call', $result[0]['type']);
        $this->assertEquals('call_1', $result[0]['call_id']);
        $this->assertEquals('function_call', $result[1]['type']);
        $this->assertEquals('call_2', $result[1]['call_id']);
    }

    public function test_handles_content_array_format(): void
    {
        $messages = [
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'What is in this image?'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'https://example.com/image.jpg']],
                ],
            ],
        ];

        $result = $this->messageFormat->map(Messages::fromArray($messages));

        $this->assertCount(1, $result);
        $this->assertEquals('message', $result[0]['type']);
        $this->assertCount(2, $result[0]['content']);
        $this->assertEquals('input_text', $result[0]['content'][0]['type']);
        $this->assertEquals('input_image', $result[0]['content'][1]['type']);
    }

    public function test_preserves_empty_messages_as_message_items(): void
    {
        $messages = [
            ['role' => 'user', 'content' => ''],
            ['role' => 'user', 'content' => 'Hello!'],
        ];

        $result = $this->messageFormat->map(Messages::fromArray($messages));

        // Empty content messages are preserved but with empty content array
        $this->assertCount(2, $result);
        $this->assertEquals('Hello!', $result[1]['content'][0]['text']);
    }

    public function test_handles_conversation_flow(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'What is the weather in NYC?'],
            [
                'role' => 'assistant',
                'parts' => [
                    [
                        'type' => 'tool_call',
                        'tool_call' => [
                            'id' => 'call_weather',
                            'name' => 'get_weather',
                            'arguments' => '{"location": "NYC"}',
                        ],
                    ],
                ],
            ],
            [
                'role' => 'tool',
                'parts' => [
                    [
                        'type' => 'tool_result',
                        'tool_result' => [
                            'content' => '{"temperature": 72, "condition": "sunny"}',
                            'call_id' => 'call_weather',
                            'tool_name' => 'get_weather',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->messageFormat->map(Messages::fromArray($messages));

        $this->assertCount(3, $result);
        $this->assertEquals('message', $result[0]['type']);
        $this->assertEquals('function_call', $result[1]['type']);
        $this->assertEquals('function_call_output', $result[2]['type']);
    }
}
