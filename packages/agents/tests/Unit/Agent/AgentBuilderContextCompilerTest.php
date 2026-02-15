<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseContextCompiler;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Context\CanAcceptMessageCompiler;
use Cognesy\Agents\Context\CanCompileMessages;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Messages\Messages;

describe('AgentBuilder context compiler wiring', function () {
    it('injects compiler into explicit drivers that accept compiler dependency', function () {
        $compiler = new class implements CanCompileMessages {
            public function compile(AgentState $state): Messages {
                return Messages::fromString('COMPILED VIEW', 'assistant');
            }
        };

        $agent = AgentBuilder::base()
            ->withCapability(new UseDriver(new FakeAgentDriver([ScenarioStep::final('ok')])))
            ->withCapability(new UseContextCompiler($compiler))
            ->build();

        $final = $agent->execute(AgentState::empty()->withUserMessage('ORIGINAL INPUT'));
        $step = $final->steps()->stepAt(0);
        $input = $step?->inputMessages()->toString() ?? '';

        expect($input)->toContain('COMPILED VIEW')
            ->and($input)->not->toContain('ORIGINAL INPUT');
    });

    it('injects compiler into default ToolCalling driver', function () {
        $compiler = new class implements CanCompileMessages {
            public function compile(AgentState $state): Messages {
                return Messages::fromString('DEFAULT DRIVER VIEW', 'assistant');
            }
        };

        $agent = AgentBuilder::base()
            ->withCapability(new UseContextCompiler($compiler))
            ->build();

        $driver = $agent->driver();
        expect($driver)->toBeInstanceOf(CanAcceptMessageCompiler::class)
            ->and($driver->messageCompiler())->toBe($compiler);
    });
});
