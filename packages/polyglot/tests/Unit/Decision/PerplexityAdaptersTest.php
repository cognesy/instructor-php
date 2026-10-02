<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Drivers\Perplexity\PerplexityRequestAdapter;
use Cognesy\Polyglot\Decision\Drivers\Perplexity\PerplexityResponseAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

const PERPLEXITY_LIVE_RESPONSE = __DIR__.'/../../Fixtures/Decision/perplexity-live-response.json';

it('renders the Perplexity System One body for the decisions endpoint', function (): void {
    $http = perplexityRequestAdapter()->toHttpClientRequest(perplexityRequest());
    $body = $http->body()->toArray();

    expect($http->url())->toBe('https://api.perplexity.ai/v1/decisions')
        ->and($http->method())->toBe('POST')
        ->and($http->headers())->toBe([
            'Authorization' => 'Bearer synthetic-test-key',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->and($body['model'])->toBe('pplx-decider-v1-27b')
        ->and($body['state'])->toBe('Checkout has been failing for every customer for the last hour.')
        ->and(array_keys($body['questions']))->toBe(['urgent', 'team', 'severity'])
        ->and($body['questions']['severity']['criteria'])->toBe(['No impact', 'Minor', 'Major', 'Critical']);
});

it('accepts a Noul defined by criteria alone and Choice or Score without instructions', function (): void {
    $request = new DecisionRequest(
        input: 'state',
        questions: Questions::of(
            new Noul('defect', criteria: new NoulCriteria(true: 'Something is broken.', false: 'Works as expected.')),
            new Choice('route', ChoiceOptions::of(new ChoiceOption('a'), new ChoiceOption('b'))),
            new Score('severity', ScoreLevels::of('low', 'high')),
        ),
    );
    $body = perplexityRequestAdapter()->toHttpClientRequest($request)->body()->toArray();

    expect($body['questions']['defect'])->not->toHaveKey('instructions')
        ->and($body['questions']['defect']['criteria'])->toBe(['true' => 'Something is broken.', 'false' => 'Works as expected.']);
});

it('rejects a Noul with neither instructions nor criteria before producing an HTTP request', function (): void {
    $request = new DecisionRequest(input: 'state', questions: Questions::of(new Noul('urgent')));

    expect(fn () => perplexityRequestAdapter()->toHttpClientRequest($request))
        ->toThrow(InvalidArgumentException::class, "instructions or criteria for Noul question 'urgent'");
});

it('requires a Perplexity API key', function (): void {
    $adapter = new PerplexityRequestAdapter(new DecisionConfig(
        driver: 'perplexity',
        apiUrl: 'https://api.perplexity.ai',
        endpoint: '/v1/decisions',
        model: 'pplx-decider-v1-27b',
    ));

    expect(fn () => $adapter->toHttpClientRequest(perplexityRequest()))
        ->toThrow(InvalidArgumentException::class, "field 'apiKey' is missing or empty");
});

it('decodes a recorded live Perplexity response', function (): void {
    $response = (new PerplexityResponseAdapter)->fromHttpResponse(perplexityHttpResponse(perplexityLiveBody()), perplexityRequest());

    expect($response->model())->toBe('pplx-decider-v1-27b')
        ->and($response->answers()->noul('urgent')->probability())->toBe(0.9912641811147593)
        ->and($response->answers()->choice('team')->value())->toBe('technical')
        ->and($response->answers()->choice('team')->confidence())->toBe(0.8902731489069797)
        ->and($response->answers()->score('severity')->value())->toBe(2.945849730275538)
        ->and($response->usage()->toArray())->toBe(['input' => 307, 'output' => 3])
        ->and($response->providerRequestId()->toString())->toBe('req-1');
});

it('fails closed when a Perplexity answer is missing', function (): void {
    $body = json_decode(perplexityLiveBody());
    unset($body->answers->team);

    expect(fn () => (new PerplexityResponseAdapter)->fromHttpResponse(
        perplexityHttpResponse(json_encode($body, JSON_THROW_ON_ERROR)),
        perplexityRequest(),
    ))->toThrow(DecisionResponseException::class, 'answer IDs');
});

it('fails closed when Perplexity omits usage token counts', function (): void {
    $body = json_decode(perplexityLiveBody());
    unset($body->usage->input_tokens);

    expect(fn () => (new PerplexityResponseAdapter)->fromHttpResponse(
        perplexityHttpResponse(json_encode($body, JSON_THROW_ON_ERROR)),
        perplexityRequest(),
    ))->toThrow(DecisionResponseException::class, "usage field 'input_tokens'");
});

it('rejects a non-JSON Perplexity response such as an HTML gateway page', function (): void {
    $http = HttpResponse::sync(200, ['content-type' => 'text/html'], '<html>gateway</html>');

    expect(fn () => (new PerplexityResponseAdapter)->fromHttpResponse($http, perplexityRequest()))
        ->toThrow(DecisionResponseException::class, 'content type must be application/json');
});

function perplexityRequestAdapter(): PerplexityRequestAdapter
{
    return new PerplexityRequestAdapter(new DecisionConfig(
        driver: 'perplexity',
        apiUrl: 'https://api.perplexity.ai/',
        apiKey: 'synthetic-test-key',
        endpoint: '/v1/decisions',
        model: 'pplx-decider-v1-27b',
    ));
}

function perplexityRequest(): DecisionRequest
{
    return new DecisionRequest(
        input: 'Checkout has been failing for every customer for the last hour.',
        questions: Questions::of(
            new Noul('urgent', 'Is this support request urgent?'),
            new Choice(
                id: 'team',
                options: ChoiceOptions::of(
                    new ChoiceOption('billing', 'Payments'),
                    new ChoiceOption('technical', 'Outages and errors'),
                    new ChoiceOption('sales', 'Plans'),
                ),
                instructions: 'Which team should handle this request?',
            ),
            new Score(
                id: 'severity',
                levels: ScoreLevels::of('No impact', 'Minor', 'Major', 'Critical'),
                instructions: 'How severe is the customer impact?',
            ),
        ),
    );
}

function perplexityLiveBody(): string
{
    $json = file_get_contents(PERPLEXITY_LIVE_RESPONSE);
    if (! is_string($json)) {
        throw new RuntimeException('Perplexity live response fixture cannot be read.');
    }

    return $json;
}

function perplexityHttpResponse(string $body): HttpResponse
{
    return HttpResponse::sync(200, ['content-type' => 'application/json', 'x-request-id' => 'req-1'], $body);
}
