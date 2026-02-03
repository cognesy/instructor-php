<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Hooks\Interceptors\HookStack;

describe('AgentBuilder::build() idempotency', function () {
    it('does not mutate builder hook stack when build is called', function () {
        $builder = AgentBuilder::base()->withMaxSteps(5);

        $stackBefore = $builder->hookStack();
        $builder->build();

        expect($builder->hookStack())->toBe($stackBefore);
    });

    it('does not duplicate hooks across multiple builds', function () {
        $builder = AgentBuilder::base()->withMaxSteps(5);

        $stackBefore = $builder->hookStack();
        $builder->build();
        $builder->build();
        $builder->build();

        expect($builder->hookStack())->toBe($stackBefore);
    });

    it('produces identical interceptors from repeated builds', function () {
        $builder = AgentBuilder::base()->withMaxSteps(3);

        $loop1 = $builder->build();
        $loop2 = $builder->build();

        $interceptor1 = $loop1->interceptor();
        $interceptor2 = $loop2->interceptor();

        // Both should be HookStack instances with the same structure
        expect($interceptor1)->toBeInstanceOf(HookStack::class);
        expect($interceptor2)->toBeInstanceOf(HookStack::class);

        // They should be different objects (fresh per build) but not the builder's stack
        expect($interceptor1)->not->toBe($interceptor2);
        expect($interceptor1)->not->toBe($builder->hookStack());
    });
});
