<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Unit\Drivers\Anthropic;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicMessageFormat;
use PHPUnit\Framework\TestCase;

class AnthropicMessageFormatTest extends TestCase
{
    private AnthropicMessageFormat $format;

    protected function setUp(): void
    {
        $this->format = new AnthropicMessageFormat();
    }

    public function test_serializes_all_tool_calls_not_just_first(): void
    {
        $message = new Message(
            role: 'assistant',
            content: '',
            toolCalls: new ToolCalls(
                new ToolCall('get_weather', ['location' => 'NYC'], 'call_1'),
                new ToolCall('get_time', ['timezone' => 'EST'], 'call_2'),
                new ToolCall('get_news', ['topic' => 'tech'], 'call_3'),
            ),
        );

        $messages = Messages::fromMessages([$message]);
        $result = $this->format->map($messages);

        $this->assertCount(1, $result);
        $this->assertEquals('assistant', $result[0]['role']);

        $content = $result[0]['content'];
        $this->assertCount(3, $content, 'All 3 tool calls must be serialized, not just the first');

        $this->assertEquals('tool_use', $content[0]['type']);
        $this->assertEquals('call_1', $content[0]['id']);
        $this->assertEquals('get_weather', $content[0]['name']);
        $this->assertEquals(['location' => 'NYC'], $content[0]['input']);

        $this->assertEquals('tool_use', $content[1]['type']);
        $this->assertEquals('call_2', $content[1]['id']);
        $this->assertEquals('get_time', $content[1]['name']);

        $this->assertEquals('tool_use', $content[2]['type']);
        $this->assertEquals('call_3', $content[2]['id']);
        $this->assertEquals('get_news', $content[2]['name']);
    }

    public function test_single_tool_call_still_works(): void
    {
        $message = new Message(
            role: 'assistant',
            content: '',
            toolCalls: new ToolCalls(
                new ToolCall('get_weather', ['location' => 'NYC'], 'call_1'),
            ),
        );

        $messages = Messages::fromMessages([$message]);
        $result = $this->format->map($messages);

        $this->assertCount(1, $result);
        $content = $result[0]['content'];
        $this->assertCount(1, $content);
        $this->assertEquals('get_weather', $content[0]['name']);
    }
}
