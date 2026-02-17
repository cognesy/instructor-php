<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Contracts\CanResolveInferencePricing;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\Pricing;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\Pricing\StaticPricingResolver;

it('resolves null when pricing is missing or empty', function () {
    $request = new InferenceRequest(messages: 'hello');

    $missing = new StaticPricingResolver();
    $empty = new StaticPricingResolver(new Pricing());

    expect($missing->resolvePricing($request))->toBeNull();
    expect($empty->resolvePricing($request))->toBeNull();
});

it('resolves configured pricing when it has values', function () {
    $request = new InferenceRequest(messages: 'hello');
    $pricing = new Pricing(inputPerMToken: 1.0, outputPerMToken: 2.0);
    $resolver = new StaticPricingResolver($pricing);

    expect($resolver->resolvePricing($request))->toBe($pricing);
});

it('uses pricing resolver contract in InferenceRuntime constructor', function () {
    $constructor = new \ReflectionMethod(InferenceRuntime::class, '__construct');
    $pricingResolver = $constructor->getParameters()[2];

    expect((string) $pricingResolver->getType())->toBe('?' . CanResolveInferencePricing::class);
});
