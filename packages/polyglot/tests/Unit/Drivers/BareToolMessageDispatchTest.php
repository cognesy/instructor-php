<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Unit\Drivers;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolResult;
use Cognesy\Messages\ToolCallId;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesMessageFormat;
use PHPUnit\Framework\TestCase;

/**
 * Regression: a plain tool-role message (no ToolResult) must not emit
 * malformed provider payloads with empty tool identifiers.
 */
class BareToolMessageDispatchTest extends TestCase
{
    private function bareToolMessage(): Message
    {
        return new Message(role: 'tool', content: 'some plain text');
    }

    private function properToolMessage(): Message
    {
        return new Message(
            role: 'tool',
            content: '{"temperature": 72}',
            toolResult: new ToolResult(
                content: '{"temperature": 72}',
                callId: new ToolCallId('call_abc'),
                toolName: 'get_weather',
            ),
        );
    }

    private function wrap(Message $message): Messages
    {
        return Messages::fromMessages([$message]);
    }

    // --- OpenAI ---

    public function test_openai_bare_tool_message_has_no_empty_tool_call_id(): void
    {
        $result = (new OpenAIMessageFormat())->map($this->wrap($this->bareToolMessage()));

        $this->assertCount(1, $result);
        // Should NOT have 'tool_call_id' with an empty string
        $this->assertArrayNotHasKey('tool_call_id', $result[0], 'Bare tool message must not emit tool_call_id');
    }

    public function test_openai_proper_tool_message_emits_tool_call_id(): void
    {
        $result = (new OpenAIMessageFormat())->map($this->wrap($this->properToolMessage()));

        $this->assertCount(1, $result);
        $this->assertEquals('tool', $result[0]['role']);
        $this->assertEquals('call_abc', $result[0]['tool_call_id']);
    }

    // --- Anthropic ---

    public function test_anthropic_bare_tool_message_has_no_empty_tool_use_id(): void
    {
        $result = (new AnthropicMessageFormat())->map($this->wrap($this->bareToolMessage()));

        $this->assertCount(1, $result);
        // Should be a plain user message, not a tool_result block with empty ids
        $contentBlock = $result[0]['content'] ?? null;
        if (is_array($contentBlock) && isset($contentBlock[0]['type'])) {
            $this->assertNotEquals('tool_result', $contentBlock[0]['type'],
                'Bare tool message must not produce a tool_result block');
        }
    }

    public function test_anthropic_proper_tool_message_emits_tool_result(): void
    {
        $result = (new AnthropicMessageFormat())->map($this->wrap($this->properToolMessage()));

        $this->assertCount(1, $result);
        $this->assertEquals('tool_result', $result[0]['content'][0]['type']);
        $this->assertEquals('call_abc', $result[0]['content'][0]['tool_use_id']);
    }

    // --- Gemini ---

    public function test_gemini_bare_tool_message_has_no_empty_function_response(): void
    {
        $result = (new GeminiMessageFormat())->map($this->wrap($this->bareToolMessage()));

        $this->assertCount(1, $result);
        $parts = $result[0]['parts'] ?? [];
        // Should NOT contain a functionResponse with empty name
        foreach ($parts as $part) {
            $this->assertArrayNotHasKey('functionResponse', $part,
                'Bare tool message must not produce a functionResponse part');
        }
    }

    public function test_gemini_proper_tool_message_emits_function_response(): void
    {
        $result = (new GeminiMessageFormat())->map($this->wrap($this->properToolMessage()));

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('functionResponse', $result[0]['parts'][0]);
        $this->assertEquals('get_weather', $result[0]['parts'][0]['functionResponse']['name']);
    }

    // --- OpenResponses ---

    public function test_open_responses_bare_tool_message_has_no_empty_call_id(): void
    {
        $result = (new OpenResponsesMessageFormat())->map($this->wrap($this->bareToolMessage()));

        $this->assertCount(1, $result);
        // Should NOT be a function_call_output with empty call_id
        $this->assertNotEquals('function_call_output', $result[0]['type'] ?? '',
            'Bare tool message must not produce a function_call_output item');
    }

    public function test_open_responses_proper_tool_message_emits_function_call_output(): void
    {
        $result = (new OpenResponsesMessageFormat())->map($this->wrap($this->properToolMessage()));

        $this->assertCount(1, $result);
        $this->assertEquals('function_call_output', $result[0]['type']);
        $this->assertEquals('call_abc', $result[0]['call_id']);
    }
}
