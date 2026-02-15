<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Hook\HookStack;
use Cognesy\Messages\Messages;

describe('AgentBuilder::build() idempotency', function () {
    it('produces a fresh interceptor on each build', function () {
        $builder = AgentBuilder::base()
            ->withCapability(new UseGuards(
                maxSteps: 3,
                maxTokens: null,
                maxExecutionTime: null,
            ));

        $loop1 = $builder->build();
        $loop2 = $builder->build();

        expect($loop1->interceptor())->toBeInstanceOf(HookStack::class);
        expect($loop2->interceptor())->toBeInstanceOf(HookStack::class);
        expect($loop1->interceptor())->not->toBe($loop2->interceptor());
    });

    it('produces equivalent runtime behavior across repeated builds', function () {
        $builder = AgentBuilder::base()
            ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('ok')))
            ->withCapability(new UseGuards(
                maxSteps: 3,
                maxTokens: null,
                maxExecutionTime: null,
            ));

        $loop1 = $builder->build();
        $loop2 = $builder->build();

        $state = AgentState::empty()->withMessages(Messages::fromString('ping'));
        $result1 = $loop1->execute($state);
        $result2 = $loop2->execute($state);

        expect($result1->stepCount())->toBe($result2->stepCount());
        expect($result1->finalResponse()->toString())->toBe($result2->finalResponse()->toString());
    });
});
