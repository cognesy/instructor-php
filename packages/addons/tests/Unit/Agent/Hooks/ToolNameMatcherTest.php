<?php declare(strict_types=1);

namespace Packages\addons\tests\Unit\Agent\Hooks;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Hooks\Data\ExecutionHookContext;
use Cognesy\Addons\Agent\Hooks\Data\ToolHookContext;
use Cognesy\Addons\Agent\Hooks\Matchers\ToolNameMatcher;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ToolNameMatcherTest extends TestCase
{
    private function createToolContext(string $name): ToolHookContext
    {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_123',
            'name' => $name,
            'arguments' => [],
        ]);

        return ToolHookContext::beforeTool($toolCall, AgentState::empty());
    }

    #[Test]
    public function it_matches_exact_tool_name(): void
    {
        $matcher = new ToolNameMatcher('bash');

        $this->assertTrue($matcher->matches($this->createToolContext('bash')));
        $this->assertFalse($matcher->matches($this->createToolContext('read_file')));
    }

    #[Test]
    public function it_matches_all_with_wildcard(): void
    {
        $matcher = new ToolNameMatcher('*');

        $this->assertTrue($matcher->matches($this->createToolContext('bash')));
        $this->assertTrue($matcher->matches($this->createToolContext('read_file')));
        $this->assertTrue($matcher->matches($this->createToolContext('anything')));
    }

    #[Test]
    public function it_matches_prefix_wildcard(): void
    {
        $matcher = new ToolNameMatcher('read_*');

        $this->assertTrue($matcher->matches($this->createToolContext('read_file')));
        $this->assertTrue($matcher->matches($this->createToolContext('read_stdin')));
        $this->assertFalse($matcher->matches($this->createToolContext('write_file')));
        $this->assertFalse($matcher->matches($this->createToolContext('read')));
    }

    #[Test]
    public function it_matches_suffix_wildcard(): void
    {
        $matcher = new ToolNameMatcher('*_file');

        $this->assertTrue($matcher->matches($this->createToolContext('read_file')));
        $this->assertTrue($matcher->matches($this->createToolContext('write_file')));
        $this->assertFalse($matcher->matches($this->createToolContext('read_stdin')));
    }

    #[Test]
    public function it_matches_regex_pattern(): void
    {
        $matcher = new ToolNameMatcher('/^(read|write)_.+$/');

        $this->assertTrue($matcher->matches($this->createToolContext('read_file')));
        $this->assertTrue($matcher->matches($this->createToolContext('write_file')));
        $this->assertFalse($matcher->matches($this->createToolContext('bash')));
        $this->assertFalse($matcher->matches($this->createToolContext('read_')));
    }

    #[Test]
    public function it_returns_false_for_non_tool_context(): void
    {
        $matcher = new ToolNameMatcher('*');
        $nonToolContext = ExecutionHookContext::onStart(AgentState::empty());

        $this->assertFalse($matcher->matches($nonToolContext));
    }
}
