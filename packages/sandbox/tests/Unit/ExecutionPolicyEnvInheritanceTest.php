<?php declare(strict_types=1);

use Cognesy\Sandbox\Config\ExecutionPolicy;

it('preserves inheritEnv when applying withEnv without explicit inherit override', function () {
    $policy = ExecutionPolicy::in('/tmp')
        ->inheritEnvironment(true)
        ->withEnv(['FOO' => 'BAR']);

    expect($policy->inheritEnv())->toBeTrue();
    expect($policy->env())->toBe(['FOO' => 'BAR']);
});

it('allows explicitly overriding inheritEnv in withEnv', function () {
    $policy = ExecutionPolicy::in('/tmp')
        ->inheritEnvironment(true)
        ->withEnv(['FOO' => 'BAR'], false);

    expect($policy->inheritEnv())->toBeFalse();
    expect($policy->env())->toBe(['FOO' => 'BAR']);
});
