<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Tests\Unit\Drivers;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiMessageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIMessageFormat;
use PHPUnit\Framework\TestCase;

final class MixedAssistantToolCallContentTest extends TestCase
{
    private function mixedAssistantToolCallMessage(): Messages
    {
        return Messages::fromMessages([
            new Message(
                role: 'assistant',
                content: 'Let me check.',
                toolCalls: new ToolCalls(
                    new ToolCall('search', ['q' => 'hello'], 'call_1'),
                ),
            ),
        ]);
    }

    public function test_openai_preserves_assistant_content_when_tool_calls_exist(): void
    {
        $result = (new OpenAIMessageFormat())->map($this->mixedAssistantToolCallMessage());

        $this->assertCount(1, $result);
        $this->assertSame('assistant', $result[0]['role']);
        $this->assertSame('Let me check.', $result[0]['content'] ?? null);
        $this->assertSame('call_1', $result[0]['tool_calls'][0]['id'] ?? null);
    }

    public function test_anthropic_preserves_text_block_when_tool_calls_exist(): void
    {
        $result = (new AnthropicMessageFormat())->map($this->mixedAssistantToolCallMessage());

        $this->assertCount(1, $result);
        $this->assertSame('assistant', $result[0]['role']);
        $this->assertSame('text', $result[0]['content'][0]['type'] ?? null);
        $this->assertSame('Let me check.', $result[0]['content'][0]['text'] ?? null);
        $this->assertSame('tool_use', $result[0]['content'][1]['type'] ?? null);
        $this->assertSame('call_1', $result[0]['content'][1]['id'] ?? null);
    }

    public function test_gemini_preserves_text_part_when_tool_calls_exist(): void
    {
        $result = (new GeminiMessageFormat())->map($this->mixedAssistantToolCallMessage());

        $this->assertCount(1, $result);
        $this->assertSame('model', $result[0]['role']);
        $this->assertSame('Let me check.', $result[0]['parts'][0]['text'] ?? null);
        $this->assertSame('search', $result[0]['parts'][1]['functionCall']['name'] ?? null);
    }
}
