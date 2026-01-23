<?php declare(strict_types=1);

namespace Packages\addons\tests\Unit\Agent\Hooks;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Hooks\Data\ToolHookContext;
use Cognesy\Addons\Agent\Hooks\Matchers\CallableMatcher;
use Cognesy\Addons\Agent\Hooks\Matchers\CompositeMatcher;
use Cognesy\Addons\Agent\Hooks\Matchers\ToolNameMatcher;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CompositeMatcherTest extends TestCase
{
    private function createToolContext(string $name, int $stepCount = 0): ToolHookContext
    {
        $toolCall = ToolCall::fromArray([
            'id' => 'call_123',
            'name' => $name,
            'arguments' => [],
        ]);

        $state = AgentState::empty();
        // Note: We can't easily set stepCount on AgentState, so we'll use metadata for testing

        return ToolHookContext::beforeTool($toolCall, $state, ['stepCount' => $stepCount]);
    }

    #[Test]
    public function it_matches_all_with_and_logic(): void
    {
        $matcher = CompositeMatcher::and(
            new ToolNameMatcher('bash'),
            new CallableMatcher(fn($ctx) => $ctx->get('stepCount') < 5),
        );

        // Both conditions true
        $this->assertTrue($matcher->matches($this->createToolContext('bash', 2)));

        // Tool name matches but step count too high
        $this->assertFalse($matcher->matches($this->createToolContext('bash', 10)));

        // Step count ok but wrong tool
        $this->assertFalse($matcher->matches($this->createToolContext('read_file', 2)));
    }

    #[Test]
    public function it_matches_any_with_or_logic(): void
    {
        $matcher = CompositeMatcher::or(
            new ToolNameMatcher('bash'),
            new ToolNameMatcher('read_file'),
        );

        $this->assertTrue($matcher->matches($this->createToolContext('bash')));
        $this->assertTrue($matcher->matches($this->createToolContext('read_file')));
        $this->assertFalse($matcher->matches($this->createToolContext('write_file')));
    }

    #[Test]
    public function it_handles_empty_matchers(): void
    {
        $andMatcher = CompositeMatcher::and();
        $orMatcher = CompositeMatcher::or();

        // Empty matchers return true (no conditions to fail)
        $context = $this->createToolContext('anything');
        $this->assertTrue($andMatcher->matches($context));
        $this->assertTrue($orMatcher->matches($context));
    }

    #[Test]
    public function it_supports_nested_composite_matchers(): void
    {
        // (bash OR read_*) AND stepCount < 5
        $matcher = CompositeMatcher::and(
            CompositeMatcher::or(
                new ToolNameMatcher('bash'),
                new ToolNameMatcher('read_*'),
            ),
            new CallableMatcher(fn($ctx) => $ctx->get('stepCount') < 5),
        );

        // bash with low step count
        $this->assertTrue($matcher->matches($this->createToolContext('bash', 2)));

        // read_file with low step count
        $this->assertTrue($matcher->matches($this->createToolContext('read_file', 2)));

        // bash with high step count
        $this->assertFalse($matcher->matches($this->createToolContext('bash', 10)));

        // write_file (doesn't match OR)
        $this->assertFalse($matcher->matches($this->createToolContext('write_file', 2)));
    }
}
