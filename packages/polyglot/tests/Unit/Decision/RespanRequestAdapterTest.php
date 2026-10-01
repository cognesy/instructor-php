<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Drivers\Respan\RespanRequestAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;

it('renders an authenticated RESPAN span and deterministic behavior definitions', function (): void {
    $request = new DecisionRequest(
        input: respanSpanInput(),
        questions: Questions::of(
            new Noul(
                id: 'apology',
                instructions: 'The assistant apologizes for a problem.',
                criteria: new NoulCriteria(
                    true: 'The reply contains an apology.',
                    false: 'The reply does not apologize.',
                ),
            ),
        ),
        model: 'span-01-free',
    );

    $http = (new RespanRequestAdapter(respanRequestConfig()))->toHttpClientRequest($request);
    $body = $http->body()->toArray();

    expect($http->url())->toBe('https://api.respan.ai/api/v1/scores')
        ->and($http->headers()['Authorization'])->toBe('Bearer test-key')
        ->and($body['model'])->toBe('span-01-free')
        ->and($body['span']['input'][0]['content'])->toBe('My order is late.')
        ->and($body['span']['output']['content'])->toBe('I am sorry.')
        ->and($body)->not->toHaveKey('respan_params')
        ->and($body['behaviors'])->toBe([[
            'id' => 'apology',
            'definition' => "The assistant apologizes for a problem.\n"
                .'Present when: The reply contains an apology.'."\n"
                .'Absent when: The reply does not apologize.',
        ]]);
});

it('rejects ambiguous input unsupported questions missing definitions and unknown models', function (): void {
    $adapter = new RespanRequestAdapter(respanRequestConfig());
    $choice = new Choice(
        'route',
        ChoiceOptions::of(new ChoiceOption('a'), new ChoiceOption('b')),
        'Choose a route.',
    );

    expect(fn () => $adapter->toHttpClientRequest(new DecisionRequest(
        input: 'plain text',
        questions: Questions::of(new Noul('q', 'A valid definition.')),
        model: 'span-01-free',
    )))->toThrow(DecisionInvalidRequestException::class, 'span object')
        ->and(fn () => $adapter->toHttpClientRequest(new DecisionRequest(
            input: respanSpanInput(),
            questions: Questions::of($choice),
            model: 'span-01-free',
        )))->toThrow(DecisionInvalidRequestException::class, 'only Noul')
        ->and(fn () => $adapter->toHttpClientRequest(new DecisionRequest(
            input: respanSpanInput(),
            questions: Questions::of(new Noul('q')),
            model: 'span-01-free',
        )))->toThrow(DecisionInvalidRequestException::class, 'require instructions')
        ->and(fn () => $adapter->toHttpClientRequest(new DecisionRequest(
            input: respanSpanInput(),
            questions: Questions::of(new Noul('q', 'Valid definition.')),
            model: 'span-02',
        )))->toThrow(DecisionInvalidRequestException::class, 'does not support model');
});

function respanSpanInput(): JsonContent
{
    return JsonContent::object([
        'input' => [
            ['role' => 'user', 'content' => 'My order is late.'],
        ],
        'output' => ['role' => 'assistant', 'content' => 'I am sorry.'],
    ]);
}

function respanRequestConfig(): DecisionConfig
{
    return new DecisionConfig(
        driver: 'respan',
        apiUrl: 'https://api.respan.ai',
        apiKey: 'test-key',
        endpoint: '/api/v1/scores',
        model: 'span-01-free',
    );
}
