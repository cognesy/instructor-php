<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Drivers\Jeff\JeffRequestAdapter;
use Cognesy\Polyglot\Decision\Questions\Noul;

it('renders System One requests for optional and authenticated Jeff deployments', function (): void {
    $request = new DecisionRequest('state', Questions::of(new Noul('safe')));
    $keyless = (new JeffRequestAdapter(jeffRequestConfig()))->toHttpClientRequest($request);
    $authenticated = (new JeffRequestAdapter(jeffRequestConfig('test-key')))->toHttpClientRequest($request);

    expect($keyless->url())->toBe('http://127.0.0.1:8000/v1/systemone')
        ->and($keyless->headers())->not->toHaveKey('Authorization')
        ->and($keyless->body()->toArray())->toMatchArray([
            'state' => 'state',
            'model' => 'gliformer-large-v1',
        ])
        ->and($authenticated->headers()['Authorization'])->toBe('Bearer test-key');
});

function jeffRequestConfig(string $apiKey = ''): DecisionConfig
{
    return new DecisionConfig(
        driver: 'jeff',
        apiUrl: 'http://127.0.0.1:8000',
        apiKey: $apiKey,
        endpoint: '/v1/systemone',
        model: 'gliformer-large-v1',
    );
}
