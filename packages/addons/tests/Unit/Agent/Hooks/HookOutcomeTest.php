<?php declare(strict_types=1);

namespace Packages\addons\tests\Unit\Agent\Hooks;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Hooks\Data\ExecutionHookContext;
use Cognesy\Addons\Agent\Hooks\Data\HookOutcome;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HookOutcomeTest extends TestCase
{
    #[Test]
    public function it_creates_proceed_outcome(): void
    {
        $outcome = HookOutcome::proceed();

        $this->assertTrue($outcome->isProceed());
        $this->assertFalse($outcome->isBlocked());
        $this->assertFalse($outcome->isStopped());
        $this->assertNull($outcome->reason());
        $this->assertNull($outcome->context());
        $this->assertEquals('proceed', $outcome->type());
    }

    #[Test]
    public function it_creates_proceed_with_modified_context(): void
    {
        $context = ExecutionHookContext::onStart(AgentState::empty());
        $outcome = HookOutcome::proceed($context);

        $this->assertTrue($outcome->isProceed());
        $this->assertSame($context, $outcome->context());
    }

    #[Test]
    public function it_creates_block_outcome(): void
    {
        $outcome = HookOutcome::block('Test reason');

        $this->assertFalse($outcome->isProceed());
        $this->assertTrue($outcome->isBlocked());
        $this->assertFalse($outcome->isStopped());
        $this->assertEquals('Test reason', $outcome->reason());
        $this->assertNull($outcome->context());
        $this->assertEquals('block', $outcome->type());
    }

    #[Test]
    public function it_creates_stop_outcome(): void
    {
        $outcome = HookOutcome::stop('Stop reason');

        $this->assertFalse($outcome->isProceed());
        $this->assertFalse($outcome->isBlocked());
        $this->assertTrue($outcome->isStopped());
        $this->assertEquals('Stop reason', $outcome->reason());
        $this->assertNull($outcome->context());
        $this->assertEquals('stop', $outcome->type());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $outcome = HookOutcome::block('Test');

        $array = $outcome->toArray();

        $this->assertEquals([
            'type' => 'block',
            'reason' => 'Test',
            'hasContext' => false,
        ], $array);
    }

    #[Test]
    public function it_converts_to_array_with_context(): void
    {
        $context = ExecutionHookContext::onStart(AgentState::empty());
        $outcome = HookOutcome::proceed($context);

        $array = $outcome->toArray();

        $this->assertEquals([
            'type' => 'proceed',
            'reason' => null,
            'hasContext' => true,
        ], $array);
    }
}
